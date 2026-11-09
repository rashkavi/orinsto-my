<?php
$status = new stdClass();

require_once("../includes/UserClass.php");
require_once '../vendor/autoload.php';
require_once '../includes/connection.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (SessionUser::hasValidSession()) {

    date_default_timezone_set('Asia/Colombo');

    $from_date = isset($_GET['from_date']) ? $_GET['from_date'] : date('Y-m-d');
    $to_date   = isset($_GET['to_date']) ? $_GET['to_date'] : date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
        $from_date = date('Y-m-d');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
        $to_date = date('Y-m-d');
    }

    if (strtotime($from_date) > strtotime($to_date)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'From date cannot be greater than To date']);
        exit;
    }

    $fromDateSafe = Database::escape($from_date);
    $toDateSafe   = Database::escape($to_date);

    $startDate = $fromDateSafe . ' 00:00:00';
    $endDate   = $toDateSafe . ' 23:59:59';

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Daily Admissions');

    $query = "
        SELECT 
            p.programme_id,
            p.programme,

            COUNT(DISTINCT CASE 
                WHEN shi.addmission_date BETWEEN '$startDate' AND '$endDate'
                THEN shi.students_has_intakes_id 
            END) AS actuals_today,

            COUNT(DISTINCT shi.students_has_intakes_id) AS total_actuals,

            COUNT(DISTINCT CASE 
                WHEN l.date_added BETWEEN '$startDate' AND '$endDate'
                THEN l.lead_id 
            END) AS app_reg_today,

            COUNT(DISTINCT l.lead_id) AS total_app_reg,

            COUNT(DISTINCT CASE 
                WHEN shi.addmission_date BETWEEN '$startDate' AND '$endDate'
                THEN shi.students_has_intakes_id 
            END) AS app_today,

            COUNT(DISTINCT shi.students_has_intakes_id) AS total_app,

            COUNT(DISTINCT CASE 
                WHEN shi.addmission_date < '$startDate'
                THEN shi.students_has_intakes_id 
            END) AS previous_intakes_registrations

        FROM programmes p

        LEFT JOIN leads l 
            ON l.programmes_programme_id = p.programme_id

        LEFT JOIN students_has_intakes shi 
            ON shi.leads_lead_id = l.lead_id

        GROUP BY p.programme_id, p.programme
        ORDER BY p.programme ASC
    ";

    $resultset = Database::search($query);

    $reports = [];

    while ($rowData = $resultset->fetch_assoc()) {
        $reports[] = $rowData;
    }

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
    $sheet->setCellValue('A2', 'SWLC Daily Admission Reports');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A2')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $sheet->mergeCells('A3:L3');
    $sheet->setCellValue(
        'A3',
        'Daily Admissions Report - ' .
            date('jS F Y', strtotime($from_date)) .
            ' to ' .
            date('jS F Y', strtotime($to_date))
    );

    $sheet->getStyle('A3')->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle('A3')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);

    $headerRow = 4;

    $headers = [
        'A' => 'Programme',
        'B' => 'Actuals',
        'C' => 'Selected Range',
        'D' => "Total\nActuals",
        'E' => "App +\nReg",
        'F' => 'Selected Range',
        'G' => "Total\nApp +\nReg",
        'H' => 'App',
        'I' => 'Selected Range',
        'J' => "Total\nApp",
        'K' => 'TOTAL',
        'L' => "Previous\nIntakes\nRegistrations",
    ];

    foreach ($headers as $col => $text) {
        $sheet->setCellValue($col . $headerRow, $text);
    }

    $sheet->getStyle("A{$headerRow}:L{$headerRow}")->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 10
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

    $blueColumns = ['D', 'G', 'J'];

    foreach ($blueColumns as $col) {
        $sheet->getStyle($col . $headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('DDEBF7');
    }

    $sheet->getStyle('K' . $headerRow)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('FFFF00');

    $sheet->getRowDimension($headerRow)->setRowHeight(48);

    $row = 5;

    $totalActualsToday = 0;
    $totalActuals = 0;
    $totalAppRegToday = 0;
    $totalAppReg = 0;
    $totalAppToday = 0;
    $totalApp = 0;
    $grandTotal = 0;
    $totalPrevious = 0;

    foreach ($reports as $data) {

        $actualsToday = (int)$data['actuals_today'];
        $actualsTotal = (int)$data['total_actuals'];
        $appRegToday = (int)$data['app_reg_today'];
        $appRegTotal = (int)$data['total_app_reg'];
        $appToday = (int)$data['app_today'];
        $appTotal = (int)$data['total_app'];
        $previousTotal = (int)$data['previous_intakes_registrations'];

        $rowTotal = $actualsToday + $appRegToday + $appToday;

        $sheet->setCellValue("A$row", $data['programme']);
        $sheet->setCellValue("B$row", $actualsToday);
        $sheet->setCellValue("C$row", $actualsToday);
        $sheet->setCellValue("D$row", $actualsTotal);
        $sheet->setCellValue("E$row", $appRegToday);
        $sheet->setCellValue("F$row", $appRegToday);
        $sheet->setCellValue("G$row", $appRegTotal);
        $sheet->setCellValue("H$row", $appToday);
        $sheet->setCellValue("I$row", $appToday);
        $sheet->setCellValue("J$row", $appTotal);
        $sheet->setCellValue("K$row", $rowTotal);
        $sheet->setCellValue("L$row", $previousTotal);

        $sheet->getStyle("A$row:L$row")->applyFromArray([
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

        foreach ($blueColumns as $col) {
            $sheet->getStyle($col . $row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('DDEBF7');
        }

        $sheet->getStyle("K$row")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('FFFF00');

        $sheet->getRowDimension($row)->setRowHeight(36);

        $totalActualsToday += $actualsToday;
        $totalActuals += $actualsTotal;
        $totalAppRegToday += $appRegToday;
        $totalAppReg += $appRegTotal;
        $totalAppToday += $appToday;
        $totalApp += $appTotal;
        $grandTotal += $rowTotal;
        $totalPrevious += $previousTotal;

        $row++;
    }

    $sheet->setCellValue("A$row", 'TOTAL');
    $sheet->setCellValue("B$row", $totalActualsToday);
    $sheet->setCellValue("C$row", $totalActualsToday);
    $sheet->setCellValue("D$row", $totalActuals);
    $sheet->setCellValue("E$row", $totalAppRegToday);
    $sheet->setCellValue("F$row", $totalAppRegToday);
    $sheet->setCellValue("G$row", $totalAppReg);
    $sheet->setCellValue("H$row", $totalAppToday);
    $sheet->setCellValue("I$row", $totalAppToday);
    $sheet->setCellValue("J$row", $totalApp);
    $sheet->setCellValue("K$row", $grandTotal);
    $sheet->setCellValue("L$row", $totalPrevious);

    $sheet->getStyle("A$row:L$row")->applyFromArray([
        'font' => [
            'bold' => true
        ],
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

    $sheet->getStyle("K$row")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('FFFF00');

    $sheet->getColumnDimension('A')->setWidth(30);
    $sheet->getColumnDimension('B')->setWidth(12);
    $sheet->getColumnDimension('C')->setWidth(14);
    $sheet->getColumnDimension('D')->setWidth(11);
    $sheet->getColumnDimension('E')->setWidth(12);
    $sheet->getColumnDimension('F')->setWidth(14);
    $sheet->getColumnDimension('G')->setWidth(11);
    $sheet->getColumnDimension('H')->setWidth(12);
    $sheet->getColumnDimension('I')->setWidth(14);
    $sheet->getColumnDimension('J')->setWidth(11);
    $sheet->getColumnDimension('K')->setWidth(10);
    $sheet->getColumnDimension('L')->setWidth(18);

    $fileName = 'SWLC-Daily-Admissions-Report-' . $from_date . '-to-' . $to_date . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"$fileName\"");
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
} else {

    header('Content-Type: application/json');
    $status->error = 'Authentication Failed!';
    echo json_encode($status);
    exit;
}
