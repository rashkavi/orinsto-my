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

                    $targetId = isset($decoded["target_id"]) ? (int)$decoded["target_id"] : 0;
                    $user = isset($decoded["user"]) ? trim($decoded["user"]) : "";
                    $type = isset($decoded["type"]) ? trim($decoded["type"]) : "";
                    $period = isset($decoded["period"]) ? trim($decoded["period"]) : "";
                    $intake = isset($decoded["intake"]) ? trim($decoded["intake"]) : "";
                    $start = isset($decoded["start"]) ? trim($decoded["start"]) : "";
                    $end = isset($decoded["end"]) ? trim($decoded["end"]) : "";
                    $value = isset($decoded["value"]) ? trim($decoded["value"]) : "";

                    if ($targetId <= 0) {
                        $status->error = "Invalid target";
                    }

                    if (empty($user)) {
                        $status->error = "Invalid user Id";
                    }

                    if (empty($type) || $type == 0) {
                        $status->type = "Please select a target type";
                    }

                    if (empty($period) || $period == 0) {
                        $status->period = "Please select a period";
                    }

                    if ($period == 3 && (empty($intake) || $intake == 0)) {
                        $status->intake = "Please select an intake";
                    }

                    if (empty($start)) {
                        $status->start = "Please select a start date";
                    }

                    if (empty($end)) {
                        $status->end = "Please select an end date";
                    }

                    if (!empty($start) && !empty($end) && strtotime($start) > strtotime($end)) {
                        $status->end = "End date must be greater than or equal to start date";
                    }

                    if (empty($value) || $value <= 0) {
                        $status->value = "Please enter a valid target value";
                    }

                    if (count((array)$status) > 0) {
                        echo json_encode($status);
                        exit;
                    }

                    $isValidTarget = Database::search("
                        SELECT `lead_target_id`
                        FROM `lead_targets`
                        WHERE `lead_target_id` = '" . $targetId . "'
                          AND `counselor_user_id` = '" . Database::escape($user) . "'
                        LIMIT 1
                    ");

                    if ($isValidTarget->num_rows == 0) {
                        $status->error = "Invalid target";
                        echo json_encode($status);
                        exit;
                    }

                    $intakeCondition = "";

                    if ($period == 3) {
                        $intakeCondition = "AND `intakes_intake_id` = '" . Database::escape($intake) . "'";
                    } else {
                        $intakeCondition = "AND (`intakes_intake_id` IS NULL OR `intakes_intake_id` = '0')";
                    }

                    $duplicateTarget = Database::search("
                        SELECT `lead_target_id`
                        FROM `lead_targets`
                        WHERE `lead_target_id` != '" . $targetId . "'
                          AND `counselor_user_id` = '" . Database::escape($user) . "'
                          AND `lead_target_type_lead_target_type_id` = '" . Database::escape($type) . "'
                          AND `lead_target_period_lead_target_period_id` = '" . Database::escape($period) . "'
                          $intakeCondition
                          AND (
                                `start_date` <= '" . Database::escape($end) . "'
                                AND `end_date` >= '" . Database::escape($start) . "'
                              )
                        LIMIT 1
                    ");

                    if ($duplicateTarget->num_rows > 0) {
                        $status->error = "A target already exists for this user, type, period and selected date range";
                        echo json_encode($status);
                        exit;
                    }

                    $intakeSql = "NULL";
                    if ($period == 3) {
                        $intakeSql = "'" . Database::escape($intake) . "'";
                    }

                    Database::iud("
                        UPDATE `lead_targets`
                        SET 
                            `target` = '" . Database::escape($value) . "',
                            `lead_target_period_lead_target_period_id` = '" . Database::escape($period) . "',
                            `lead_target_type_lead_target_type_id` = '" . Database::escape($type) . "',
                            `start_date` = '" . Database::escape($start) . "',
                            `end_date` = '" . Database::escape($end) . "',
                            `intakes_intake_id` = $intakeSql
                        WHERE `lead_target_id` = '" . $targetId . "'
                          AND `counselor_user_id` = '" . Database::escape($user) . "'
                    ");

                    $status->done = "Lead Target Updated Successfully!";
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
