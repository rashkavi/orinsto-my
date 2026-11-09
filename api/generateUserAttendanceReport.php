<?php
/**
 * api/generateAttendanceReport.php
 *
 * Generates a staff/user attendance report as .xlsx for a given date range.
 * Query params: from_date (YYYY-MM-DD), to_date (YYYY-MM-DD)
 *
 * Attendance record key:
 *   1          = Present (full day)
 *   0          = Absent (no pay)
 *   AL         = Annual Leave
 *   SL         = Sick Leave
 *   CL         = Casual Leave
 *   0.5CL/0.5AL= Half day leave
 *   LL         = Late attendance
 *   offday     = Off Day / Weekend
 *   Off Day    = Off Day / Holiday
 *   (empty)    = No record
 *
 * Database tables used:
 *   users                 — user_id, user_fname, user_lname, title_title_id
 *   title                 — title_id, title
 *   attendance            — attendance_id, users_user_id, check_in_time,
 *                           check_out_time, date, attendance_type_id (optional)
 *   attendance_types      — (optional) type_id, type_name
 *
 * NOTE: Adjust the SQL queries to match your exact table/column names.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');

/* ── Auth ─────────────────────────────────────────────────────────────────── */
if (!SessionUser::hasValidSession()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

/* ── Input validation ────────────────────────────────────────────────────── */
$fromDate = $_GET['from_date'] ?? '';
$toDate   = $_GET['to_date']   ?? '';

if (!$fromDate || !$toDate) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Please provide from_date and to_date']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid date format. Use YYYY-MM-DD']);
    exit;
}

if ($fromDate > $toDate) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'From date cannot be greater than To date']);
    exit;
}

/* ── Vendor autoload (PhpSpreadsheet) ────────────────────────────────────── */
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server dependency missing — run: composer require phpoffice/phpspreadsheet']);
    exit;
}
require_once $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Color;

/* ════════════════════════════════════════════════════════════════════════════
   1. FETCH DATA
════════════════════════════════════════════════════════════════════════════ */

/* ── All active users ── */
$usersResult = Database::search("
    SELECT
        u.user_id,
        u.user_fname,
        u.user_lname,
        u.user_email as email,
        t.title
    FROM users u
    INNER JOIN title t ON u.title_title_id = t.title_id
    WHERE u.status_status_id = 1
    ORDER BY u.user_fname, u.user_lname
");

if (!$usersResult || $usersResult->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No active users found']);
    exit;
}

$users = [];
while ($u = $usersResult->fetch_assoc()) {
    $users[] = $u;
}

/* ── Attendance records for the date range ── */
/*
 * Expected columns from your attendance table:
 *   users_user_id   — foreign key to users
 *   check_in_time   — DATETIME or TIME
 *   check_out_time  — DATETIME or TIME (nullable)
 *   date            — DATE  (the working date)
 *   status          — VARCHAR: 'present','absent','AL','SL','CL','LL','offday', etc.
 *                     (adjust to match your column name/values)
 *
 * If your table stores check_in_time as a DATETIME, extract DATE part with DATE()
 */
$safeFrom = addslashes($fromDate);
$safeTo   = addslashes($toDate);

$attResult = Database::search("
    SELECT
        a.users_user_id,
        DATE(a.check_in)  AS att_date,
        TIME(a.check_in)  AS check_in,
        TIME(a.check_out) AS check_out,
        COALESCE(a.attendance_status, 'present') AS att_status
    FROM user_attendance a
    WHERE DATE(a.check_in) BETWEEN '$safeFrom' AND '$safeTo'
    ORDER BY a.users_user_id, att_date ASC
");

/* Index attendance by user_id → date */
$attMap = [];
if ($attResult && $attResult->num_rows > 0) {
    while ($a = $attResult->fetch_assoc()) {
        $attMap[$a['users_user_id']][$a['att_date']] = $a;
    }
}

/* ── Build date range array ── */
$dates = [];
$cur   = new DateTime($fromDate);
$end   = new DateTime($toDate);
while ($cur <= $end) {
    $dates[] = $cur->format('Y-m-d');
    $cur->modify('+1 day');
}

/* ════════════════════════════════════════════════════════════════════════════
   2. STATUS CLASSIFIER
   Returns: [display_value, fill_color_hex, text_color_hex]
════════════════════════════════════════════════════════════════════════════ */
function classifyStatus(string $raw): array
{
    $val = strtolower(trim($raw));

    /* Off day / weekend */
    if (in_array($val, ['offday', 'off day', 'off', 'weekend', 'holiday'])) {
        return ['Off Day', 'D9D9D9', '595959'];   // grey
    }
    /* Absent / no pay */
    if ($val === '0' || $val === 'absent' || $val === 'nopay' || $val === 'no pay') {
        return ['Absent', 'FFE0E0', 'C00000'];    // red
    }
    /* Annual Leave */
    if (str_contains($val, 'al')) {
        return [strtoupper($raw), 'FFC000', '7B4F00'];  // amber
    }
    /* Sick Leave */
    if (str_contains($val, 'sl')) {
        return [strtoupper($raw), 'FFC000', '7B4F00'];  // amber
    }
    /* Casual Leave */
    if (str_contains($val, 'cl')) {
        return [strtoupper($raw), '92D050', '375623'];  // green
    }
    /* Late attendance */
    if (str_contains($val, 'll') || str_contains($val, 'late')) {
        return ['LL', 'E2EFDA', '375623'];               // light green
    }
    /* Present */
    if ($val === '1' || $val === 'present') {
        return ['P', 'FFFFFF', '111827'];
    }
    /* Empty */
    if ($val === '' || $val === '-') {
        return ['—', 'F9FAFB', '9CA3AF'];
    }

    return [strtoupper($raw), 'FFFFFF', '374151'];
}

/* ════════════════════════════════════════════════════════════════════════════
   3. BUILD SPREADSHEET
════════════════════════════════════════════════════════════════════════════ */
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Attendance Report');

/* ── Helpers ── */
$FONT_NAME = 'Arial';

function applyHeaderStyle($sheet, string $range, string $bgHex, string $fgHex = 'FFFFFF', bool $bold = true, int $size = 9): void
{
    global $FONT_NAME;
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'bold' => $bold, 'size' => $size, 'color' => ['rgb' => $fgHex]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgHex]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
    ]);
}

function applyDataStyle($sheet, string $range, string $bgHex, string $fgHex = '111827', int $size = 8): void
{
    global $FONT_NAME;
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'size' => $size, 'color' => ['rgb' => $fgHex]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgHex]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);
}

function setCellValueByColumnAndRow($sheet, int $column, int $row, $value): void
{
    $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column) . $row;
    $sheet->setCellValue($cell, $value);
}

$totalCols  = count($dates) + 7;   // #, Name, ...dates..., Present, Absent, Leave, NoPay, Total
$lastColLtr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalCols);

/* ── Row 1: Report Title ── */
$sheet->mergeCells("A1:{$lastColLtr}1");
$title = 'STAFF ATTENDANCE REPORT  |  ' .
    date('d M Y', strtotime($fromDate)) . '  —  ' . date('d M Y', strtotime($toDate));
$sheet->setCellValue('A1', $title);
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['name' => $FONT_NAME, 'bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(28);

/* ── Row 2: Generated by / date line ── */
$sheet->mergeCells("A2:{$lastColLtr}2");
$genUser = SessionUser::getCurrentUserObject();
$genName = ($genUser['user_fname'] ?? '') . ' ' . ($genUser['user_lname'] ?? '');
$sheet->setCellValue('A2', 'Generated by: ' . trim($genName) . '   |   Generated on: ' . date('d M Y H:i'));
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['name' => $FONT_NAME, 'size' => 8, 'color' => ['rgb' => '6B7280'], 'italic' => true],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F8FAFC']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
    'borders'   => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '1E3A5F']]],
]);
$sheet->getRowDimension(2)->setRowHeight(16);

/* ── Row 3: Column Headers ── */
$headerRow = 3;
$colIdx    = 1;

/* Fixed headers */
setCellValueByColumnAndRow($sheet, $colIdx++, $headerRow, '#');
setCellValueByColumnAndRow($sheet, $colIdx++, $headerRow, 'Staff Name');

/* Date headers */
$dateColStart = $colIdx;
foreach ($dates as $date) {
    $dt      = new DateTime($date);
    $dayName = $dt->format('D');  // Mon, Tue…
    $dayNum  = $dt->format('d');
    $month   = $dt->format('M');
    $isWeekend = in_array($dt->format('N'), ['6', '7']);

    setCellValueByColumnAndRow($sheet, $colIdx, $headerRow, $dayName . "\n" . $dayNum . "\n" . $month);

    $colLtr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
    $bgColor = $isWeekend ? 'CBD5E1' : '1E3A5F';
    $fgColor = $isWeekend ? '374151' : 'FFFFFF';

    $sheet->getStyle("{$colLtr}{$headerRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'bold' => true, 'size' => 8, 'color' => ['rgb' => $fgColor]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgColor]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
    ]);
    $sheet->getColumnDimensionByColumn($colIdx)->setWidth(5.5);
    $colIdx++;
}

/* Summary headers */
$summaryHeaders = ['Present', 'Absent', 'Leave', 'Late', 'No Pay'];
$summaryCols    = [];
foreach ($summaryHeaders as $sh) {
    $summaryCols[$sh] = $colIdx;
    $colLtr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx);
    setCellValueByColumnAndRow($sheet, $colIdx, $headerRow, $sh);
    $sheet->getStyle("{$colLtr}{$headerRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '374151']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
    ]);
    $sheet->getColumnDimensionByColumn($colIdx)->setWidth(7);
    $colIdx++;
}

/* Style #, Name headers */
applyHeaderStyle($sheet, 'A3', '1E3A5F', 'FFFFFF', true, 8);
applyHeaderStyle($sheet, 'B3', '1E3A5F', 'FFFFFF', true, 8);
$sheet->getRowDimension($headerRow)->setRowHeight(36);

/* ── Fixed column widths ── */
$sheet->getColumnDimension('A')->setWidth(4);
$sheet->getColumnDimension('B')->setWidth(18);

/* ── Data rows ── */
$dataRowStart = $headerRow + 1;
$dataRow      = $dataRowStart;

/* Summary totals (for footer row) */
$grandPresent = 0;
$grandAbsent  = 0;
$grandLeave   = 0;
$grandLate    = 0;
$grandNoPay   = 0;

foreach ($users as $idx => $user) {
    $uid      = $user['user_id'];
    $rowBg    = ($idx % 2 === 0) ? 'FFFFFF' : 'F8FAFC';
    $nameDisp = $user['title'] . '. ' . $user['user_fname'] . ' ' . $user['user_lname'];

    /* Row number & name */
    setCellValueByColumnAndRow($sheet, 1, $dataRow, $idx + 1);
    setCellValueByColumnAndRow($sheet, 2, $dataRow, $nameDisp);

    $numLtrA = 'A';
    $numLtrB = 'B';

    $sheet->getStyle("A{$dataRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'size' => 8, 'color' => ['rgb' => '9CA3AF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);
    $sheet->getStyle("B{$dataRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'size' => 8, 'bold' => false, 'color' => ['rgb' => '111827']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rowBg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);

    /* Per-user counters */
    $cntPresent = 0;
    $cntAbsent  = 0;
    $cntLeave   = 0;
    $cntLate    = 0;
    $cntNoPay   = 0;

    $dColIdx = $dateColStart;
    foreach ($dates as $date) {
        $dt        = new DateTime($date);
        $isWeekend = in_array($dt->format('N'), ['6', '7']);

        /* Get attendance record for this user on this date */
        $rec    = $attMap[$uid][$date] ?? null;
        $status = '';
        $checkIn  = '';
        $checkOut = '';

        if ($rec) {
            $status   = $rec['att_status'] ?? 'present';
            $checkIn  = $rec['check_in']  ?? '';
            $checkOut = $rec['check_out'] ?? '';
        } elseif ($isWeekend) {
            $status = 'offday';
        } else {
            $status = '';
        }

        /* Map status to display */
        if ($isWeekend && empty($rec)) {
            $display = 'Off';
            $bgHex   = 'D9D9D9';
            $fgHex   = '595959';
        } elseif ($status === '') {
            $display = '—';
            $bgHex   = $rowBg;
            $fgHex   = '9CA3AF';
        } else {
            [$display, $bgHex, $fgHex] = classifyStatus($status);
        }

        /* Count for summary */
        $statusLower = strtolower($status);
        if ($display === 'P')   { $cntPresent++; }
        elseif ($display === 'Absent') { $cntAbsent++; }
        elseif (str_contains($statusLower, 'al') || str_contains($statusLower, 'sl') || str_contains($statusLower, 'cl')) { $cntLeave++; }
        elseif ($display === 'LL')  { $cntLate++; }
        elseif ($display === 'Absent' || $statusLower === '0') { $cntNoPay++; }

        /* Write cell */
        $colLtr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dColIdx);

        /* Show check-in time if present record */
        $cellValue = $display;
        if ($display === 'P' && $checkIn) {
            $cellValue = date('H:i', strtotime($checkIn));
        }

        setCellValueByColumnAndRow($sheet, $dColIdx, $dataRow, $cellValue);
        $sheet->getStyle("{$colLtr}{$dataRow}")->applyFromArray([
            'font'      => ['name' => $FONT_NAME, 'size' => 7, 'color' => ['rgb' => $fgHex]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgHex]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);

        $dColIdx++;
    }

    /* Write summary count cells */
    $summaryData = [
        'Present' => [$cntPresent, 'DCFCE7', '166534'],
        'Absent'  => [$cntAbsent,  'FEE2E2', '991B1B'],
        'Leave'   => [$cntLeave,   'FEF3C7', '92400E'],
        'Late'    => [$cntLate,    'E0F2FE', '075985'],
        'No Pay'  => [$cntNoPay,   'FEE2E2', '991B1B'],
    ];

    foreach ($summaryHeaders as $sh) {
        $sColIdx = $summaryCols[$sh];
        $colLtr  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sColIdx);
        [$val, $bg, $fg] = $summaryData[$sh];

        setCellValueByColumnAndRow($sheet, $sColIdx, $dataRow, $val);
        $sheet->getStyle("{$colLtr}{$dataRow}")->applyFromArray([
            'font'      => ['name' => $FONT_NAME, 'size' => 8, 'bold' => true, 'color' => ['rgb' => $val > 0 ? $fg : '9CA3AF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $val > 0 ? $bg : $rowBg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
        ]);
    }

    /* Accumulate grand totals */
    $grandPresent += $cntPresent;
    $grandAbsent  += $cntAbsent;
    $grandLeave   += $cntLeave;
    $grandLate    += $cntLate;
    $grandNoPay   += $cntNoPay;

    $sheet->getRowDimension($dataRow)->setRowHeight(16);
    $dataRow++;
}

/* ── Totals footer row ── */
$footerRow = $dataRow;
$totalCells = ($dateColStart - 1); // A and B

$sheet->mergeCells("A{$footerRow}:B{$footerRow}");
$sheet->setCellValue("A{$footerRow}", 'TOTALS');
$sheet->getStyle("A{$footerRow}")->applyFromArray([
    'font'      => ['name' => $FONT_NAME, 'bold' => true, 'size' => 8, 'color' => ['rgb' => 'FFFFFF']],
    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
]);

/* Fill date columns in footer with dashes */
for ($c = $dateColStart; $c < $dateColStart + count($dates); $c++) {
    $colLtr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
    setCellValueByColumnAndRow($sheet, $c, $footerRow, '');
    $sheet->getStyle("{$colLtr}{$footerRow}")->applyFromArray([
        'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
    ]);
}

/* Summary totals in footer */
$grandData = [
    'Present' => $grandPresent,
    'Absent'  => $grandAbsent,
    'Leave'   => $grandLeave,
    'Late'    => $grandLate,
    'No Pay'  => $grandNoPay,
];
foreach ($summaryHeaders as $sh) {
    $sColIdx = $summaryCols[$sh];
    $colLtr  = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($sColIdx);
    setCellValueByColumnAndRow($sheet, $sColIdx, $footerRow, $grandData[$sh]);
    $sheet->getStyle("{$colLtr}{$footerRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'bold' => true, 'size' => 9, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A5F']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
    ]);
}
$sheet->getRowDimension($footerRow)->setRowHeight(18);

/* ── Legend block ── */
$legendRow = $footerRow + 2;
$sheet->setCellValue("A{$legendRow}", 'LEGEND');
$sheet->getStyle("A{$legendRow}")->applyFromArray([
    'font' => ['name' => $FONT_NAME, 'bold' => true, 'size' => 8, 'color' => ['rgb' => '374151']],
]);

$legends = [
    ['P',       'FFFFFF', '111827', 'Present (check-in time shown)'],
    ['Absent',  'FFE0E0', 'C00000', 'Absent / No attendance'],
    ['AL',      'FFC000', '7B4F00', 'Annual Leave'],
    ['SL',      'FFC000', '7B4F00', 'Sick Leave'],
    ['CL',      '92D050', '375623', 'Casual Leave'],
    ['LL',      'E2EFDA', '375623', 'Late Attendance'],
    ['Off',     'D9D9D9', '595959', 'Off Day / Weekend / Holiday'],
    ['—',       'F9FAFB', '9CA3AF', 'No Record'],
];

$lRow = $legendRow + 1;
foreach ($legends as [$sym, $bg, $fg, $desc]) {
    $sheet->setCellValue("A{$lRow}", $sym);
    $sheet->setCellValue("B{$lRow}", $desc);
    $sheet->getStyle("A{$lRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'size' => 8, 'color' => ['rgb' => $fg]],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);
    $sheet->getStyle("B{$lRow}")->applyFromArray([
        'font'      => ['name' => $FONT_NAME, 'size' => 8, 'color' => ['rgb' => '374151']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F9FAFB']],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E5E7EB']]],
    ]);
    $sheet->getRowDimension($lRow)->setRowHeight(14);
    $lRow++;
}

/* ── Freeze panes: keep header + name columns fixed when scrolling ── */
$freezeCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($dateColStart);
$sheet->freezePane("{$freezeCol}4");

/* ── Page setup for printing ── */
$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A3)
    ->setFitToPage(true)
    ->setFitToWidth(1)
    ->setFitToHeight(0);
$sheet->getHeaderFooter()
    ->setOddHeader('&C&B&14Staff Attendance Report')
    ->setOddFooter('&LGenerated: ' . date('d M Y H:i') . '&RPage &P of &N');

/* ════════════════════════════════════════════════════════════════════════════
   4. OUTPUT
════════════════════════════════════════════════════════════════════════════ */
$filename = 'Attendance_Report_' . $fromDate . '_to_' . $toDate . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;