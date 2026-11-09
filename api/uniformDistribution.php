<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/UserClass.php';
require_once __DIR__ . '/../includes/UserPermissionsClass.php';
require_once __DIR__ . '/../includes/connection.php';
require_once __DIR__ . '/../includes/classes/UniformScope.php';

if (!SessionUser::hasValidSession() || ($role = SessionUser::getCurrentUserRole()) == null) {
    echo json_encode(['error' => 'Session expired. Please log in again.']);
    exit;
}
$uid   = (int) SessionUser::getCurrentUserObject()['user_id'];
$in    = json_decode(file_get_contents('php://input'), true) ?: [];
$shiId = (int) ($in['shi_id'] ?? 0);

try {
    if ($shiId <= 0) throw new Exception('Invalid student.');

    // Student must be an eligible student inside this user's scope.
    $check = Database::search(
        "SELECT shi.uniform_status, u.scheduled, u.paid, udc.uniform_distribution_confirmations_id AS cid " .
            uniformFrom() . uniformWhere($role, $uid, []) . " AND shi.students_has_intakes_id = $shiId"
    )->fetch_assoc();
    if (!$check) throw new Exception('Student not found.');

    if (($in['action'] ?? '') === 'details') {
        $d = Database::search(
            "SELECT udc.note, udc.confirmed_at, CONCAT(usr.user_fname, ' ', usr.user_lname) AS confirmed_by
             FROM uniform_distribution_confirmations udc
             LEFT JOIN users usr ON usr.user_id = udc.confirmed_by_user_id
             WHERE udc.students_has_intakes_id = $shiId"
        )->fetch_assoc();
        if (!$d) throw new Exception('No confirmation found.');
        echo json_encode(['details' => $d]);
        exit;
    }

    if (($in['action'] ?? '') === 'confirm') {
        if ($check['uniform_status'] === 'RECEIVED' || $check['cid']) throw new Exception('Uniform already confirmed.');
        if (((float) $check['scheduled'] - (float) $check['paid']) > 0.005) {
            throw new Exception('Uniform fee must be fully paid before confirming distribution.');
        }
        $note = Database::escape(trim($in['note'] ?? ''));
        // Unique key on students_has_intakes_id blocks a double-submit race.
        Database::iud("INSERT INTO uniform_distribution_confirmations
                       (students_has_intakes_id, note, confirmed_by_user_id, confirmed_at)
                       VALUES ($shiId, '$note', $uid, NOW())");
        Database::iud("UPDATE students_has_intakes SET uniform_status = 'RECEIVED'
                       WHERE students_has_intakes_id = $shiId");
        echo json_encode(['success' => true]);
        exit;
    }
    throw new Exception('Invalid action.');
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
