<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

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

// Same resolution as the assigned-students list: default to the logged in
// counselor, only a Pro/AcademicPro user can look at someone else's roster.
$counselorUserId = (int) $sessionUserObject['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($input['counselor_id'])) {
    $counselorUserId = (int) $input['counselor_id'];
}

$stage      = isset($input['stage']) ? $input['stage'] : ''; // programmes | intakes | subjects
$facultyId  = isset($input['faculty_id']) ? (int) $input['faculty_id'] : 0;
$programmeId = isset($input['programme_id']) ? (int) $input['programme_id'] : 0;
$intakeId   = isset($input['intake_id']) ? (int) $input['intake_id'] : 0;

if (!in_array($stage, ['programmes', 'intakes', 'subjects'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown filter stage requested.']);
    exit;
}

$counselorEsc = Database::escape($counselorUserId);

// Every stage starts from the same base: this counselor's live assigned
// students, joined up to faculty/programme/intake. Optional filters just
// narrow that same base further before the DISTINCT projection.
$baseJoin = "
    FROM students_has_intakes shi
    INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
    INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
    INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
     INNER JOIN levels l ON l.intakes_intake_id = i.intake_id
    INNER JOIN subjects s ON s.levels_level_id = l.level_id
      WHERE shi.status_status_id != '3'
";

if (SessionUser::ifUserIsCoordinator($sessionUserRole)) {
    $baseJoin = "
        FROM students_has_intakes shi
        INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
        INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
        INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
        INNER JOIN `users_has_permission_to_programmes` UPP ON UPP.programmes_programme_id = p.programme_id
         INNER JOIN levels l ON l.intakes_intake_id = i.intake_id
    INNER JOIN subjects s ON s.levels_level_id = l.level_id
        WHERE UPP.users_user_id = '" . $counselorEsc . "'
          AND shi.status_status_id != '3'
    ";
}

if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
    $baseJoin = "
        FROM students_has_intakes shi
        INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
        INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
        INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
         INNER JOIN levels l ON l.intakes_intake_id = i.intake_id
    INNER JOIN subjects s ON s.levels_level_id = l.level_id
        WHERE shi.counselor_user_id = '" . $counselorEsc . "'
          AND shi.status_status_id != '3'
    ";
}

if ($facultyId > 0) {
    $baseJoin .= " AND f.faculty_id = '" . Database::escape($facultyId) . "'";
}
if ($programmeId > 0) {
    $baseJoin .= " AND p.programme_id = '" . Database::escape($programmeId) . "'";
}
if ($intakeId > 0) {
    $baseJoin .= " AND i.intake_id = '" . Database::escape($intakeId) . "'";
}

if ($stage === 'programmes') {
    $query = "SELECT DISTINCT p.programme_id, p.programme " . $baseJoin . " ORDER BY p.programme ASC";
    $rows = Database::search($query)->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['programmes' => $rows]);
    exit;
}

if ($stage === 'intakes') {
    $query = "SELECT DISTINCT i.intake_id, i.intake " . $baseJoin . " ORDER BY i.intake ASC";
    $rows = Database::search($query)->fetch_all(MYSQLI_ASSOC);
    echo json_encode(['intakes' => $rows]);
    exit;
}

// stage === 'subjects' - subjects hang off levels, which hang off the
// intake, so this one needs two more joins on top of the shared base.
$query = "
    SELECT DISTINCT s.subject_id, s.subject, l.level_id, l.level
    " . $baseJoin . "
   
    ORDER BY l.level ASC, s.subject ASC
";
$rows = Database::search($query)->fetch_all(MYSQLI_ASSOC);
echo json_encode(['subjects' => $rows]);
