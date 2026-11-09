<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}
$sessionUserObject = SessionUser::getCurrentUserObject();

$input = json_decode(file_get_contents('php://input'), true);

$lecturersId     = (int)($input['lecturers_id'] ?? 0);
$contractId      = (int)($input['lecturer_contract_id'] ?? 0);
$timeTableIds    = array_values(array_unique(array_map('intval', (array)($input['time_table_ids'] ?? []))));
$manualDuration  = isset($input['duration']) && $input['duration'] !== null && $input['duration'] !== '' ? (float) $input['duration'] : null;
$manualAmount    = isset($input['amount']) ? (float) $input['amount'] : null;
$note            = trim($input['note'] ?? '');
$earningStatusId = (int)($input['earning_status_id'] ?? 0);
$addedBy         = (int) $sessionUserObject['user_id'];

if (!$lecturersId || !$contractId || empty($timeTableIds) || $manualAmount === null) {
    echo json_encode(['success' => false, 'message' => 'Missing/invalid parameters']);
    exit;
}
if (!in_array($earningStatusId, [1, 3], true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status — must be Confirm (2) or Reject (3)']);
    exit;
}

$idList = implode(',', $timeTableIds);

// ---- 1. Load the subject/lecturer assignment ----
$lecturerResult = Database::search("
    SELECT lecturers_id, subjects_subject_id, lecture_rate, minimum_payable_hours,
           `lecture_payment _type_lecture_payment _type_id` AS payment_type_id,`users_user_id`
    FROM lecturers
    WHERE lecturers_id = " . Database::escape($lecturersId) . "
      AND lecturer_contracts_lecturer_contract_id = " . Database::escape($contractId) . "
    LIMIT 1
");
if ($lecturerResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Lecturer/contract mismatch']);
    exit;
}
$lecturerRow = $lecturerResult->fetch_assoc();
$payTypeId = (int) $lecturerRow['payment_type_id'];

if ($payTypeId == 4) {
    echo json_encode(['success' => false, 'message' => 'This subject is Fixed Amount — use the fixed-payment flow instead']);
    exit;
}
if ($payTypeId == 1 && ($manualDuration === null || $manualDuration <= 0)) {
    echo json_encode(['success' => false, 'message' => 'Paid hours is required for Per Hour payment type']);
    exit;
}
if ($earningStatusId == 3 && $note === '') {
    echo json_encode(['success' => false, 'message' => 'A note is required when rejecting a payment']);
    exit;
}

// ---- 2. Validate the time_table rows: belong to this lecturer, not
//      already billed, and all share the exact same date/start/end ----
$rowsResult = Database::search("
    SELECT tt.time_table_id, tt.date, tt.start_time, tt.end_time,
           lphl.lecturer_payments_has_lecture_id AS already_billed
    FROM time_table tt
    LEFT JOIN lecturer_payments_has_lectures lphl
           ON lphl.time_table_time_table_id = tt.time_table_id
    WHERE tt.time_table_id IN ($idList)
      AND tt.lecturers_lecturers_id = " . Database::escape($lecturersId) . "
");

$fetched = [];
while ($r = $rowsResult->fetch_assoc()) {
    $fetched[] = $r;
}
if (count($fetched) !== count($timeTableIds)) {
    echo json_encode(['success' => false, 'message' => 'One or more lectures do not belong to this lecturer']);
    exit;
}
foreach ($fetched as $r) {
    if ($r['already_billed']) {
        echo json_encode(['success' => false, 'message' => "Lecture #{$r['time_table_id']} is already billed"]);
        exit;
    }
}
$distinctSlots = array_unique(array_map(
    fn($r) => $r['date'] . '|' . $r['start_time'] . '|' . $r['end_time'],
    $fetched
));
if (count($distinctSlots) !== 1) {
    echo json_encode(['success' => false, 'message' => 'Selected lectures must all be the same date/time']);
    exit;
}

// ---- 3. Units / duration for the record ----
if ($payTypeId == 1) {
    $units = round($manualDuration, 2);
    $paidDuration = round($manualDuration, 2);
} else {
    $units = 1;
    $paidDuration = null;
}

// ---- 4. Save ----
Database::iud("START TRANSACTION");

$durationSql = ($paidDuration === null) ? "NULL" : Database::escape($paidDuration);
$noteSql = ($note === '') ? "NULL" : "'" . Database::escape($note) . "'";

Database::iud("
    INSERT INTO lecturer_payments
        (users_user_id, lecturer_contracts_lecturer_contract_id, subjects_subject_id,
         lecture_payment_type_id, units, rate_used, amount, duration, payment_note,
         earning_status_earning_status_id, added_by, added_at)
    VALUES (
        " . Database::escape($lecturerRow['users_user_id']) . ",
        " . Database::escape($contractId) . ",
        " . Database::escape($lecturerRow['subjects_subject_id']) . ",
        " . Database::escape($payTypeId) . ",
        " . Database::escape($units) . ",
        " . Database::escape($lecturerRow['lecture_rate']) . ",
        " . Database::escape($manualAmount) . ",
        $durationSql,
        $noteSql,
        " . Database::escape($earningStatusId) . ",
        " . Database::escape($addedBy) . ", NOW()
    )
");

if (Database::$connection->errno) {
    $err = Database::$connection->error;
    Database::iud("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Failed to save payment: ' . $err]);
    exit;
}

$paymentId = Database::$connection->insert_id;

foreach ($timeTableIds as $ttId) {
    Database::iud("
        INSERT INTO lecturer_payments_has_lectures
            (lecturer_payments_lecturer_payments_id, time_table_time_table_id)
        VALUES (" . Database::escape($paymentId) . ", " . Database::escape($ttId) . ")
    ");
    if (Database::$connection->errno) {
        $err = Database::$connection->error;
        Database::iud("ROLLBACK");
        echo json_encode(['success' => false, 'message' => "Lecture #$ttId could not be linked: $err"]);
        exit;
    }
}

$logLabel = $earningStatusId == 2 ? 'Generated & Approved' : 'Generated & Rejected';
$logNoteSql = "'" . Database::escape($note !== '' ? $note : $logLabel) . "'";

Database::iud("
    INSERT INTO lecturer_payments_logs
        (note, log, date_updated, lecturer_payments_lecturer_payments_id, users_user_id)
    VALUES (
        $logNoteSql,
        '" . Database::escape($logLabel) . "',
        NOW(),
        " . Database::escape($paymentId) . ",
        " . Database::escape($addedBy) . "
    )
");

if (Database::$connection->errno) {
    $err = Database::$connection->error;
    Database::iud("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Failed to write log: ' . $err]);
    exit;
}

Database::iud("COMMIT");

echo json_encode([
    'success' => true,
    'lecturer_payments_id' => $paymentId,
    'amount' => $manualAmount,
    'earning_status_id' => $earningStatusId,
]);
