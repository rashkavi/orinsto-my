<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('students', 0))) {
            $status->error = "You are not authorized to this action.";
        } else {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                    $content = trim(file_get_contents('php://input'));
                    $decoded = json_decode($content, true);
                    if (json_last_error() == JSON_ERROR_NONE) {

                        $id = isset($decoded['id']) ? $decoded['id'] : 0;
                        $date = isset($decoded['date']) ? $decoded['date'] : 0;
                        $note = isset($decoded['note']) ? $decoded['note'] : '';

                        if (empty($id)) {
                            $status->error = 'Invalid student id';
                        } else {
                            $isValidId = Database::search("SELECT * FROM `students_has_intakes`
                                INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id` 
                                WHERE `students_has_intakes_id`='" . $id . "'");

                            if ($isValidId->num_rows == 0) {
                                $status->error = 'Invalid student id';
                            } else {
                                $studentIntakeData = $isValidId->fetch_assoc();

                                if (empty($date)) {
                                    $status->date = "Please select a date";
                                } else if (!CheckDateTime::isFutureDate($date)) {
                                    $status->date = "Date should be in the future";
                                } else {
                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $dateTime = new DateTime("now", $timezone);
                                    $addTime = $dateTime->format('Y-m-d H:i:s');

                                    Database::iud("UPDATE `students_has_intakes` SET `inactivate_date` ='" . $date . "' WHERE `students_has_intakes_id`='" . $id . "'");

                                    $log = "Deactivate date changed " . $studentIntakeData['inactivate_date'] . " → " . $date;

                                    $columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`,`log`";
                                    $values = "'" . $id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','" . $log . "'";

                                    if (!empty($note)) {
                                        $columns .= ",`note`";
                                        $values .= ",'" . Database::escape($note) . "'";
                                    }

                                    Database::iud("INSERT INTO `students_has_intakes_logs`(" . $columns . ") VALUES(" . $values . ")");

                                    $status->done = "Student deactive date changed successfully";
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
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
