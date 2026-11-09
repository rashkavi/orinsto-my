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
            $user = SessionUser::getCurrentUserObject();
            $addedBy = (int)$user["user_id"];

            $status = new stdClass();
            $errors = [];
            $componentErrors = [];

            $userId = isset($_POST["user_id"]) ? (int)$_POST["user_id"] : 0;
            $employmentType = trim($_POST["employment_type"] ?? "");
            $epfNo = trim($_POST["epf_no"] ?? "");
            $basicSalary = isset($_POST["basic_salary"]) ? (float)$_POST["basic_salary"] : 0;

            $epfEmployeeRate = isset($_POST["epf_employee_rate"]) ? (float)$_POST["epf_employee_rate"] : 8;
            $epfEmployerRate = isset($_POST["epf_employer_rate"]) ? (float)$_POST["epf_employer_rate"] : 12;
            $etfRate = isset($_POST["etf_rate"]) ? (float)$_POST["etf_rate"] : 3;

            $isApitApplicable = isset($_POST["is_apit_applicable"]) ? (int)$_POST["is_apit_applicable"] : 0;

            $paymentMethod = trim($_POST["payment_method"] ?? "BANK");
            $bankName = trim($_POST["bank_name"] ?? "");
            $bankAccountNo = trim($_POST["bank_account_no"] ?? "");
            $bankBranch = trim($_POST["bank_branch"] ?? "");

            // Salary pay date (used by Payments → Staff Payments)
            $salaryPayDayRaw = trim((string)($_POST["salary_pay_day"] ?? ""));
            $salaryPayTiming = trim((string)($_POST["salary_pay_timing"] ?? "NEXT_MONTH"));

            $components = json_decode($_POST["components"] ?? "[]", true);

            $allowedEmploymentTypes = ["EMPLOYEE", "INTERN", "CONTRACT"];
            $allowedPaymentMethods = ["BANK", "CASH", "CHEQUE"];
            $allowedDeductionBases = ["GROSS", "NET"];

            if ($userId <= 0) {
                $errors["user_id"] = "Employee is required";
            }

            if (!in_array($employmentType, $allowedEmploymentTypes)) {
                $errors["employment_type"] = "Employment type is required";
            }

            if (!empty($epfNo)) {
                $isValidEpfNo = Database::search("SELECT `epf_no` FROM `payroll_profiles` WHERE `epf_no` = '$epfNo'");
                if ($isValidEpfNo->num_rows > 0) {
                    $errors["epf_no"] = "EPF number already exists";
                }
            }

            if ($basicSalary <= 0) {
                $errors["basic_salary"] = "Basic salary is required";
            }

            if ($epfEmployeeRate < 0) {
                $errors["epf_employee_rate"] = "Invalid EPF employee rate";
            }

            if ($epfEmployerRate < 0) {
                $errors["epf_employer_rate"] = "Invalid EPF employer rate";
            }

            if ($etfRate < 0) {
                $errors["etf_rate"] = "Invalid ETF rate";
            }

            if (!in_array($paymentMethod, $allowedPaymentMethods)) {
                $paymentMethod = "BANK";
            }

            if ($paymentMethod === "BANK") {
                if ($bankName === "") {
                    $errors["bank_name"] = "Bank name is required";
                }

                if ($bankAccountNo === "") {
                    $errors["bank_account_no"] = "Bank account number is required";
                }

                if ($bankBranch === "") {
                    $errors["bank_branch"] = "Bank branch is required";
                }
            }

            if (!ctype_digit($salaryPayDayRaw) || (int)$salaryPayDayRaw < 1 || (int)$salaryPayDayRaw > 31) {
                $errors["salary_pay_day"] = "Select the salary pay day (1 - 31)";
            }
            $salaryPayDay = (int)$salaryPayDayRaw;

            if (!in_array($salaryPayTiming, ["SAME_MONTH", "NEXT_MONTH"], true)) {
                $errors["salary_pay_timing"] = "Select when the salary is paid";
            }

            $userRs = Database::search("
                SELECT `user_id`
                FROM `users`
                WHERE `user_id` = '$userId'
                LIMIT 1
            ");

            if ($userRs->num_rows === 0) {
                $errors["user_id"] = "Employee not found";
            }

            $existingProfile = Database::search("
                SELECT `payroll_profile_id`
                FROM `payroll_profiles`
                WHERE `users_user_id` = '$userId'
                AND `status_status_id` = '1'
                LIMIT 1
            ");

            if ($existingProfile->num_rows > 0) {
                $errors["user_id"] = "Active payroll profile already exists for this employee";
            }

            if (!is_array($components)) {
                $components = [];
            }

            foreach ($components as $component) {
                $index = (int)($component["index"] ?? 1);

                $componentName = trim($component["component_name"] ?? "");
                $componentType = trim($component["component_type"] ?? "");
                $amount = isset($component["amount"]) ? (float)$component["amount"] : 0;
                $deductionBase = trim($component["deduction_base"] ?? "GROSS");

                if ($componentName === "") {
                    $componentErrors[] = [
                        "index" => $index,
                        "input" => ".component-name",
                        "feedback" => ".component-name-feedback",
                        "message" => "Component name is required"
                    ];
                }

                if (!in_array($componentType, ["EARNING", "DEDUCTION"])) {
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

                // Validate deduction base only for deductions
                if ($componentType === "DEDUCTION" && !in_array($deductionBase, $allowedDeductionBases)) {
                    $componentErrors[] = [
                        "index" => $index,
                        "input" => ".component-deduction-base",
                        "feedback" => ".component-deduction-base-feedback",
                        "message" => "Invalid deduction calculation base"
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

            $bankName = addslashes($bankName);
            $bankAccountNo = addslashes($bankAccountNo);
            $bankBranch = addslashes($bankBranch);

            $isEpfApplicable = 1;
            $isEtfApplicable = 1;

            if (empty($epfNo)) {
                $epfNo = NULL;
            }

            Database::iud("
                INSERT INTO `payroll_profiles`
                (
                    `users_user_id`,
                    `basic_salary`,
                    `epf_employee_rate`,
                    `epf_employer_rate`,
                    `etf_rate`,
                    `is_epf_applicable`,
                    `is_etf_applicable`,
                    `is_apit_applicable`,
                    `employment_type`,
                    `payment_method`,
                    `bank_name`,
                    `bank_account_no`,
                    `bank_branch`,
                    `status_status_id`,
                    `date_added`,
                    `epf_no`,
                    `salary_pay_day`,
                    `salary_pay_timing`
                )
                VALUES
                (
                    '$userId',
                    '$basicSalary',
                    '$epfEmployeeRate',
                    '$epfEmployerRate',
                    '$etfRate',
                    '$isEpfApplicable',
                    '$isEtfApplicable',
                    '$isApitApplicable',
                    '$employmentType',
                    '$paymentMethod',
                    '$bankName',
                    '$bankAccountNo',
                    '$bankBranch',
                    '1',
                    NOW(),
                    '$epfNo',
                    '$salaryPayDay',
                    '$salaryPayTiming'
                )
            ");

            $profileIdRs = Database::search("SELECT LAST_INSERT_ID() AS id");
            $profileId = (int)$profileIdRs->fetch_assoc()["id"];

            foreach ($components as $component) {
                $componentName = addslashes(trim($component["component_name"] ?? ""));
                $componentType = trim($component["component_type"] ?? "");
                $amount = (float)($component["amount"] ?? 0);
                $isEpf = (int)($component["is_epf_applicable"] ?? 0);
                $isTaxable = (int)($component["is_taxable"] ?? 0);
                $isRecurring = (int)($component["is_recurring"] ?? 1);
                $deductionBase = trim($component["deduction_base"] ?? "GROSS");

                // Default deduction base to GROSS if not a deduction
                if ($componentType !== "DEDUCTION") {
                    $deductionBase = "GROSS";
                }

                if ($componentName === "") {
                    continue;
                }

                Database::iud("
                    INSERT INTO `payroll_profile_components`
                    (
                        `payroll_profiles_payroll_profile_id`,
                        `payroll_components_payroll_component_id`,
                        `component_type`,
                        `calculation_type`,
                        `amount`,
                        `is_epf_applicable`,
                        `is_taxable`,
                        `is_recurring`,
                        `deduction_calculation_base`,
                        `status_status_id`
                    )
                    VALUES
                    (
                        '$profileId',
                        '$componentName',
                        '$componentType',
                        'FIXED',
                        '$amount',
                        '$isEpf',
                        '$isTaxable',
                        '$isRecurring',
                        '$deductionBase',
                        '1'
                    )
                ");
            }

            echo json_encode([
                "status" => "success",
                "message" => "Payroll profile created successfully"
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
