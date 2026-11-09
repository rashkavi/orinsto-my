<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $request_id = isset($decoded['request_id']) ? $decoded['request_id'] : 0;
                    $request_status = isset($decoded['request_status']) ? $decoded['request_status'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if ($request_id == 0) {
                        $status->error = "Invalid request id";
                    } else {
                        $leaveRequest = Database::search("SELECT * FROM `leave_requests` WHERE `leave_request_id` = '" . $request_id . "'");
                        if ($leaveRequest->num_rows == 0) {
                            $status->error = "Invalid request id";
                        } else {

                            if (empty($request_status)) {
                                $status->status = "Please select request status";
                            }

                            if (empty($note)) {
                                $status->note = "Please enter note";
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
                                $dateTime = $dateTime->format('Y-m-d H:i:s');

                                $update_query = "`leave_request_status_leave_request_status_id`='" . $request_status . "',
                                `review_note`='" . $note . "',`reviewed_by`='" . $sessionUserObject["user_id"] . "',`reviewed_at`='" . $dateTime . "'";

                                Database::iud("UPDATE `leave_requests` SET " . $update_query . " WHERE `leave_request_id` = '" . $request_id . "'");
                                $status->done = "Request updated successfully";
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
