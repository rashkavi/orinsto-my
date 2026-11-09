<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";
require_once "../includes/Dashboards/IntakesAttendanceSummary.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Your session has expired. Please log in again.']);
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have access to this page.']);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$timezone = new DateTimeZone("Asia/Colombo");
$today    = (new DateTime("now", $timezone))->format('Y-m-d');

$date = isset($_GET['date']) ? $_GET['date'] : $today;
if (!isValidAttendanceDate($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a valid date.']);
    exit;
}
if ($date > $today) {
    http_response_code(400);
    echo json_encode(['error' => 'Future dates are not available yet.']);
    exit;
}

echo json_encode([
    'date'  => $date,
    'today' => $today,
    'cards' => getIntakesAttendanceSummaryData($date),
]);