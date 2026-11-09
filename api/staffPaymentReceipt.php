<?php
/**
 * api/staffPaymentReceipt.php?id=<payroll_run_employee_payment_id>
 * Streams a salary receipt to users allowed to view staff payments.
 * Receipts live outside public access (uploads/.htaccess denies direct URLs).
 */
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/classes/StaffPayments.php");

if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
    http_response_code(401);
    exit('Authentication Failed!');
}
$role = SessionUser::getCurrentUserRole();
$user = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($user['user_permissions']);
if (!StaffPayments::canView($role)) {
    http_response_code(403);
    exit('Access denied');
}

$id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
$payment = $id > 0 ? StaffPayments::getPayment($id) : null;
if (!$payment) {
    http_response_code(404);
    exit('Receipt not found');
}

$path = StaffPayments::receiptDir() . basename($payment['receipt_file']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Receipt file is missing');
}

$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
if (!isset(StaffPayments::ALLOWED_MIME[$mime])) {
    http_response_code(415);
    exit('Unsupported file');
}
$download = isset($_GET['download']);
$name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $payment['receipt_original_name']);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $name . '"');
readfile($path);
