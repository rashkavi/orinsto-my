<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/classes/StudentIdGenerator.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/student-mail-body.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            $errors = [];
            $componentErrors = [];

            $payrollRunEmployeeId = isset($_POST["payroll_run_employee_id"]) ? (int)$_POST["payroll_run_employee_id"] : 0;
            $basicSalary = isset($_POST["basic_salary"]) ? (float)$_POST["basic_salary"] : 0;
            $components = json_decode($_POST["components"] ?? "[]", true);

            if ($payrollRunEmployeeId <= 0) {
                echo json_encode(["status" => "error", "message" => "Invalid payroll employee record"]);
                exit;
            }

            if ($basicSalary <= 0) {
                $errors["basic_salary"] = "Basic salary is required";
            }

            $employeeRs = Database::search("
                            SELECT 
                                pre.*,
                                pp.epf_employee_rate,
                                pp.epf_employer_rate,
                                pp.etf_rate,
                                pp.is_epf_applicable,
                                pp.is_etf_applicable
                            FROM payroll_run_employees pre
                            LEFT JOIN payroll_profiles pp
                                ON pre.payroll_profiles_payroll_profile_id = pp.payroll_profile_id
                            WHERE pre.payroll_run_employee_id = '$payrollRunEmployeeId'
                            LIMIT 1
                        ");

            if ($employeeRs->num_rows == 0) {
                echo json_encode(["status" => "error", "message" => "Payroll employee record not found"]);
                exit;
            }

            $employee = $employeeRs->fetch_assoc();

            if ($employee["employee_payroll_status"] == "PAID") {
                echo json_encode(["status" => "error", "message" => "Paid payroll records cannot be modified"]);
                exit;
            }

            if (!is_array($components)) {
                $components = [];
            }

            foreach ($components as $component) {
                $index = (int)($component["index"] ?? 1);
                $name = trim($component["component_name"] ?? "");
                $type = trim($component["component_type"] ?? "");
                $amount = (float)($component["amount"] ?? 0);

                if ($name == "") {
                    $componentErrors[] = [
                        "index" => $index,
                        "input" => ".component-name",
                        "feedback" => ".component-name-feedback",
                        "message" => "Component name is required"
                    ];
                }

                if (!in_array($type, ["EARNING", "DEDUCTION"])) {
                    $componentErrors[] = [
                        "index" => $index,
                        "input" => ".component-type",
                        "feedback" => ".component-type-feedback",
                        "message" => "Component type is required"
                    ];
                }

                if ($amount < 0) {
                    $componentErrors[] = [
                        "index" => $index,
                        "input" => ".component-amount",
                        "feedback" => ".component-amount-feedback",
                        "message" => "Invalid component amount"
                    ];
                }
            }

            if (!empty($errors) || !empty($componentErrors)) {
                echo json_encode([
                    "status" => "validation_error",
                    "errors" => $errors,
                    "component_errors" => $componentErrors
                ]);
                exit;
            }

            $payrollRunTotals = Database::search("SELECT * FROM `payroll_runs` WHERE `payroll_run_id`='" . $employee["payroll_runs_payroll_run_id"] . "'");
            $payrollRunTotal = $payrollRunTotals->fetch_assoc();

            $total_gross_salary = (float)$payrollRunTotal["total_gross_salary"] - (float)$employee["gross_salary"];
            $total_deductions = (float)$payrollRunTotal["total_deductions"] - (float)$employee["total_deductions"];
            $total_net_salary = (float)$payrollRunTotal["total_net_salary"] - (float)$employee["net_salary"];
            $total_employee_epf = (float)$payrollRunTotal["total_employee_epf"] - (float)$employee["employee_epf"];
            $total_employer_epf = (float)$payrollRunTotal["total_employer_epf"] - (float)$employee["employer_epf"];
            $total_etf = (float)$payrollRunTotal["total_etf"] - (float)$employee["etf"];

            $epfEmployeeRate = (float)($employee["epf_employee_rate"] ?? 8);
            $epfEmployerRate = (float)($employee["epf_employer_rate"] ?? 12);
            $etfRate = (float)($employee["etf_rate"] ?? 3);

            $isEpfApplicable = (int)($employee["is_epf_applicable"] ?? 1);
            $isEtfApplicable = (int)($employee["is_etf_applicable"] ?? 1);

            $totalEarnings = 0;
            $deductionComponentsTotal = 0;
            $epfApplicableEarnings = 0;

            foreach ($components as $component) {
                $type = $component["component_type"];
                $amount = (float)$component["amount"];

                if ($type == "EARNING") {
                    $totalEarnings += $amount;

                    if ((int)$component["is_epf_applicable"] == 1) {
                        $epfApplicableEarnings += $amount;
                    }
                }

                if ($type == "DEDUCTION") {
                    $deductionComponentsTotal += $amount;
                }
            }

            $epfSalary = $isEpfApplicable == 1 ? $basicSalary + $epfApplicableEarnings : 0;
            $employeeEpf = $isEpfApplicable == 1 ? ($epfSalary * $epfEmployeeRate / 100) : 0;
            $employerEpf = $isEpfApplicable == 1 ? ($epfSalary * $epfEmployerRate / 100) : 0;
            $etf = $isEtfApplicable == 1 ? ($epfSalary * $etfRate / 100) : 0;

            $grossSalary = $basicSalary + $totalEarnings;
            $totalDeductions = $deductionComponentsTotal + $employeeEpf;
            $netSalary = $grossSalary - $totalDeductions;

            $total_gross_salary = $total_gross_salary + $grossSalary;
            $total_deductions = $total_deductions + $totalDeductions;
            $total_net_salary = $total_net_salary + $netSalary;
            $total_employee_epf = $total_employee_epf + $employeeEpf;
            $total_employer_epf = $total_employer_epf + $employerEpf;
            $total_etf = $total_etf + $etf;

            Database::iud("
                UPDATE payroll_run_employees
                SET 
                    basic_salary = '$basicSalary',
                    total_earnings = '$totalEarnings',
                    gross_salary = '$grossSalary',
                    epf_salary = '$epfSalary',
                    employee_epf = '$employeeEpf',
                    employer_epf = '$employerEpf',
                    etf = '$etf',
                    total_deductions = '$totalDeductions',
                    net_salary = '$netSalary'
                WHERE payroll_run_employee_id = '$payrollRunEmployeeId'
            ");

            Database::iud("
                UPDATE payroll_runs
                SET 
                    total_gross_salary = '$total_gross_salary',
                    total_deductions = '$total_deductions',
                    total_net_salary = '$total_net_salary',
                    total_employee_epf = '$total_employee_epf',
                    total_employer_epf = '$total_employer_epf',
                    total_etf = '$total_etf'
                WHERE payroll_run_id = '" . $employee["payroll_runs_payroll_run_id"] . "'
            ");

            Database::iud("
                    DELETE FROM payroll_run_employee_components
                    WHERE payroll_run_employees_payroll_run_employee_id = '$payrollRunEmployeeId'
                ");

            foreach ($components as $component) {
                $componentId = (int)($component["component_id"] ?? 0);
                $name = addslashes(trim($component["component_name"]));
                $type = addslashes($component["component_type"]);
                $amount = (float)$component["amount"];
                $isEpf = (int)$component["is_epf_applicable"];
                $isTaxable = (int)$component["is_taxable"];
                $note = addslashes(trim($component["note"] ?? ""));

                $amountSource = $componentId > 0 ? "MANUAL_ADJUSTMENT" : "MANUAL_ADJUSTMENT";

                Database::iud("
        INSERT INTO payroll_run_employee_components
        (
            payroll_run_employees_payroll_run_employee_id,
            payroll_profile_components_payroll_profile_component_id,
            payroll_components_payroll_component_id,
            component_type,
            amount,
            is_epf_applicable,
            is_taxable,
            amount_source,
            note
        )
        VALUES
        (
            '$payrollRunEmployeeId',
            NULL,
            '$name',
            '$type',
            '$amount',
            '$isEpf',
            '$isTaxable',
            '$amountSource',
            '$note'
        )
    ");
            }

            $runId = (int)$employee["payroll_runs_payroll_run_id"];

            $summaryRs = Database::search("
                SELECT 
                    COUNT(*) AS total_employees,
                    SUM(gross_salary) AS total_gross_salary,
                    SUM(total_deductions) AS total_deductions,
                    SUM(net_salary) AS total_net_salary,
                    SUM(employee_epf) AS total_employee_epf,
                    SUM(employer_epf) AS total_employer_epf,
                    SUM(etf) AS total_etf
                FROM payroll_run_employees
                WHERE payroll_runs_payroll_run_id = '$runId'
            ");

            $summary = $summaryRs->fetch_assoc();

            Database::iud("
                UPDATE payroll_runs
                SET
                    total_employees = '" . (int)$summary["total_employees"] . "',
                    total_gross_salary = '" . (float)$summary["total_gross_salary"] . "',
                    total_deductions = '" . (float)$summary["total_deductions"] . "',
                    total_net_salary = '" . (float)$summary["total_net_salary"] . "',
                    total_employee_epf = '" . (float)$summary["total_employee_epf"] . "',
                    total_employer_epf = '" . (float)$summary["total_employer_epf"] . "',
                    total_etf = '" . (float)$summary["total_etf"] . "'
                WHERE payroll_run_id = '$runId'
            ");

            Database::iud("INSERT INTO `payroll_run_employees_logs`
            (
                `payroll_run_employees_payroll_run_employee_id`,
                `users_user_id`,
                `log`,
                `date_updated`
            )
            VALUES
            (
                '$payrollRunEmployeeId',
                '" . $sessionUserObject["user_id"] . "',
                'Updated payroll employee details',
                NOW()
            )");

            echo json_encode([
                "status" => "success",
                "message" => "Payroll employee details updated successfully"
            ]);
            exit;
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
