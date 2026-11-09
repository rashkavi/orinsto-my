<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if (SessionUser::ifUserIsPro($sessionUserRole)) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $id = $decoded['id'];

                    $validLead = Database::search("SELECT * FROM `leads` WHERE `lead_id`='" . $id . "'");
                    if ($validLead->num_rows == 0) {
                        http_response_code(400);
                        $status->error = 'Invalid Lead ID';
                        exit();
                    } else {

                        $leadLogs = Database::search("SELECT * FROM `lead_logs` WHERE `leads_id`='" . $id . "'");
                        if ($leadLogs->num_rows > 0) {
                            Database::iud("DELETE FROM `lead_logs` WHERE `leads_id`='" . $id . "'");
                        }

                        Database::iud("DELETE FROM `leads` WHERE `lead_id` = '" . $id . "'");
                        $status->done = 'Lead Deleted Successfully!';
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
