<?php

// declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../includes/connection.php");
require_once(__DIR__ . "/../includes/UserClass.php");
require_once(__DIR__ . "/../includes/Project.php");

// if (php_sapi_name() !== 'cli') {
//     exit("Access denied");
// }

// Optional log file
$logFile = __DIR__ . '/logs/genarateMonthlyPayrollRun.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    $month = (int)date("m");
    $year = (int)date("Y");

    $periodStart = date("Y-m-01");
    $periodEnd = date("Y-m-t");

    $existingRun = Database::search("
        SELECT `payroll_run_id`
        FROM `payroll_runs`
        WHERE `payroll_month` = '$month'
        AND `payroll_year` = '$year'
        LIMIT 1
    ");

    if ($existingRun->num_rows > 0) {
        echo json_encode([
            "status" => "error",
            "message" => "Payroll run already exists for " . date("F Y")
        ]);
        exit;
    }

    Database::iud("
        INSERT INTO `payroll_runs`
        (
            `payroll_month`,
            `payroll_year`,
            `payroll_period_start`,
            `payroll_period_end`,
            `payroll_status`,
           
            `date_added`,
            `calculated_at`,
            `note`
        )
        VALUES
        (
            '$month',
            '$year',
            '$periodStart',
            '$periodEnd',
            'CALCULATED',
           
            NOW(),
            NOW(),
            'Monthly payroll run generated automatically by cron job.'
        )
    ");

    $runRs = Database::search("SELECT LAST_INSERT_ID() AS id");
    $payrollRunId = (int)$runRs->fetch_assoc()["id"];

    $profiles = Database::search("
        SELECT *
        FROM `payroll_profiles`
        WHERE `status_status_id` = '1'
    ");

    $totalEmployees = 0;
    $totalGrossSalary = 0;
    $totalDeductions = 0;
    $totalNetSalary = 0;
    $totalEmployeeEpf = 0;
    $totalEmployerEpf = 0;
    $totalEtf = 0;

    while ($profile = $profiles->fetch_assoc()) {

        $profileId = (int)$profile["payroll_profile_id"];
        $userId = (int)$profile["users_user_id"];

        $basicSalary = (float)$profile["basic_salary"];

        $epfEmployeeRate = (float)$profile["epf_employee_rate"];
        $epfEmployerRate = (float)$profile["epf_employer_rate"];
        $etfRate = (float)$profile["etf_rate"];

        $isEpfApplicable = (int)$profile["is_epf_applicable"];
        $isEtfApplicable = (int)$profile["is_etf_applicable"];
        $isApitApplicable = (int)$profile["is_apit_applicable"];

        $earningComponentsTotal = 0;
        $deductionComponentsGrossTotal = 0;  // GROSS-based deductions
        $deductionComponentsNetTotal = 0;    // NET-based deductions
        $epfApplicableEarnings = 0;

        $components = [];

        $componentRs = Database::search("
            SELECT *
            FROM `payroll_profile_components`
            WHERE `payroll_profiles_payroll_profile_id` = '$profileId'
            AND `status_status_id` = '1'
            AND `is_recurring` = '1'
        ");

        while ($component = $componentRs->fetch_assoc()) {

            $amount = (float)$component["amount"];

            if ($component["component_type"] === "EARNING") {
                $earningComponentsTotal += $amount;

                if ((int)$component["is_epf_applicable"] === 1) {
                    $epfApplicableEarnings += $amount;
                }
            }

            // ========================================
            // CORRECTED: Separate deductions by base
            // ========================================
            if ($component["component_type"] === "DEDUCTION") {
                $calculationBase = isset($component["deduction_calculation_base"])
                    ? $component["deduction_calculation_base"]
                    : 'GROSS';

                if ($calculationBase === "GROSS") {
                    $deductionComponentsGrossTotal += $amount;
                } else {
                    $deductionComponentsNetTotal += $amount;
                }
            }

            $components[] = $component;
        }

        $epfSalary = $isEpfApplicable === 1
            ? $basicSalary + $epfApplicableEarnings
            : 0;

        $employeeEpf = $isEpfApplicable === 1
            ? ($epfSalary * $epfEmployeeRate / 100)
            : 0;

        $employerEpf = $isEpfApplicable === 1
            ? ($epfSalary * $epfEmployerRate / 100)
            : 0;

        $etf = $isEtfApplicable === 1
            ? ($epfSalary * $etfRate / 100)
            : 0;

        $apitAmount = $isApitApplicable === 1
            ? 0
            : 0;

        $totalEarnings = $earningComponentsTotal;
        $grossSalary = $basicSalary + $totalEarnings;

        // ========================================
        // CORRECTED: Calculate net salary properly
        // ========================================
        // Step 1: Apply GROSS-based deductions first
        $salaryAfterGrossDeductions = $grossSalary - $deductionComponentsGrossTotal;

        // Step 2: Apply NET-based deductions to remaining amount
        $salaryAfterNetDeductions = $salaryAfterGrossDeductions - $deductionComponentsNetTotal;

        // Step 3: Apply EPF and other statutory deductions
        $employeeTotalDeductions = $employeeEpf + $deductionComponentsGrossTotal + $deductionComponentsNetTotal + $apitAmount;

        // Final Net Salary
        $netSalary = $salaryAfterNetDeductions;

        Database::iud("
            INSERT INTO `payroll_run_employees`
            (
                `payroll_runs_payroll_run_id`,
                `users_user_id`,
                `payroll_profiles_payroll_profile_id`,
                `basic_salary`,
                `total_earnings`,
                `gross_salary`,
                `epf_salary`,
                `employee_epf`,
                `employer_epf`,
                `etf`,
                `apit_amount`,
                `total_deductions`,
                `net_salary`,
                `payment_method`,
                `bank_name`,
                `bank_account_no`,
                `bank_branch`,
                `employee_payroll_status`,
                `date_added`
            )
            VALUES
            (
                '$payrollRunId',
                '$userId',
                '$profileId',
                '$basicSalary',
                '$totalEarnings',
                '$grossSalary',
                '$epfSalary',
                '$employeeEpf',
                '$employerEpf',
                '$etf',
                '$apitAmount',
                '$employeeTotalDeductions',
                '$netSalary',
                '" . addslashes($profile["payment_method"]) . "',
                '" . addslashes($profile["bank_name"] ?? "") . "',
                '" . addslashes($profile["bank_account_no"] ?? "") . "',
                '" . addslashes($profile["bank_branch"] ?? "") . "',
                'DRAFT',
                NOW()
            )
        ");

        $employeeRunRs = Database::search("SELECT LAST_INSERT_ID() AS id");
        $payrollRunEmployeeId = (int)$employeeRunRs->fetch_assoc()["id"];

        foreach ($components as $component) {

            Database::iud("
                INSERT INTO `payroll_run_employee_components`
                (
                    `payroll_run_employees_payroll_run_employee_id`,
                    `payroll_profile_components_payroll_profile_component_id`,
                    `payroll_components_payroll_component_id`,
                    `component_type`,
                    `amount`,
                    `is_epf_applicable`,
                    `is_taxable`,
                    `amount_source`,
                    `note`,
                    `deduction_calculation_base`
                )
                VALUES
                (
                    '$payrollRunEmployeeId',
                    '" . (int)$component["payroll_profile_component_id"] . "',
                    '" . addslashes($component["payroll_components_payroll_component_id"]) . "',
                    '" . addslashes($component["component_type"]) . "',
                    '" . (float)$component["amount"] . "',
                    '" . (int)$component["is_epf_applicable"] . "',
                    '" . (int)$component["is_taxable"] . "',
                    'DEFAULT',
                    'Auto generated from payroll profile.',
                    '" . addslashes($component["deduction_calculation_base"]) . "'
                )
            ");
        }

        $totalEmployees++;
        $totalGrossSalary += $grossSalary;
        $totalDeductions += $employeeTotalDeductions;
        $totalNetSalary += $netSalary;
        $totalEmployeeEpf += $employeeEpf;
        $totalEmployerEpf += $employerEpf;
        $totalEtf += $etf;
    }

    Database::iud("
        UPDATE `payroll_runs`
        SET
            `total_employees` = '$totalEmployees',
            `total_gross_salary` = '$totalGrossSalary',
            `total_deductions` = '$totalDeductions',
            `total_net_salary` = '$totalNetSalary',
            `total_employee_epf` = '$totalEmployeeEpf',
            `total_employer_epf` = '$totalEmployerEpf',
            `total_etf` = '$totalEtf'
        WHERE `payroll_run_id` = '$payrollRunId'
    ");

    Database::iud("
        INSERT INTO `payroll_run_logs`
        (
            `payroll_runs_payroll_run_id`,
          
            `payroll_status`,
            `log`,
            `note`,
            `date_updated`
        )
        VALUES
        (
            '$payrollRunId',
            
            'CALCULATED',
            'Monthly payroll run generated',
            'Payroll run generated automatically for " . date("F Y") . ".',
            NOW()
        )
    ");

    writeLog(
        "Payroll run generated successfully | payroll_run_id: " . $payrollRunId .
            " | month: " . $month .
            " | year: " . $year .
            " | total_employees: " . $totalEmployees .
            " | total_gross_salary: " . number_format($totalGrossSalary, 2, '.', '') .
            " | total_net_salary: " . number_format($totalNetSalary, 2, '.', '') .
            " | date_generated: " . date("Y-m-d H:i:s"),
        $logFile
    );
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
