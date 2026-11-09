<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/DateTimeClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole   = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();

    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {

                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);

                if (json_last_error() == JSON_ERROR_NONE) {

                    $leadId         = $decoded['lead']            ?? null;
                    $nstatus        = $decoded['status']          ?? null;
                    $note           = $decoded['note']            ?? null;
                    $paymentEnabled = $decoded['payment_enabled'] ?? false;
                    $paymentAmount  = $decoded['payment_amount']  ?? null;

                    // ── Validate lead ID ────────────────────────────────────────
                    if (empty($leadId)) {
                        http_response_code(400);
                        $status->error = 'Invalid lead id';
                    } else {
                        $isValidLead = Database::search("
                            SELECT * FROM `leads`
                            WHERE `lead_id` = '" . Database::escape($leadId) . "'
                            AND (
                                `lead_student_reg_status_lead_student_reg_status_id` IS NULL
                                OR `lead_student_reg_status_lead_student_reg_status_id` = 1
                            )
                        ");

                        if ($isValidLead->num_rows == 0) {
                            $status->error = 'Invalid lead Id';
                        } else {
                            $leadData = $isValidLead->fetch_assoc();

                            // ── Permission check ────────────────────────────────
                            $hasPermissionUpdateLead = true; // replace with real check

                            if (!$hasPermissionUpdateLead) {
                                $status->error = "You don't have permission to update this lead";
                            } else {

                                // ── Validate status ─────────────────────────────
                                if (empty($nstatus)) {
                                    http_response_code(400);
                                    $status->status = 'Please select lead status';
                                }

                                // ── Validate payment amount when enabled ─────────
                                if ($paymentEnabled) {
                                    if ($paymentAmount === null || $paymentAmount === '') {
                                        $status->payment_amount = 'Registration amount is required when payment is enabled.';
                                    } elseif (!is_numeric($paymentAmount) || (float)$paymentAmount <= 0) {
                                        $status->payment_amount = 'Registration amount must be a positive number.';
                                    }
                                }

                                // ── Check all fields are clean (no error properties) ─
                                $requiredProperties = ['status', 'cdate', 'ctime', 'note', 'response', 'payment_amount'];

                                $allFieldsPresent = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allFieldsPresent = false;
                                        break;
                                    }
                                }

                                if ($allFieldsPresent) {
                                    $d = new DateTime();
                                    $d->setTimezone(new DateTimeZone("Asia/Colombo"));
                                    $ndate = $d->format("Y-m-d H:i:s");

                                    // ── Update lead status ──────────────────────
                                    Database::iud("
                                        UPDATE `leads`
                                        SET `lead_student_reg_status_lead_student_reg_status_id` = '" . Database::escape($nstatus) . "'
                                        WHERE `lead_id` = '" . Database::escape($leadId) . "'
                                    ");

                                    // ── Insert lead log ─────────────────────────
                                    $logColumns = "`date_updated`, `leads_id`, `users_user_id`, `lead_student_reg_status_lead_student_reg_status_id`";
                                    $logValues  = "'" . $ndate . "', '" . Database::escape($leadId) . "', '" . Database::escape($sessionUserObject['user_id']) . "', '" . Database::escape($nstatus) . "'";

                                    if (!empty($note)) {
                                        $logColumns .= ", `note`";
                                        $logValues  .= ", '" . Database::escape($note) . "'";
                                    } else {
                                        $logColumns .= ", `note`";
                                        $logValues  .= ", 'Send Registration Link'";
                                    }

                                    Database::iud("INSERT INTO `lead_logs` ($logColumns) VALUES ($logValues)");

                                    // ── Save registration payment if enabled ────
                                    if ($paymentEnabled && !empty($paymentAmount)) {
                                        $cleanAmount = number_format((float)$paymentAmount, 2, '.', '');

                                        // Update the leads table with payment details.
                                        // Assumes columns: reg_payment_enabled (TINYINT), reg_payment_amount (DECIMAL)
                                        // Add these columns if they don't exist:
                                        //   ALTER TABLE `leads`
                                        //     ADD COLUMN `reg_payment_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                                        //     ADD COLUMN `reg_payment_amount`  DECIMAL(10,2) NULL DEFAULT NULL;
                                        Database::iud("
                                            UPDATE `leads`
                                            SET
                                                `reg_payment_enabled` = 1,
                                                `reg_payment_amount`  = '" . Database::escape($cleanAmount) . "'
                                            WHERE `lead_id` = '" . Database::escape($leadId) . "'
                                        ");
                                    } else {
                                        // Explicitly store that payment was not required
                                        Database::iud("
                                            UPDATE `leads`
                                            SET
                                                `reg_payment_enabled` = 0,
                                                `reg_payment_amount`  = NULL
                                            WHERE `lead_id` = '" . Database::escape($leadId) . "'
                                        ");
                                    }

                                    $status->done = 'Lead updated successfully';
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

echo json_encode($status);
