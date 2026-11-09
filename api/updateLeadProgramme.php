<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/connection.php");
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
                    $programme  = $decoded['programme']   ?? null;

                    if (empty($leadId)) {
                        http_response_code(400);
                        $status->error = 'Invalid lead id';
                    } else {
                        $isValidLead = Database::search("SELECT * FROM `leads` WHERE `lead_id`='" . $leadId . "'");
                        if ($isValidLead->num_rows == 0) {
                            $status->error = "Invalid lead Id";
                        } else {
                            $leadData = $isValidLead->fetch_assoc();

                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10))) {
                                $status->error = "You are not authorized to update leads";
                            } else {
                                if ($leadData['programmes_programme_id'] != null) {
                                    $status->error = "Invalid Request";
                                } else {
                                    $isValidProgramme = Database::search("SELECT * FROM `programmes` WHERE `programme_id` = '" . $programme . "'");
                                    if ($isValidProgramme->num_rows == 0) {
                                        $status->programme =  "Programme does not exist";
                                    } else {
                                        $programmeData = $isValidProgramme->fetch_assoc();

                                        $d = new DateTime();
                                        $tz = new DateTimeZone("Asia/Colombo");
                                        $d->setTimezone($tz);
                                        $ndate = $d->format("Y-m-d H:i:s");

                                        Database::iud("UPDATE `leads` SET `programmes_programme_id`='" . $programme . "' WHERE `lead_id`='" . $leadId . "'");

                                        $load_log_insert_columns = "`date_updated`,`leads_id`,`users_user_id`,`note`";
                                        $load_log_insert_values = "'" . $ndate . "','" . $leadId . "','" . $sessionUserObject['user_id'] . "','Update Lead Programme'";

                                        Database::iud("INSERT INTO `lead_logs` (" . $load_log_insert_columns . ") 
                                    VALUES (" . $load_log_insert_values . ")");

                                        $status->done = "Programme updated successfully";
                                    }
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
