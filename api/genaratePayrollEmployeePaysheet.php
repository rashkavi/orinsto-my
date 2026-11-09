<?php

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");

require "../vendor/autoload.php";

if (!SessionUser::hasValidSession()) {
    die("Unauthorized access");
}

if (!isset($_GET["payroll-run-id"]) || empty($_GET["payroll-run-id"])) {
    die("No payroll employees selected");
}

$ids = $_GET["payroll-run-id"];

$employees = Database::search("
    SELECT 
        pre.*,
        pr.payroll_month,
        pr.payroll_year,
        pr.payroll_period_start,
        pr.payroll_period_end,

        u.user_fname,
        u.user_lname,
        u.user_email,
        u.nic AS nic_no,
        u.epf_no,

        t.title

    FROM payroll_run_employees pre

    INNER JOIN payroll_runs pr 
        ON pre.payroll_runs_payroll_run_id = pr.payroll_run_id

    INNER JOIN users u 
        ON pre.users_user_id = u.user_id

    INNER JOIN title t 
        ON u.title_title_id = t.title_id

    WHERE pre.payroll_run_employee_id = '" . $ids . "'
");

class PaysheetPDF extends FPDF
{
    function Header() {}

    function Footer()
    {
        $this->SetY(-12);

        $this->SetFont("Arial", "I", 8);
        $this->SetTextColor(120, 120, 120);

        $this->Cell(
            0,
            5,
            "Computer Generated Payslip",
            0,
            0,
            "C"
        );
    }

    function SectionTitle($title)
    {
        $this->SetFillColor(8, 28, 71, 0.5);
        $this->SetTextColor(255, 255, 255);

        $this->SetFont("Arial", "B", 10);

        $this->Cell(
            180,
            8,
            "  " . $title,
            0,
            1,
            "L",
            true
        );

        $this->SetTextColor(0, 0, 0);
    }

    function TableHeader()
    {
        $this->SetFillColor(235, 235, 235);

        $this->SetFont("Arial", "B", 9);

        $this->Cell(120, 8, "Description", 1, 0, "L", true);
        $this->Cell(20, 8, "Currency", 1, 0, "C", true);
        $this->Cell(40, 8, "Amount", 1, 1, "R", true);
    }

    function TableRow($description, $amount)
    {
        $this->SetFont("Arial", "", 9);

        $this->Cell(
            120,
            8,
            "  " . $description,
            1,
            0,
            "L"
        );

        $this->Cell(
            20,
            8,
            "LKR",
            1,
            0,
            "C"
        );

        $this->Cell(
            40,
            8,
            number_format((float)$amount, 2),
            1,
            1,
            "R"
        );
    }

    function SummaryRow($title, $amount, $fill = false)
    {
        if ($fill) {
            $this->SetFillColor(245, 245, 245);
        }

        $this->SetFont("Arial", "B", 9);

        $this->Cell(
            140,
            9,
            $title,
            1,
            0,
            "R",
            $fill
        );

        $this->Cell(
            40,
            9,
            number_format((float)$amount, 2),
            1,
            1,
            "R",
            $fill
        );
    }
}

$pdf = new PaysheetPDF("P", "mm", "A4");

$pdf->SetMargins(15, 15, 15);

$pdf->SetAutoPageBreak(true, 15);

while ($employee = $employees->fetch_assoc()) {

    $monthName = date(
        "F Y",
        mktime(
            0,
            0,
            0,
            $employee["payroll_month"],
            1,
            $employee["payroll_year"]
        )
    );

    $employeeName =
        $employee["title"] .
        ". " .
        $employee["user_fname"] .
        " " .
        $employee["user_lname"];

    $components = Database::search("
        SELECT *
        FROM payroll_run_employee_components
        INNER JOIN payroll_components 
            ON payroll_run_employee_components.payroll_components_component_id = payroll_components.component_id
        WHERE payroll_run_employees_payroll_run_employee_id = '" . $employee['payroll_run_employee_id'] . "'
        ORDER BY component_type ASC
    ");

    $earnings = [];
    $deductions = [];

    while ($component = $components->fetch_assoc()) {

        if ($component["component_type"] == "EARNING") {
            $earnings[] = $component;
        } else {
            $deductions[] = $component;
        }
    }

    $pdf->AddPage();

    /*
    |--------------------------------------------------------------------------
    | COMPANY HEADER
    |--------------------------------------------------------------------------
    */

    $pdf->SetFillColor(8, 28, 71, 0.5);

    $pdf->Rect(15, 15, 180, 24, "F");

    if (file_exists("../assets/imgs/common/logo.png")) {

        $pdf->Image(
            "../assets/imgs/common/logo.png",
            18,
            19,
            20,
            16
        );
    }

    $pdf->SetXY(40, 18);

    $pdf->SetTextColor(255, 255, 255);

    $pdf->SetFont("Arial", "B", 15);

    $pdf->Cell(100, 6, "SWLC SRI LANKA", 0, 1);

    $pdf->SetX(40);

    $pdf->SetFont("Arial", "", 9);

    $pdf->Cell(
        100,
        5,
        "Employee Payroll Payslip",
        0,
        1
    );

    $pdf->SetX(40);

    $pdf->Cell(
        100,
        5,
        "Month : " . $monthName,
        0,
        1
    );

    $pdf->SetXY(140, 19);

    $pdf->SetFont("Arial", "", 8);

    $pdf->MultiCell(
        50,
        4,
        "No.282/1,\nHigh Level Road,\nNugegoda,\nSri Lanka",
        0,
        "R"
    );

    $pdf->SetTextColor(0, 0, 0);

    $pdf->Ln(8);

    /*
    |--------------------------------------------------------------------------
    | EMPLOYEE INFORMATION
    |--------------------------------------------------------------------------
    */

    $pdf->SectionTitle("Employee Information");

    $pdf->SetFont("Arial", "", 9);

    $pdf->Cell(45, 8, "Employee Name", 1);
    $pdf->Cell(45, 8, $employeeName, 1);

    $pdf->Cell(45, 8, "EPF Number", 1);
    $pdf->Cell(45, 8, $employee["epf_no"], 1, 1);

    $pdf->Cell(45, 8, "NIC Number", 1);
    $pdf->Cell(45, 8, $employee["nic_no"], 1);

    $pdf->Cell(45, 8, "Payroll Month", 1);
    $pdf->Cell(45, 8, $monthName, 1, 1);

    $pdf->Cell(45, 8, "Period Start", 1);
    $pdf->Cell(45, 8, $employee["payroll_period_start"], 1);

    $pdf->Cell(45, 8, "Period End", 1);
    $pdf->Cell(45, 8, $employee["payroll_period_end"], 1, 1);

    $pdf->Ln(6);

    /*
    |--------------------------------------------------------------------------
    | EARNINGS
    |--------------------------------------------------------------------------
    */

    $pdf->SectionTitle("Earnings");

    $pdf->TableHeader();

    foreach ($earnings as $earn) {

        $pdf->TableRow(
            $earn["payroll_component"],
            $earn["amount"]
        );
    }

    $pdf->SummaryRow(
        "Gross Salary",
        $employee["gross_salary"],
        true
    );

    $pdf->Ln(6);

    /*
    |--------------------------------------------------------------------------
    | DEDUCTIONS
    |--------------------------------------------------------------------------
    */

    $pdf->SectionTitle("Deductions");

    $pdf->TableHeader();

    foreach ($deductions as $deduct) {

        $pdf->TableRow(
            $deduct["payroll_component"],
            $deduct["amount"]
        );
    }

    $pdf->SummaryRow(
        "Total Deductions",
        $employee["total_deductions"],
        true
    );

    $pdf->SummaryRow(
        "Net Salary",
        $employee["net_salary"]
    );

    $pdf->Ln(6);

    /*
    |--------------------------------------------------------------------------
    | EMPLOYER CONTRIBUTIONS
    |--------------------------------------------------------------------------
    */

    $pdf->SectionTitle("Employer Contributions");

    $pdf->TableHeader();

    $epfEmployer =
        (float)$employee["gross_salary"] * 0.12;

    $etfEmployer =
        (float)$employee["gross_salary"] * 0.03;

    $pdf->TableRow(
        "EPF Employer Contribution (12%)",
        $epfEmployer
    );

    $pdf->TableRow(
        "ETF Employer Contribution (3%)",
        $etfEmployer
    );

    $pdf->Ln(10);

    /*
    |--------------------------------------------------------------------------
    | ACKNOWLEDGEMENT
    |--------------------------------------------------------------------------
    */

    $pdf->SectionTitle("Payslip Acknowledgement");

    $pdf->SetFont("Arial", "", 9);

    $pdf->Cell(45, 8, "Employee", 1);
    $pdf->Cell(135, 8, $employeeName, 1, 1);

    $pdf->Cell(45, 8, "Payroll Month", 1);
    $pdf->Cell(135, 8, $monthName, 1, 1);

    $pdf->Cell(45, 8, "EPF Number", 1);
    $pdf->Cell(135, 8, $employee["epf_no"], 1, 1);

    $pdf->Cell(45, 14, "Signature", 1);
    $pdf->Cell(135, 14, "", 1, 1);

    $pdf->Cell(45, 10, "Date", 1);
    $pdf->Cell(135, 10, "", 1, 1);
}

$fileName =
    "Employee_Paysheets_" .
    date("Ymd_His") .
    ".pdf";

$pdf->Output("I", $fileName);

exit;
