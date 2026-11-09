<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
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

                    $user = isset($decoded['user']) ? trim($decoded['user']) : '';
                    $type = isset($decoded['type']) ? trim($decoded['type']) : '';
                    $period = isset($decoded['period']) ? trim($decoded['period']) : '';
                    $intake = isset($decoded['intake']) ? trim($decoded['intake']) : '';
                    $start = isset($decoded['start']) ? trim($decoded['start']) : '';
                    $end = isset($decoded['end']) ? trim($decoded['end']) : '';
                    $value = isset($decoded['value']) ? trim($decoded['value']) : '';

                    if (empty($user)) {
                        $status->error = "Invalid user Id";
                    } else {
                        $isValidUser = Database::search("SELECT * FROM `users` WHERE `user_id`='" . $user . "' AND `status_status_id`='1'");
                        if ($isValidUser->num_rows == 0) {
                            $status->error = "Invalid user Id";
                        } else {

                            if (empty($type)) {
                                $status->type = "Please select a target type";
                            }

                            if (empty($period)) {
                                $status->period = "Please select a period";
                            }

                            if ($period == 3 && empty($intake)) {
                                $status->intake = "Please select a intake";
                            }

                            if (empty($start)) {
                                $status->start = "Please select a start date";
                            }

                            if (empty($end)) {
                                $status->end = "Please select a end date";
                            }

                            if (empty($value)) {
                                $status->value = "Please select a value";
                            }


                            $requiredProperties = [
                                'type',
                                'period',
                                'intake',
                                'start',
                                'end',
                                'value'
                            ];

                            $allFieldsPresent = true;
                            foreach ($requiredProperties as $property) {
                                if (property_exists($status, $property)) {
                                    $allFieldsPresent = false;
                                    break;
                                }
                            }

                            if ($allFieldsPresent) {

                                if (strtotime($start) > strtotime($end)) {
                                    $status->end = "End date must be greater than or equal to start date";
                                    echo json_encode($status);
                                    exit;
                                }

                                $intakeCondition = "";

                                if ($period == 3) {
                                    $intakeCondition = " 
                                        AND `intakes_intake_id` = '" . Database::escape($intake) . "' 
                                    ";
                                } else {
                                    $intakeCondition = " 
                                        AND (`intakes_intake_id` IS NULL OR `intakes_intake_id` = '0') 
                                    ";
                                }

                                $duplicateTarget = Database::search("
                                    SELECT `lead_target_id`
                                    FROM `lead_targets`
                                    WHERE `counselor_user_id` = '" . Database::escape($user) . "'
                                      AND `lead_target_type_lead_target_type_id` = '" . Database::escape($type) . "'
                                      AND `lead_target_period_lead_target_period_id` = '" . Database::escape($period) . "'
                                      $intakeCondition
                                      AND (
                                            (`start_date` <= '" . Database::escape($end) . "'
                                             AND `end_date` >= '" . Database::escape($start) . "')
                                          )
                                    LIMIT 1
                                ");

                                if ($duplicateTarget->num_rows > 0) {
                                    $status->error = "A target already exists for this user, type, period and selected date range";
                                    echo json_encode($status);
                                    exit;
                                }

                                $d = new DateTime();
                                $tz = new DateTimeZone("Asia/Colombo");
                                $d->setTimezone($tz);
                                $date = $d->format("Y-m-d H:i:s");

                                $columns = "`target`,`date_added`,`users_user_id`,`counselor_user_id`,
                                            `lead_target_period_lead_target_period_id`,
                                            `lead_target_type_lead_target_type_id`,
                                            `start_date`,`end_date`";

                                $values = "'" . Database::escape($value) . "',
                                           '" . $date . "',
                                           '" . $sessionUserObject['user_id'] . "',
                                           '" . Database::escape($user) . "',
                                           '" . Database::escape($period) . "',
                                           '" . Database::escape($type) . "',
                                           '" . Database::escape($start) . "',
                                           '" . Database::escape($end) . "'";

                                if ($period == 3) {
                                    $columns .= ",`intakes_intake_id`";
                                    $values .= ",'" . Database::escape($intake) . "'";
                                }

                                Database::iud("INSERT INTO `lead_targets` (" . $columns . ") VALUES (" . $values . ")");

                                $status->done = 'Lead Target Added Successfully!';
                            }
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid request method';
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
