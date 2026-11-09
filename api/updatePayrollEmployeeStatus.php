<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $payrollEmployeeId = isset($decoded['payrollEmployeeId']) ? $decoded['payrollEmployeeId'] : 0;
                    $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if ($payrollEmployeeId == 0) {
                        $status->error = "Invalid Payroll Employee Id";
                    } else {
                        $isValidPaymentId = Database::search("SELECT * FROM `payroll_run_employees` WHERE `payroll_run_employee_id` = '" . $payrollEmployeeId . "'
                        ");

                        if ($isValidPaymentId->num_rows == 0) {
                            $status->error = "Invalid payment id";
                        } else {
                            $payrollEmployeeData = $isValidPaymentId->fetch_assoc();

                            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("payroll", 27) || UserPermissions::hasPermission("payroll", 28))) {
                                $status->error = "You are not authorized to change employee payroll status";
                            } else {
                                if (empty($paymentStatus)) {
                                    $status->status = "Please select employee paysheet status";
                                } else if (
                                    $paymentStatus == "VERIFIED" &&
                                    !(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("payroll", 27))
                                ) {
                                    $status->error = "You are not authorized to verify employee paysheet";
                                } else if (
                                    $paymentStatus == "APPROVED" &&
                                    !(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("payroll", 28))
                                ) {
                                    $status->error = "You are not authorized to approve employee paysheet";
                                }

                                if (($paymentStatus == "HOLD" || $paymentStatus == "CANCELLED") && empty($note)) {
                                    $status->note = "Note is required for " . $paymentStatus;
                                }

                                $requiredProperties = [
                                    'status',
                                    'note'
                                ];
                                $allPropertiesExist = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allPropertiesExist = false;
                                        break; // Exit loop on the first missing property
                                    }
                                }

                                if ($allPropertiesExist) {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $now = $dateTime->format('Y-m-d H:i:s');

                                    Database::iud("UPDATE `payroll_run_employees` SET `employee_payroll_status`='" . $paymentStatus . "' WHERE `payroll_run_employee_id`='" . $payrollEmployeeId . "'");

                                    $log = "Paysheet status changed " . $payrollEmployeeData['employee_payroll_status'] . " → " . $paymentStatus;

                                    Database::iud("INSERT INTO `payroll_run_employees_logs` 
                                    (`payroll_run_employees_payroll_run_employee_id`, `employee_payroll_status`, `note`, `users_user_id`, `date_updated`,`log`) 
                                    VALUES ('" . $payrollEmployeeId . "', '" . $paymentStatus . "', '" . Database::escape($note) . "', '" . $sessionUserObject['user_id'] . "', '" . $now . "', '" . Database::escape($log) . "')");

                                    $status->done = "Employee paysheet status updated successfully";
                                }
                            }
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
