<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
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
                        $user = isset($decoded['user']) ? $decoded['user'] : 0;
                        $note = isset($decoded['note']) ? $decoded['note'] : '';

                        if (empty($id)) {
                            $status->error = 'Invalid student id';
                        } else {
                            $isValidId = Database::search("SELECT * FROM `students_has_intakes`
                                INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id`=`intakes`.`intake_id` 
                                LEFT JOIN `users` ON `students_has_intakes`.`assigned_user_id` = `users`.`user_id`
                                LEFT JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                              WHERE `students_has_intakes_id`='" . $id . "'");

                            if ($isValidId->num_rows == 0) {
                                $status->error = 'Invalid student id';
                            } else {
                                $studentIntakeData = $isValidId->fetch_assoc();

                                if (empty($user)) {
                                    $status->user = "Please select a user";
                                } else {
                                    $isValidUser = Database::search("SELECT * FROM `users`
                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                WHERE `user_roles_user_role_id` NOT IN(1,2,8) AND `status_status_id`='1'
                                AND `user_id` != '" . $studentIntakeData['user_id'] . "' AND `users`.`user_id`='" . $user . "'");

                                    if ($isValidUser->num_rows == 0) {
                                        $status->user = "Please select a valid user";
                                    } else {
                                        $userData = $isValidUser->fetch_assoc();
                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $addTime = $dateTime->format('Y-m-d H:i:s');

                                        Database::iud("UPDATE `students_has_intakes` SET `assigned_user_id` ='" . $user . "' WHERE `students_has_intakes_id`='" . $id . "'");

                                        if (!empty($studentIntakeData['assigned_user_id'])) {
                                            $log = "Reassigned user : " . $studentIntakeData['title'] . "." . $studentIntakeData['user_fname'] . " " . $studentIntakeData['user_lname'] . " → " . $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname'] . "";
                                        } else {
                                            $log = "Assigned user : " . $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname'] . "";
                                        }

                                        $columns = "`students_has_intakes_students_has_intakes_id`,`users_user_id`,`date_updated`,`log`";
                                        $values = "'" . $id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','" . Database::escape($log) . "'";

                                        if (!empty($note)) {
                                            $columns .= ",`note`";
                                            $values .= ",'" . Database::escape($note) . "'";
                                        }

                                        Database::iud("INSERT INTO `students_has_intakes_logs`(" . $columns . ") VALUES(" . $values . ")");

                                        $status->done = "Intake student assigned user changed successfully";
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
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
