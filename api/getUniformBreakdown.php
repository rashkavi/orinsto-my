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
$uid = (int) SessionUser::getCurrentUserObject()['user_id'];
$in  = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    // ---- Dropdown options (scoped to eligible students, cascading) ----
    if (($in['action'] ?? '') === 'options') {
        $map = [
            'faculty'   => ['f.faculty_id', 'f.faculty'],
            'programme' => ['p.programme_id', 'p.programme'],
            'intake'    => ['i.intake_id', 'i.intake'],
        ];
        if (!isset($map[$in['stage'] ?? ''])) throw new Exception('Invalid stage');
        [$idCol, $nameCol] = $map[$in['stage']];
        $sql = "SELECT DISTINCT $idCol AS id, $nameCol AS name " . uniformFrom() .
            uniformWhere($role, $uid, $in, false) . " AND $idCol IS NOT NULL ORDER BY $nameCol ASC";
        echo json_encode(['options' => Database::search($sql)->fetch_all(MYSQLI_ASSOC)]);
        exit;
    }

    // ---- Breakdown: table rows, cards and charts all come from the same filtered rows ----
    $sql = "SELECT shi.students_has_intakes_id AS shi_id, shi.student_id AS sId, shi.uniform_status,
                   t.title, ld.first_name, ld.last_name, s.profile_img,
                   f.faculty, p.programme, i.intake, u.scheduled, u.paid,u.course_payments_id,
                   udc.uniform_distribution_confirmations_id AS confirmation_id " .
        uniformFrom() . uniformWhere($role, $uid, $in) .
        " ORDER BY (u.scheduled - u.paid) DESC, ld.first_name ASC";
    $rows = Database::search($sql)->fetch_all(MYSQLI_ASSOC);

    $sum = [
        'total' => 0,
        'pending_payments' => 0,
        'payments_received' => 0,
        'uniforms_pending' => 0,
        'uniforms_received' => 0,
        'collected' => 0.0,
        'outstanding' => 0.0
    ];
    foreach ($rows as &$r) {
        $r['scheduled']   = round((float) $r['scheduled'], 2);
        $r['paid']        = round((float) $r['paid'], 2);
        $r['outstanding'] = max(0, round($r['scheduled'] - $r['paid'], 2));
        $r['payment_status'] = $r['outstanding'] > 0.005 ? 'PENDING' : 'RECEIVED';

        $sum['total']++;
        $sum[$r['payment_status'] === 'PENDING' ? 'pending_payments' : 'payments_received']++;
        $sum[$r['uniform_status'] === 'RECEIVED' ? 'uniforms_received' : 'uniforms_pending']++;
        $sum['collected']   += $r['paid'];
        $sum['outstanding'] += $r['outstanding'];
    }
    echo json_encode(['students' => $rows, 'summary' => $sum]);
} catch (Throwable $e) {
    echo json_encode(['error' => 'Failed to load uniform breakdown.' . $e->getMessage()]);
}
