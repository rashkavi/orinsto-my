<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../vendor/autoload.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    exit('Unauthorized');
}

$sessionUserRole = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$counselorUserId = (int) $sessionUserObject['user_id'];
if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($_GET['counselor_id'])) {
    $counselorUserId = (int) $_GET['counselor_id'];
}

$assignedDateExpr = counselorAssignedDateExpr();
$assignedDateJoin = counselorAssignedDateJoinSql();

$query = "
    SELECT
        `login_details`.`first_name`,
        `login_details`.`last_name`,
        `login_details`.`email`,
        `students`.`mobile`,
        `login_details`.`whatsapp`,
        `title`.`title`,
        `status`.`status`,
        {$assignedDateExpr} AS assigned_date
    FROM `students_has_intakes`
    {$assignedDateJoin}
    INNER JOIN `students` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
    INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
    INNER JOIN `title` ON `title`.`title_id` = `students`.`title_title_id`
    INNER JOIN `status` ON `status`.`status_id` = `login_details`.`status_status_id`
    WHERE `students_has_intakes`.`counselor_user_id` = '" . Database::escape($counselorUserId) . "'
    ORDER BY assigned_date DESC
";
$result = Database::search($query);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Assigned Students');

$headers = ['Title', 'First Name', 'Last Name', 'Email', 'Mobile', 'WhatsApp', 'Assigned Date', 'Status'];
$sheet->fromArray($headers, null, 'A1');

$headerRange = 'A1:H1';
$sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B2A4A');
$sheet->getStyle($headerRange)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setBold(true);

$row = 2;
while ($data = $result->fetch_assoc()) {
    $sheet->fromArray([
        $data['title'],
        $data['first_name'],
        $data['last_name'],
        $data['email'],
        $data['mobile'],
        $data['whatsapp'],
        date('Y-m-d h:i A', strtotime($data['assigned_date'])),
        $data['status'],
    ], null, 'A' . $row);
    $row++;
}

foreach (range('A', 'H') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$sheet->setAutoFilter($headerRange);
$sheet->freezePane('A2');

$filename = 'assigned-students-' . date('Y-m-d') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

function counselorAssignedDateJoinSql($alias = 'students_has_intakes')
{
    return "
        LEFT JOIN (
            SELECT
                `students_has_intakes_students_has_intakes_id`,
                MAX(`date_updated`) AS `last_counselor_change`
            FROM `students_has_intakes_logs`
            WHERE `log` LIKE 'Counselor changed%'
            GROUP BY `students_has_intakes_students_has_intakes_id`
        ) `counselor_change_log`
            ON `counselor_change_log`.`students_has_intakes_students_has_intakes_id` = `{$alias}`.`students_has_intakes_id`
    ";
}

function counselorAssignedDateExpr($alias = 'students_has_intakes')
{
    return "COALESCE(`counselor_change_log`.`last_counselor_change`, `{$alias}`.`addmission_date`)";
}
