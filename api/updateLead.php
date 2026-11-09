<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $leadId   = $decoded['lead']     ?? null;
                    $nstatus  = $decoded['status']   ?? null;
                    $cdate    = $decoded['cdate']    ?? null;
                    $ctime    = $decoded['ctime']    ?? null;
                    $note     = $decoded['note']     ?? null;

                    if (empty($leadId)) {
                        http_response_code(400);
                        $status->error = 'Invalid lead id';
                    } else {
                        $isValidLead = Database::search("SELECT * FROM `leads` WHERE `lead_id`='" . $leadId . "'");
                        if ($isValidLead->num_rows == 0) {
                            $status->error = "Invalid lead Id";
                        } else {
                            $leadData = $isValidLead->fetch_assoc();

                            $hasPermissionUpdateLead = true;

                            if (!$hasPermissionUpdateLead) {
                                $status->error = "You don't have permission to update this lead";
                            } else {

                                if (empty($nstatus)) {
                                    http_response_code(400);
                                    $status->status = 'Please select lead status';
                                }

                                if (!empty($date) && !CheckDateTime::isFutureDate($date)) {
                                    $status->cdate = "Date should be in the future";
                                }

                                $requiredProperties = [
                                    'status',
                                    'cdate',
                                    'ctime',
                                    'note',
                                    'response'
                                ];

                                $allFieldsPresent = true;
                                foreach ($requiredProperties as $property) {
                                    if (property_exists($status, $property)) {
                                        $allFieldsPresent = false;
                                        break;
                                    }
                                }

                                if ($allFieldsPresent) {
                                    $d = new DateTime();
                                    $tz = new DateTimeZone("Asia/Colombo");
                                    $d->setTimezone($tz);
                                    $ndate = $d->format("Y-m-d H:i:s");

                                    $lead_update_query = "`lead_status_lead_status_id`='" . $nstatus . "'";

                                    if (!empty($cdate)) {
                                        $lead_update_query .= ",`remind_date`='" . $cdate . "'";
                                    }
                                    if (!empty($ctime)) {
                                        $lead_update_query .= ",`remind_time`='" . $ctime . "'";
                                    }

                                    Database::iud("UPDATE `leads` SET " . $lead_update_query . " WHERE `lead_id`='" . $leadId . "'");

                                    $load_log_insert_columns = "`date_updated`,`leads_id`,`users_user_id`,`lead_status_lead_status_id`";
                                    $load_log_insert_values = "'" . $ndate . "','" . $leadId . "','" . $sessionUserObject['user_id'] . "','" . $nstatus . "'";

                                    if (!empty($note)) {
                                        $load_log_insert_columns .= ", `note`";
                                        $load_log_insert_values .= ", '" . Database::escape($note) . "'";
                                    }

                                    Database::iud("INSERT INTO `lead_logs` (" . $load_log_insert_columns . ") 
                                        VALUES (" . $load_log_insert_values . ")");

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

echo (json_encode($status));
