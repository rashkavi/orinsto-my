<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once '../includes/UserClass.php';
require_once '../includes/connection.php';
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $user = $decoded['user'];

                    if (empty($user) || $user == 0 || !is_numeric($user)) {
                        $status->error = 'Invalid user Id';
                    } else {
                        $isValidUser = Database::search("SELECT * FROM `users` 
                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                        WHERE `user_id` = '" . $user . "'");
                        if ($isValidUser->num_rows == 0) {
                            $status->error = 'Invalid User Id';
                        } else {
                            $userData = $isValidUser->fetch_assoc();

                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $now = $dateTime->format('Y-m-d H:i:s');

                            $fullname = $userData['user_fname'] . " " . $userData['user_lname'];
                            $email = $userData['user_email'];

                            $bytes = random_bytes(75);
                            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                            if ($userData['password_setup_status'] == "SETUP") {
                                Database::iud("UPDATE `users` SET `user_verification` = '" . $token . "',`user_reset_time`='" . $now . "' WHERE `user_id` = '" . $user . "'");

                                $userName = $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname'];
                                $mail = EmailNotificationService::build(
                                    EmailTemplateContent::PASSWORD_RESET,
                                    [
                                        'action_url' => Project::getBaseUrl() . "/forgot-password?token=" . $token
                                    ]
                                );
                                try {
                                    sendMail($userData['user_email'], $userName, $mail['subject'], $mail['body']);
                                    $status->done = "Password Reset Email Sent Successfully";
                                } catch (Exception $e) {
                                    $status->error = "Unable to send the password reset email. Please check the email address and try again.";
                                }
                            } else {
                                Database::iud("UPDATE `users` SET `user_verification` = '" . $token . "',`password_setup_status`='SEND' WHERE `user_id` = '" . $user . "'");

                                $userName = $userData['title'] . "." . $userData['user_fname'] . " " . $userData['user_lname'];
                                $mail = EmailNotificationService::build(
                                    EmailTemplateContent::PASSWORD_SETUP,
                                    [
                                        'action_url' => Project::getBaseUrl() . "/setup-password?token=" . $token
                                    ]
                                );
                                try {
                                    sendMail($userData['user_email'], $userName, $mail['subject'], $mail['body']);
                                    $status->done = "Registration Email Sent Successfully";
                                } catch (Exception $e) {
                                    $status->error = "Unable to send the registration email. Please check the email address and try again.";
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
