<?php
error_reporting(0);
ini_set('display_errors', 0);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_once '../vendor/autoload.php';
require_once '../includes/connection.php';

/**
 * Generate Payroll Pay Sheet — matches payroll_components table exactly
 *
 * Component IDs → Excel columns:
 *  1  Other Allowance       → D
 *  2  Travelling Allowance  → E
 *  3  BR 1Allowance         → F
 *  4  BR 2 Allowance        → G
 *  5  leave payment         → H
 *  6  Arreears/ (No pay)    → I
 *  7  Allowance with EPF    → J
 *  8  SUB Total for EP F    → K  (also mapped from epf_salary)
 *  9  Non Cash Benefits     → L
 * 10  Other                 → M
 * 11  Salary advance        → P
 * 12  Other Deduction       → Q
 * 13  APIT                  → R  (also mapped from apit_amount)
 * 14  Mobile Usage          → S
 * 15  Payee Tax Adj.        → T
 * 16  Stamp Duty            → U
 */
function generatePayrollReport(int $payrollRunId, ?string $outputPath = null): void
{
    // ── 1. FETCH RUN ─────────────────────────────────────────────────────────
    $runResult = Database::search("SELECT * FROM `payroll_runs` WHERE `payroll_run_id`='" . $payrollRunId . "'");
    if ($runResult->num_rows === 0) {
        throw new \Exception("Payroll run not found: $payrollRunId");
    }
    $run = $runResult->fetch_assoc();

    $monthName = date('F', mktime(0, 0, 0, (int)$run['payroll_month'], 1));
    $year      = $run['payroll_year'];

    // ── 2. FETCH EMPLOYEES ───────────────────────────────────────────────────
    $empResult = Database::search("
        SELECT pre.*,
               CONCAT(u.`user_fname`,' ',u.`user_lname`) AS employee_name
        FROM   `payroll_run_employees` pre
        INNER JOIN `users` u ON pre.`users_user_id` = u.`user_id`
        WHERE  pre.`payroll_runs_payroll_run_id` = '" . $payrollRunId . "'
        ORDER  BY u.`user_fname` ASC
    ");
    $employees = [];
    while ($row = $empResult->fetch_assoc()) $employees[] = $row;

    // ── 3. FETCH COMPONENTS (keyed by component_id for exact matching) ───────
    // $compMap[payroll_run_employee_id][payroll_component_id] = amount
    $compMap = [];
    foreach ($employees as $emp) {
        $eid = $emp['payroll_run_employee_id'];
        $compMap[$eid] = [];
        $res = Database::search("
            SELECT prec.`payroll_components_payroll_component_id` AS cid,
                   prec.`amount`
            FROM   `payroll_run_employee_components` prec
            WHERE  prec.`payroll_run_employees_payroll_run_employee_id` = '" . $eid . "'
        ");
        while ($c = $res->fetch_assoc()) {
            $compMap[$eid][(int)$c['cid']] = (float)$c['amount'];
        }
    }

    // Helper: get component amount by ID (trim handles trailing spaces in DB)
    $get = fn(array $map, int $id) => $map[$id] ?? 0.00;

    // ── 4. SPREADSHEET SETUP ─────────────────────────────────────────────────
    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Pay Sheet');

    // Colours
    $C_NAVY    = 'FF1F3864';
    $C_WHITE   = 'FFFFFFFF';
    $C_LBLUE   = 'FFD6DCE4';
    $C_GREEN   = 'FFE2EFDA';
    $C_PINK    = 'FFFCE4EC';
    $C_YELLOW  = 'FFFFFF00';
    $C_GREY    = 'FFF2F2F2';
    $C_ALTROW  = 'FFEAF4FB';
    $C_BORDER  = 'FF9E9E9E';
    $C_SUMMARY = 'FFEDEDED';

    $FMT = '#,##0.00';

    // ── COLUMN DEFINITIONS ───────────────────────────────────────────────────
    // Each entry: [header label, width, group]
    // Groups: 'info' | 'earning' | 'deduction' | 'statutory'
    $cols = [
        'A' => ['#',                        5,   'info'],
        'B' => ["Employee\nName",           26,   'info'],
        'C' => ["Basic\nSalary",            13,   'earning'],
        'D' => ["Other\nAllowance",         13,   'earning'],   // cid 1
        'E' => ["Travelling\nAllowance",    14,   'earning'],   // cid 2
        'F' => ["BR 1\nAllowance",          13,   'earning'],   // cid 3
        'G' => ["BR 2\nAllowance",          13,   'earning'],   // cid 4
        'H' => ["Leave\nPayment",           13,   'earning'],   // cid 5
        'I' => ["Arrears/\n(No Pay)",       13,   'earning'],   // cid 6
        'J' => ["Allowance\nwith EPF",      14,   'earning'],   // cid 7
        'K' => ["SUB Total\nfor EPF",       14,   'earning'],   // cid 8 / epf_salary
        'L' => ["Non Cash\nBenefits",       13,   'earning'],   // cid 9
        'M' => ["Other",                    11,   'earning'],   // cid 10
        'N' => ["Gross\nSalary",            14,   'earning'],
        'O' => ["EPF\nEmployee\n8%",        12,   'deduction'],
        'P' => ["Salary\nAdvance",          12,   'deduction'], // cid 11
        'Q' => ["Other\nDeduction",         13,   'deduction'], // cid 12
        'R' => ["APIT",                     12,   'deduction'], // cid 13 / apit_amount
        'S' => ["Mobile\nUsage",            11,   'deduction'], // cid 14
        'T' => ["Payee Tax\nAdj.",          11,   'deduction'], // cid 15
        'U' => ["Stamp\nDuty",             10,   'deduction'], // cid 16
        'V' => ["Total\nDeduction",         13,   'deduction'],
        'W' => ["Net\nSalary",              13,   'statutory'],
        'X' => ["EPF\nEmployer\n12%",       13,   'statutory'],
        'Y' => ["EPF\n20%\n(Total)",        12,   'statutory'],
        'Z' => ["ETF\n3%",                  10,   'statutory'],
    ];

    $lastCol = 'Z';

    foreach ($cols as $col => [$label, $width, $group]) {
        $sheet->getColumnDimension($col)->setWidth($width);
    }

    // ── ROW PLAN ─────────────────────────────────────────────────────────────
    $R_COMPANY    = 1;
    $R_TITLE      = 2;
    $R_GRP_HDR    = 3;
    $R_COL_HDR    = 4;
    $R_DATA_START = 5;
    $empCount     = count($employees);
    $R_DATA_END   = $R_DATA_START + max($empCount - 1, 0);
    $R_TOTAL      = $R_DATA_END + 1;
    $R_BLANK      = $R_TOTAL + 1;
    // Summary block
    $R_SUM_HDR    = $R_BLANK + 1;
    $R_SUM_EMP    = $R_SUM_HDR + 1;
    $R_SUM_GROSS  = $R_SUM_HDR + 2;
    $R_SUM_DEDUCT = $R_SUM_HDR + 3;
    $R_SUM_NET    = $R_SUM_HDR + 4;
    $R_SUM_EEPF   = $R_SUM_HDR + 5;
    $R_SUM_ERЕПF  = $R_SUM_HDR + 6;
    $R_SUM_EPF20  = $R_SUM_HDR + 7;
    $R_SUM_ETF    = $R_SUM_HDR + 8;
    $R_SUM_APIT   = $R_SUM_HDR + 9;
    // Tax table  (right side, same rows as summary)
    $R_TAX_HDR    = $R_SUM_HDR;
    $R_TAX_APIT   = $R_TAX_HDR + 1;
    $R_TAX_EPF    = $R_TAX_HDR + 2;
    $R_TAX_ETF    = $R_TAX_HDR + 3;
    // Sign-off
    $R_SIGN       = $R_SUM_APIT + 2;
    $R_AUTH       = $R_SIGN + 2;

    // ── ROW HEIGHTS ──────────────────────────────────────────────────────────
    $sheet->getRowDimension($R_COMPANY)->setRowHeight(24);
    $sheet->getRowDimension($R_TITLE)->setRowHeight(18);
    $sheet->getRowDimension($R_GRP_HDR)->setRowHeight(16);
    $sheet->getRowDimension($R_COL_HDR)->setRowHeight(52);

    // ── ROW 1: Company ───────────────────────────────────────────────────────
    $sheet->mergeCells("A{$R_COMPANY}:{$lastCol}{$R_COMPANY}");
    $sheet->setCellValue("A{$R_COMPANY}", 'Orinsto Campus');
    $sheet->getStyle("A{$R_COMPANY}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 16, 'color' => ['argb' => $C_WHITE], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_NAVY]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // ── ROW 2: Title ─────────────────────────────────────────────────────────
    $sheet->mergeCells("A{$R_TITLE}:{$lastCol}{$R_TITLE}");
    $sheet->setCellValue("A{$R_TITLE}", "Pay Sheet — Calculation for the Month of {$monthName} {$year}");
    $sheet->getStyle("A{$R_TITLE}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 11, 'color' => ['argb' => $C_WHITE], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_NAVY]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // ── ROW 3: Group headers ─────────────────────────────────────────────────
    $groups = [
        ['A', 'B',  'INFO',        $C_LBLUE, 'FF000000'],
        ['C', 'N',  'EARNINGS',    'FF375623', $C_WHITE],
        ['O', 'V',  'DEDUCTIONS',  'FF7B2D00', $C_WHITE],
        ['W', 'Z',  'STATUTORY',   'FF1F3864', $C_WHITE],
    ];
    foreach ($groups as [$from, $to, $label, $bg, $fg]) {
        $sheet->mergeCells("{$from}{$R_GRP_HDR}:{$to}{$R_GRP_HDR}");
        $sheet->setCellValue("{$from}{$R_GRP_HDR}", $label);
        $sheet->getStyle("{$from}{$R_GRP_HDR}:{$to}{$R_GRP_HDR}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'color' => ['argb' => $fg], 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFFFFFFF']]],
        ]);
    }

    // ── ROW 4: Column headers ─────────────────────────────────────────────────
    $groupBg = [
        'info'       => $C_LBLUE,
        'earning'    => $C_GREEN,
        'deduction'  => $C_PINK,
        'statutory'  => $C_LBLUE,
    ];
    foreach ($cols as $col => [$label, $width, $group]) {
        $cell = $col . $R_COL_HDR;
        $sheet->setCellValue($cell, $label);
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['bold' => true, 'size' => 8, 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $groupBg[$group]]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $C_BORDER]]],
        ]);
    }

    // ── EMPLOYEE DATA ROWS ────────────────────────────────────────────────────
    foreach ($employees as $i => $emp) {
        $row  = $R_DATA_START + $i;
        $eid  = $emp['payroll_run_employee_id'];
        $cmap = $compMap[$eid] ?? [];

        $sheet->setCellValue("A{$row}", $i + 1);
        $sheet->setCellValue("B{$row}", $emp['employee_name']);
        $sheet->setCellValue("C{$row}", (float)$emp['basic_salary']);
        $sheet->setCellValue("D{$row}", $get($cmap, 1));   // Other Allowance
        $sheet->setCellValue("E{$row}", $get($cmap, 2));   // Travelling Allowance
        $sheet->setCellValue("F{$row}", $get($cmap, 3));   // BR 1Allowance
        $sheet->setCellValue("G{$row}", $get($cmap, 4));   // BR 2 Allowance
        $sheet->setCellValue("H{$row}", $get($cmap, 5));   // leave payment
        $sheet->setCellValue("I{$row}", $get($cmap, 6));   // Arreears/ (No pay)
        $sheet->setCellValue("J{$row}", $get($cmap, 7));   // Allowance with EPF
        // K: prefer component 8, fall back to epf_salary from payroll_run_employees
        $sheet->setCellValue("K{$row}", $get($cmap, 8) ?: (float)$emp['epf_salary']);
        $sheet->setCellValue("L{$row}", $get($cmap, 9));   // Non Cash Benefits
        $sheet->setCellValue("M{$row}", $get($cmap, 10));  // Other
        $sheet->setCellValue("N{$row}", (float)$emp['gross_salary']);
        $sheet->setCellValue("O{$row}", (float)$emp['employee_epf']);
        $sheet->setCellValue("P{$row}", $get($cmap, 11));  // Salary advance
        $sheet->setCellValue("Q{$row}", $get($cmap, 12));  // Other Deduction
        // R: prefer component 13, fall back to apit_amount
        $sheet->setCellValue("R{$row}", $get($cmap, 13) ?: (float)$emp['apit_amount']);
        $sheet->setCellValue("S{$row}", $get($cmap, 14));  // Mobile Usage
        $sheet->setCellValue("T{$row}", $get($cmap, 15));  // Payee Tax Adj.
        $sheet->setCellValue("U{$row}", $get($cmap, 16));  // Stamp Duty
        $sheet->setCellValue("V{$row}", (float)$emp['total_deductions']);
        $sheet->setCellValue("W{$row}", (float)$emp['net_salary']);
        $sheet->setCellValue("X{$row}", (float)$emp['employer_epf']);
        $sheet->setCellValue("Y{$row}", (float)($emp['employee_epf'] + $emp['employer_epf']));
        $sheet->setCellValue("Z{$row}", (float)$emp['etf']);

        $bg = ($i % 2 === 0) ? $C_WHITE : $C_ALTROW;
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
            'font'      => ['size' => 9, 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $C_BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        foreach (range('C', 'Z') as $c) {
            $sheet->getStyle("{$c}{$row}")->getNumberFormat()->setFormatCode($FMT);
            $sheet->getStyle("{$c}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getRowDimension($row)->setRowHeight(16);
    }

    // ── TOTAL ROW ─────────────────────────────────────────────────────────────
    $sheet->mergeCells("A{$R_TOTAL}:B{$R_TOTAL}");
    $sheet->setCellValue("A{$R_TOTAL}", 'TOTAL');
    $numCols = range('C', 'Z');
    foreach ($numCols as $c) {
        $sheet->setCellValue("{$c}{$R_TOTAL}", "=SUM({$c}{$R_DATA_START}:{$c}{$R_DATA_END})");
        $sheet->getStyle("{$c}{$R_TOTAL}")->getNumberFormat()->setFormatCode($FMT);
    }
    $sheet->getStyle("A{$R_TOTAL}:{$lastCol}{$R_TOTAL}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_YELLOW]],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => $C_BORDER]]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getStyle("A{$R_TOTAL}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($R_TOTAL)->setRowHeight(18);

    // ── SUMMARY BLOCK (left side) ─────────────────────────────────────────────
    // Header
    $sheet->mergeCells("A{$R_SUM_HDR}:G{$R_SUM_HDR}");
    $sheet->setCellValue("A{$R_SUM_HDR}", 'PAYROLL SUMMARY');
    $sheet->getStyle("A{$R_SUM_HDR}:G{$R_SUM_HDR}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $C_WHITE], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_NAVY]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
    $sheet->getRowDimension($R_SUM_HDR)->setRowHeight(18);

    $summaryRows = [
        $R_SUM_EMP    => ['Total Employees',          "=COUNTA(B{$R_DATA_START}:B{$R_DATA_END})", '#,##0'],
        $R_SUM_GROSS  => ['Total Gross Salary',        "=N{$R_TOTAL}",  $FMT],
        $R_SUM_DEDUCT => ['Total Deductions',          "=V{$R_TOTAL}",  $FMT],
        $R_SUM_NET    => ['Total Net Salary',           "=W{$R_TOTAL}",  $FMT],
        $R_SUM_EEPF   => ['Total EPF (Employee 8%)',   "=O{$R_TOTAL}",  $FMT],
        $R_SUM_ERЕПF  => ['Total EPF (Employer 12%)',  "=X{$R_TOTAL}",  $FMT],
        $R_SUM_EPF20  => ['Total EPF (20%)',            "=Y{$R_TOTAL}",  $FMT],
        $R_SUM_ETF    => ['Total ETF (3%)',             "=Z{$R_TOTAL}",  $FMT],
        $R_SUM_APIT   => ['Total APIT',                "=R{$R_TOTAL}",  $FMT],
    ];

    $altSummary = false;
    foreach ($summaryRows as $r => [$label, $formula, $fmt]) {
        $bg = $altSummary ? $C_GREY : $C_WHITE;
        $altSummary = !$altSummary;

        $sheet->mergeCells("A{$r}:D{$r}");
        $sheet->setCellValue("A{$r}", $label);
        $sheet->mergeCells("E{$r}:G{$r}");
        $sheet->setCellValue("E{$r}", $formula);
        $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($fmt);

        $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
            'font'    => ['size' => 9, 'name' => 'Arial'],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $C_BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("A{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);
        $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Highlight Net Salary row
        if ($r === $R_SUM_NET) {
            $sheet->getStyle("A{$r}:G{$r}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => $C_WHITE]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '334472C4']],
            ]);
        }
        $sheet->getRowDimension($r)->setRowHeight(16);
    }

    // ── TAX PAYMENTS TABLE (right side, cols R–Z) ─────────────────────────────
    $sheet->mergeCells("R{$R_TAX_HDR}:Z{$R_TAX_HDR}");
    $sheet->setCellValue("R{$R_TAX_HDR}", 'TAX PAYMENTS SUMMARY');
    $sheet->getStyle("R{$R_TAX_HDR}:Z{$R_TAX_HDR}")->applyFromArray([
        'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => $C_WHITE], 'name' => 'Arial'],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_NAVY]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);

    // Sub-header row
    $taxHdrRow = $R_TAX_HDR;
    // Re-use the same header row for labels
    $taxDataRows = [
        $R_TAX_APIT => ['APIT',  "=R{$R_TOTAL}"],
        $R_TAX_EPF  => ['EPF',   "=Y{$R_TOTAL}"],
        $R_TAX_ETF  => ['ETF',   "=Z{$R_TOTAL}"],
    ];

    // Column labels for tax table
    $taxCols = ['R' => 'Tax Type', 'T' => 'Amount (LKR)', 'V' => 'Cheque No', 'X' => 'Date Due'];
    foreach ($taxCols as $c => $hdr) {
        $endC = chr(ord($c) + 1);
        $sheet->mergeCells("{$c}" . ($R_TAX_HDR + 1) . ":{$endC}" . ($R_TAX_HDR + 1));
        $sheet->setCellValue("{$c}" . ($R_TAX_HDR + 1), $hdr);
        $sheet->getStyle("{$c}" . ($R_TAX_HDR + 1) . ":{$endC}" . ($R_TAX_HDR + 1))->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_LBLUE]],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $C_BORDER]]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
    }

    // Tax data rows (APIT, EPF, ETF)
    $taxOffset = 2; // rows below TAX header
    foreach ([
        [$R_TAX_HDR + 2, 'APIT',  "=R{$R_TOTAL}"],
        [$R_TAX_HDR + 3, 'EPF',   "=Y{$R_TOTAL}"],
        [$R_TAX_HDR + 4, 'ETF',   "=Z{$R_TOTAL}"],
    ] as [$r, $type, $formula]) {
        $sheet->mergeCells("R{$r}:S{$r}");
        $sheet->setCellValue("R{$r}", $type);
        $sheet->mergeCells("T{$r}:U{$r}");
        $sheet->setCellValue("T{$r}", $formula);
        $sheet->getStyle("T{$r}")->getNumberFormat()->setFormatCode($FMT);
        $sheet->mergeCells("V{$r}:W{$r}");  // Cheque No (blank — filled manually)
        $sheet->mergeCells("X{$r}:Z{$r}");  // Date Due  (blank — filled manually)

        $sheet->getStyle("R{$r}:Z{$r}")->applyFromArray([
            'font'    => ['size' => 9, 'name' => 'Arial'],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_WHITE]],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $C_BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("R{$r}")->getFont()->setBold(true);
        $sheet->getStyle("T{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getRowDimension($r)->setRowHeight(16);
    }

    // ── SIGN-OFF ROWS ─────────────────────────────────────────────────────────
    foreach ([
        [$R_SIGN,  'A', 'F', 'Prepared By / Date: ___________________________'],
        [$R_SIGN,  'L', 'R', 'Checked By / Date: ____________________________'],
        [$R_AUTH,  'A', 'F', 'Authorised By / Date: _________________________'],
    ] as [$r, $from, $to, $text]) {
        $sheet->mergeCells("{$from}{$r}:{$to}{$r}");
        $sheet->setCellValue("{$from}{$r}", $text);
        $sheet->getStyle("{$from}{$r}:{$to}{$r}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 9, 'name' => 'Arial'],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $C_GREY]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $C_BORDER]]],
        ]);
        $sheet->getRowDimension($r)->setRowHeight(20);
    }

    // ── FREEZE PANES & PAGE SETUP ─────────────────────────────────────────────
    $sheet->freezePane("C{$R_DATA_START}");
    $sheet->getPageSetup()
        ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3)
        ->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.4)->setRight(0.4);
    $sheet->getHeaderFooter()
        ->setOddHeader("&C&B Orinsto Campus — Payroll Pay Sheet — {$monthName} {$year}");
    $sheet->getHeaderFooter()
        ->setOddFooter('&LGenerated: ' . date('Y-m-d H:i') . '&RPage &P of &N');

    // ── OUTPUT ────────────────────────────────────────────────────────────────
    $writer = new Xlsx($spreadsheet);
    if ($outputPath !== null) {
        $writer->save($outputPath);
    } else {
        $filename = "Payroll_PaySheet_{$monthName}_{$year}.xlsx";
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
        exit;
    }
}

// ── ENTRY POINT ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['payroll-run-id'])) {
    $status = new stdClass();
    require_once '../includes/UserClass.php';
    require_once '../includes/UserPermissionsClass.php';

    if (!SessionUser::hasValidSession()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication Failed']);
        exit;
    }

    $runId = (int)$_GET['payroll-run-id'];
    if ($runId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payroll run ID']);
        exit;
    }

    try {
        generatePayrollReport($runId);
    } catch (\Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}