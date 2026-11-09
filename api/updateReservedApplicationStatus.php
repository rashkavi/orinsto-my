<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/classes/StudentIdGenerator.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $inquiryId = isset($decoded['id']) ? $decoded['id'] : 0;
                    $inquiryStatus = isset($decoded['updateStatus']) ? $decoded['updateStatus'] : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';
                    $intake = isset($decoded['intake']) ? $decoded['intake'] : '';

                    if ($inquiryId == 0) {
                        $status->error = "Invalid inquiry id";
                    } else {
                        $isValidInquiryId = Database::search(" SELECT *,`login_details`.`email` AS `s_email`,
                        `students_has_intakes`.`student_id` AS `intakeStudentId`,`programmes`.`programme` FROM `students_has_intakes` 
            INNER JOIN `students` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
            INNER JOIN `leads` ON `students_has_intakes`.`leads_lead_id` = `leads`.`lead_id`
            INNER JOIN `programmes` ON `leads`.`programmes_programme_id` = `programmes`.`programme_id`
            LEFT JOIN `users` ON `students_has_intakes`.`assigned_user_id` = `users`.`user_id`
            LEFT JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
            WHERE `leads_lead_id` = '" .  $inquiryId . "'");

                        if ($isValidInquiryId->num_rows == 0) {
                            $status->error = "Invalid inquiry id";
                        } else {
                            $studentData = $isValidInquiryId->fetch_assoc();

                            if (empty($inquiryStatus)) {
                                $status->status = "Please select status";
                            }

                            if (!empty($note) && strlen($note) > 100) {
                                $status->note = "Note is too long";
                            }

                            if ($inquiryStatus == 3 && empty($note)) {
                                $status->note = "Please provide a reason for rejecting the application";
                            }

                            if (!empty($intake)) {
                                $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id` = '" . $intake . "'");
                                if ($isValidIntake->num_rows == 0) {
                                    $status->intake =  "Intake does not exist";
                                } else {
                                    $intakeData = $isValidIntake->fetch_assoc();
                                    if (empty($intakeData['intake_prefix'])) {
                                        $status->intake = "Intake prefix is not defined for the selected intake. It is required for Student ID generation. Please configure it and continue.";
                                    }
                                }
                            }

                            if (empty(get_object_vars($status))) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');

                                if ($inquiryStatus == 4) {

                                    $load_log_insert_columns = "`date_updated`,`leads_id`,`users_user_id`,`lead_student_reg_status_lead_student_reg_status_id`,`lead_status_lead_status_id`,`note`";
                                    $load_log_insert_values = "'" . $date . "','" . $inquiryId . "','" . $sessionUserObject['user_id'] . "','4','6','" . Database::escape($note) . "'";

                                    Database::iud("INSERT INTO `lead_logs` (" . $load_log_insert_columns . ") 
                                            VALUES (" . $load_log_insert_values . ")");

                                    Database::iud("UPDATE `leads` SET `lead_status_lead_status_id`='6',`lead_student_reg_status_lead_student_reg_status_id`='4' WHERE `lead_id`='" . $inquiryId . "'");

                                    Database::iud("UPDATE `login_details` SET `status_status_id`='1' WHERE `login_details_id`='" . $studentData['login_details_id'] . "'");

                                    $userName = $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'];
                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::APPLICATION_APPROVED,
                                        [
                                            'student_name' => $userName
                                        ]
                                    );

                                    try {
                                        sendMail($studentData['s_email'], $userName, $mail['subject'], $mail['body']);
                                        $status->done = "Registration application updated successfully";
                                    } catch (Exception $e) {
                                        $status->done = "Registration application updated successfully.but email could not be sent";
                                    }


                                    if (!empty($intake)) {
                                        $intakeStudentId = StudentIdGenerator::generateByIntakeId($intake);

                                        $bytes = random_bytes(75);
                                        $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                                        Database::iud("UPDATE `login_details` SET `verification`='" . $token . "',`status_status_id`='1' WHERE `login_details_id`='" . $studentData['login_details_id'] . "'");

                                        Database::iud("UPDATE `students_has_intakes` SET `intakes_intake_id`='" . $intake . "',`student_id`='" . Database::escape($intakeStudentId) . "' WHERE `leads_lead_id`='" . $inquiryId . "'");

                                        $mail = EmailNotificationService::build(
                                            EmailTemplateContent::WELCOME_STUDENT,
                                            [
                                                'action_url' => Project::getBasePortalUrl() . "/setup-password?token=" . $token,
                                                'program' => $studentData['programme'],
                                                'intake' => $intakeData['intake'],
                                                'student_id' => $intakeStudentId,
                                                'student_name' => $userName
                                            ]
                                        );

                                        try {

                                            sendMail($studentData['s_email'], $userName, $mail['subject'], $mail['body']);
                                            $status->done = "Registration application updated successfully";
                                        } catch (Exception $e) {
                                            $status->done = "Registration application updated successfully.but email could not be sent";
                                        }
                                    }
                                } else {
                                    $load_log_insert_columns = "`date_updated`,`leads_id`,`users_user_id`,`lead_student_reg_status_lead_student_reg_status_id`,`note`";
                                    $load_log_insert_values = "'" . $date . "','" . $inquiryId . "','" . $sessionUserObject['user_id'] . "','3','" . Database::escape($note) . "'";

                                    Database::iud("INSERT INTO `lead_logs` (" . $load_log_insert_columns . ") 
                                            VALUES (" . $load_log_insert_values . ")");

                                    Database::iud("UPDATE `leads` SET `lead_student_reg_status_lead_student_reg_status_id`='3' WHERE `lead_id`='" . $inquiryId . "'");

                                    $url = Project::getBasePortalUrl() . "/update-registration-application?id=" . base64_encode($inquiryId);

                                    $userName = $studentData['title'] . "." . $studentData['first_name'] . " " . $studentData['last_name'];
                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::APPLICATION_REJECTED,
                                        [
                                            'student_name' => $userName,
                                            'note' => $note,
                                            'application_update_url' => $url
                                        ]
                                    );

                                    try {
                                        sendMail($studentData['s_email'], $userName, $mail['subject'], $mail['body']);
                                        $status->done = "Registration application updated successfully";
                                    } catch (Exception $e) {
                                        $status->done = "Registration application updated successfully.but email could not be sent";
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
