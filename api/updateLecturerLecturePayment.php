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

$paymentId      = (int)($input['lecturer_payments_id'] ?? 0);
$manualDuration = isset($input['duration']) && $input['duration'] !== null && $input['duration'] !== '' ? (float) $input['duration'] : null;
$manualAmount   = isset($input['amount']) ? (float) $input['amount'] : null;
$note           = trim($input['note'] ?? '');
$updatedBy      = (int) $sessionUserObject['user_id'];

if (!$paymentId || $manualAmount === null) {
    echo json_encode(['success' => false, 'message' => 'Missing/invalid parameters']);
    exit;
}

// Only editable while still Pending — once Approved/Rejected this endpoint won't touch it.
$existing = Database::search("
    SELECT lecturer_payments_id, earning_status_earning_status_id, lecture_payment_type_id
    FROM lecturer_payments
    WHERE lecturer_payments_id = " . Database::escape($paymentId) . "
    LIMIT 1
");
if ($existing->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Payment not found']);
    exit;
}
$existingRow = $existing->fetch_assoc();
if ((int) $existingRow['earning_status_earning_status_id'] !== 1) {
    echo json_encode(['success' => false, 'message' => 'This payment can no longer be edited']);
    exit;
}

$payTypeId = (int) $existingRow['lecture_payment_type_id'];
if ($payTypeId == 1 && ($manualDuration === null || $manualDuration <= 0)) {
    echo json_encode(['success' => false, 'message' => 'Paid hours is required for Per Hour payment type']);
    exit;
}

$units = $payTypeId == 1 ? round($manualDuration, 2) : 1;
$durationSql = ($payTypeId == 1) ? Database::escape(round($manualDuration, 2)) : "NULL";
$noteSql = ($note === '') ? "NULL" : "'" . Database::escape($note) . "'";

Database::iud("START TRANSACTION");

Database::iud("
    UPDATE lecturer_payments
    SET units = " . Database::escape($units) . ",
        amount = " . Database::escape($manualAmount) . ",
        duration = $durationSql,
        payment_note = $noteSql
    WHERE lecturer_payments_id = " . Database::escape($paymentId) . "
");

if (Database::$connection->errno) {
    $err = Database::$connection->error;
    Database::iud("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Failed to update payment: ' . $err]);
    exit;
}

Database::iud("
    INSERT INTO lecturer_payments_logs
        (note, log, date_updated, lecturer_payments_lecturer_payments_id, users_user_id)
    VALUES (
        " . ($note !== '' ? "'" . Database::escape($note) . "'" : "'Updated pending payment'") . ",
        'Updated',
        NOW(),
        " . Database::escape($paymentId) . ",
        " . Database::escape($updatedBy) . "
    )
");

if (Database::$connection->errno) {
    $err = Database::$connection->error;
    Database::iud("ROLLBACK");
    echo json_encode(['success' => false, 'message' => 'Failed to write log: ' . $err]);
    exit;
}

Database::iud("COMMIT");

echo json_encode(['success' => true, 'lecturer_payments_id' => $paymentId, 'amount' => $manualAmount]);