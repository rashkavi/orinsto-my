<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Dashboards/IntakesAttendanceSummary.php";
require_once '../vendor/autoload.php'; // adjust if PhpSpreadsheet's autoload lives elsewhere in this project

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    exit('Your session has expired. Please log in again.');
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    http_response_code(403);
    exit('You do not have access to this page.');
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$timezone = new DateTimeZone("Asia/Colombo");
$today    = (new DateTime("now", $timezone))->format('Y-m-d');

$date = isset($_GET['date']) ? $_GET['date'] : $today;
if (!isValidAttendanceDate($date) || $date > $today) {
    http_response_code(400);
    exit('Please select a valid date.');
}

$cards = getIntakesAttendanceSummaryData($date);

// Mirror the same search/branch/faculty/programme filters the card view
// uses client-side, so the export matches whatever the user is looking at.
$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$branch    = isset($_GET['branch']) ? trim($_GET['branch']) : '';
$faculty   = isset($_GET['faculty']) ? trim($_GET['faculty']) : '';
$programme = isset($_GET['programme']) ? trim($_GET['programme']) : '';

if ($search !== '' || $branch !== '' || $faculty !== '' || $programme !== '') {
    $cards = array_values(array_filter($cards, function ($c) use ($search, $branch, $faculty, $programme) {
        if ($branch !== '' && $c['branch'] !== $branch) return false;
        if ($faculty !== '' && $c['faculty'] !== $faculty) return false;
        if ($programme !== '' && $c['programme'] !== $programme) return false;
        if ($search !== '') {
            $haystack = strtolower($c['intake'] . ' ' . $c['programme'] . ' ' . $c['faculty'] . ' ' . $c['branch']);
            if (strpos($haystack, strtolower($search)) === false) return false;
        }
        return true;
    }));
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Batches Attendance');

$headers = ['Intake', 'Faculty', 'Programme', 'Total Students', 'Present', 'On Time', 'Late', 'Morning Skipped', 'Absent'];
$sheet->fromArray($headers, null, 'A1');

// Standard navy header row for every XLSX export in this project.
$lastCol = chr(64 + count($headers));
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F0C71F']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$sheet->getRowDimension(1)->setRowHeight(22);

$rowIndex = 2;
foreach ($cards as $c) {
    $sheet->fromArray([
        $c['intake'],
        $c['faculty'],
        $c['programme'],
        $c['total_students'],
        $c['present'],
        $c['on_time'],
        $c['late'],
        $c['morning_skipped'],
        $c['absent'],
    ], null, 'A' . $rowIndex);
    $rowIndex++;
}
$lastRow = max($rowIndex - 1, 1);

$sheet->setAutoFilter('A1:' . $lastCol . $lastRow);
$sheet->freezePane('A2');
foreach (range('A', $lastCol) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'batches-attendance-summary-' . $date . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;