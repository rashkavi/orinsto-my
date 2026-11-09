<?php

require_once '../includes/connection.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

date_default_timezone_set('Asia/Colombo');

$from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
$to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid from date"]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "Invalid to date"]);
    exit;
}

if (strtotime($from_date) > strtotime($to_date)) {
    header('Content-Type: application/json');
    echo json_encode(["error" => "From date cannot be greater than To date"]);
    exit;
}

$fromDateSafe = Database::escape($from_date);
$toDateSafe   = Database::escape($to_date);

$startDate = $fromDateSafe . ' 00:00:00';
$endDate   = $toDateSafe . ' 23:59:59';

/* Status IDs */
$HOT_ID = 7;
$WARM_ID = 2;
$BUSY_ID = 3;
$SLEEP_ID = 4;
$ENROLLED_ID = 16;
$FUTURE_ID = 10;
$COLD_ID = 9;

$query = "
SELECT
    p.programme_id,
    p.programme,

    COUNT(DISTINCT CASE
        WHEN l.lead_status_lead_status_id NOT IN ($ENROLLED_ID, $COLD_ID)
        THEN l.lead_id
    END) AS ongoing_leads,

    COUNT(DISTINCT CASE
        WHEN ll.date_updated BETWEEN '$startDate' AND '$endDate'
        THEN ll.leads_id
    END) AS today_followed,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $HOT_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS hot_count,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $WARM_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS warm_count,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $BUSY_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS busy_count,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $SLEEP_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS sleep_count,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $ENROLLED_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS enrolled_count,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $FUTURE_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS future_count,

    COUNT(DISTINCT CASE 
        WHEN l.lead_status_lead_status_id = $COLD_ID
        AND l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id 
    END) AS cold_count,

    COUNT(DISTINCT CASE
        WHEN l.date_added BETWEEN '$startDate' AND '$endDate'
        THEN l.lead_id
    END) AS total_leads

FROM programmes p

LEFT JOIN leads l
    ON l.programmes_programme_id = p.programme_id

LEFT JOIN lead_logs ll
    ON ll.leads_id = l.lead_id

GROUP BY p.programme_id, p.programme
ORDER BY p.programme ASC
";

$resultset = Database::search($query);

$reports = [];

while ($data = $resultset->fetch_assoc()) {
    $reports[] = $data;
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Daily Inquiry Summary');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);

$sheet->getPageSetup()
    ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
    ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

$sheet->getPageMargins()->setTop(0.25);
$sheet->getPageMargins()->setRight(0.25);
$sheet->getPageMargins()->setLeft(0.25);
$sheet->getPageMargins()->setBottom(0.25);

$sheet->getDefaultRowDimension()->setRowHeight(22);

$logoPath = '../assets/imgs/common/logo.webp';

$sheet->getRowDimension(1)->setRowHeight(65);
$sheet->getRowDimension(2)->setRowHeight(24);
$sheet->getRowDimension(3)->setRowHeight(22);

$sheet->mergeCells('A1:L1');

if (file_exists($logoPath)) {
    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
    $drawing->setName('SWLC Logo');
    $drawing->setPath($logoPath);
    $drawing->setHeight(70);
    $drawing->setCoordinates('F1');
    $drawing->setOffsetX(12);
    $drawing->setOffsetY(4);
    $drawing->setWorksheet($sheet);
}

$sheet->mergeCells('A2:L2');
$sheet->setCellValue('A2', 'SWLC Daily Inquiry Summary Report');
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A2')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$sheet->mergeCells('A3:L3');
$sheet->setCellValue(
    'A3',
    'Daily Inquiries Summary Report - ' .
    date('jS F Y', strtotime($from_date)) .
    ' to ' .
    date('jS F Y', strtotime($to_date))
);

$sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
$sheet->getStyle('A3')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

/* Summary totals */
$totalOngoing = 0;
$totalFollowed = 0;
$totalHot = 0;
$totalWarm = 0;
$totalBusy = 0;
$totalSleep = 0;
$totalEnrolled = 0;
$totalFuture = 0;
$totalCold = 0;
$totalLeads = 0;

foreach ($reports as $r) {
    $totalOngoing += (int)$r['ongoing_leads'];
    $totalFollowed += (int)$r['today_followed'];
    $totalHot += (int)$r['hot_count'];
    $totalWarm += (int)$r['warm_count'];
    $totalBusy += (int)$r['busy_count'];
    $totalSleep += (int)$r['sleep_count'];
    $totalEnrolled += (int)$r['enrolled_count'];
    $totalFuture += (int)$r['future_count'];
    $totalCold += (int)$r['cold_count'];
    $totalLeads += (int)$r['total_leads'];
}

$sheet->setCellValue('A5', 'Ongoing Leads');
$sheet->setCellValue('B5', $totalOngoing);
$sheet->setCellValue('C5', 'Range Followed');
$sheet->setCellValue('D5', $totalFollowed);
$sheet->setCellValue('E5', 'Range Total Leads');
$sheet->setCellValue('F5', $totalLeads);

$sheet->getStyle('A5:F5')->applyFromArray([
    'font' => ['bold' => true, 'size' => 11],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
]);

$sheet->getStyle('A5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DDEBF7');
$sheet->getStyle('B5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF4FF');
$sheet->getStyle('C5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAD3');
$sheet->getStyle('D5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EAF7E8');
$sheet->getStyle('E5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF2CC');
$sheet->getStyle('F5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8E5');

$headerRow = 8;

$headers = [
    'A' => 'Programme',
    'B' => "Ongoing\nLeads",
    'C' => "Range\nFollowed",
    'D' => 'Hot',
    'E' => 'Warm',
    'F' => 'Busy',
    'G' => 'Sleep',
    'H' => 'Enrolled',
    'I' => 'Future',
    'J' => 'Cold',
    'K' => 'TOTAL'
];

foreach ($headers as $col => $title) {
    $sheet->setCellValue($col . $headerRow, $title);
}

$sheet->getRowDimension($headerRow)->setRowHeight(45);

$sheet->getStyle("A{$headerRow}:K{$headerRow}")->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 10,
        'color' => ['rgb' => '000000']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
        'wrapText' => true
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ]
]);

$sheet->getStyle('D' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FF0000');
$sheet->getStyle('E' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
$sheet->getStyle('F' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC000');
$sheet->getStyle('G' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00A9E0');
$sheet->getStyle('H' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('7030A0');
$sheet->getStyle('I' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('00B050');
$sheet->getStyle('J' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('7F7F7F');
$sheet->getStyle('K' . $headerRow)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFFFF');

$row = 9;

foreach ($reports as $data) {

    $rowTotal =
        (int)$data['hot_count'] +
        (int)$data['warm_count'] +
        (int)$data['busy_count'] +
        (int)$data['sleep_count'] +
        (int)$data['enrolled_count'] +
        (int)$data['future_count'] +
        (int)$data['cold_count'];

    $sheet->setCellValue("A$row", $data['programme']);
    $sheet->setCellValue("B$row", (int)$data['ongoing_leads']);
    $sheet->setCellValue("C$row", (int)$data['today_followed']);
    $sheet->setCellValue("D$row", (int)$data['hot_count']);
    $sheet->setCellValue("E$row", (int)$data['warm_count']);
    $sheet->setCellValue("F$row", (int)$data['busy_count']);
    $sheet->setCellValue("G$row", (int)$data['sleep_count']);
    $sheet->setCellValue("H$row", (int)$data['enrolled_count']);
    $sheet->setCellValue("I$row", (int)$data['future_count']);
    $sheet->setCellValue("J$row", (int)$data['cold_count']);
    $sheet->setCellValue("K$row", $rowTotal);

    $sheet->getStyle("A$row:K$row")->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);

    $sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getRowDimension($row)->setRowHeight(28);

    $row++;
}

$sheet->setCellValue("A$row", 'TOTAL');
$sheet->setCellValue("B$row", $totalOngoing);
$sheet->setCellValue("C$row", $totalFollowed);
$sheet->setCellValue("D$row", $totalHot);
$sheet->setCellValue("E$row", $totalWarm);
$sheet->setCellValue("F$row", $totalBusy);
$sheet->setCellValue("G$row", $totalSleep);
$sheet->setCellValue("H$row", $totalEnrolled);
$sheet->setCellValue("I$row", $totalFuture);
$sheet->setCellValue("J$row", $totalCold);
$sheet->setCellValue("K$row", $totalHot + $totalWarm + $totalBusy + $totalSleep + $totalEnrolled + $totalFuture + $totalCold);

$sheet->getStyle("A$row:K$row")->applyFromArray([
    'font' => ['bold' => true],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => '000000']
        ]
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => 'F2F2F2']
    ]
]);

$sheet->getColumnDimension('A')->setWidth(32);
$sheet->getColumnDimension('B')->setWidth(14);
$sheet->getColumnDimension('C')->setWidth(14);
$sheet->getColumnDimension('D')->setWidth(10);
$sheet->getColumnDimension('E')->setWidth(10);
$sheet->getColumnDimension('F')->setWidth(10);
$sheet->getColumnDimension('G')->setWidth(10);
$sheet->getColumnDimension('H')->setWidth(12);
$sheet->getColumnDimension('I')->setWidth(10);
$sheet->getColumnDimension('J')->setWidth(10);
$sheet->getColumnDimension('K')->setWidth(10);

$fileName = 'SWLC-Daily-Inquiries-Summary-Report-' . $from_date . '-to-' . $to_date . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$fileName\"");
header('Cache-Control: max-age=0');

$writer->save('php://output');
exit;