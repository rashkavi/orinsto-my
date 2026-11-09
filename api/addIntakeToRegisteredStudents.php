<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/classes/StudentIdGenerator.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $studentId = isset($decoded['studentId']) ? $decoded['studentId'] : 0;
                    $intakeId = isset($decoded['intakeId']) ? $decoded['intakeId'] : 0;
                    $intakeStudentId = isset($decoded['intakeStudentId']) ? $decoded['intakeStudentId'] : 0;
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $intakeId))) {
                        $status->error = 'You do not have permission to add student to this intake';
                    } else {
                        if ($studentId == 0) {
                            $status->error = 'Invalid student id';
                        } else {
                            require_once '../includes/connection.php';
                            $isValidStudent = Database::search("SELECT * FROM `students` 
                         INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                        WHERE `student_id`='" . $studentId . "' AND `student_id` NOT IN (SELECT `students_student_id` FROM `students_has_intakes` WHERE `intakes_intake_id`='" . $intakeId . "')");

                            if ($isValidStudent->num_rows == 0) {
                                $status->error = 'Invalid student id';
                            } else {
                                $studentData = $isValidStudent->fetch_assoc();
                                $isValidIntake = Database::search("SELECT * FROM `intakes` 
                                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
                                WHERE `intake_id`='" . $intakeId . "'");

                                if ($isValidIntake->num_rows == 0) {
                                    $status->error = 'Invalid intake id';
                                } else {

                                    $intakeData = $isValidIntake->fetch_assoc();
                                    if (empty($intakeData['intake_prefix'])) {
                                        $status->error = "Intake letter is not defined for the selected intake. It is required for Student ID generation. Please configure it and continue.";
                                    } else {
                                        $timezone = new DateTimeZone("Asia/Colombo");
                                        $dateTime = new DateTime("now", $timezone);
                                        $addTime = $dateTime->format('Y-m-d H:i:s');

                                        $intakeStudentId = StudentIdGenerator::generateByIntakeId($intakeId);

                                        $insert_columns = "`students_student_id`,`intakes_intake_id`,`student_id`,`addmission_date`,`status_status_id`,`users_user_id`";
                                        $insert_values = "'" . $studentId . "','" . $intakeId . "','" . $intakeStudentId . "','" . $addTime . "','1','" . $sessionUserObject['user_id'] . "'";

                                        Database::iud("INSERT INTO `students_has_intakes` (" . $insert_columns . ") VALUES (" . $insert_values . ")");
                                        $studentHasIntakeId = Database::$connection->insert_id;

                                        $intakePaymentPlan = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intakes_intake_id`='" . $intakeId . "' AND `is_default_plan`='1'");
                                        if ($intakePaymentPlan->num_rows > 0) {
                                            $paymentPlanData = $intakePaymentPlan->fetch_assoc();

                                            $studentPlanPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` 
                                                INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id`=`payment_type`.`payment_type_id`
                                                WHERE `intake_has_payment_plan_id`='" . $paymentPlanData['intake_has_payment_plan_id'] . "'
                                                ");

                                            $paymentPlanPaymentsPenalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` IN (SELECT `intake_payments_plan_has_payment_id` FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id` = '" . $paymentPlanData['intake_has_payment_plan_id'] . "')");

                                            if ($studentPlanPayments->num_rows > 0) {
                                                $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`,  `discount_type_discount_type_id`, `amount`, `discount`, `date_added`,`intake_has_payment_plan_id`,`payment_plan_payment_plan_id`,`payment_category_payment_category_id`";
                                                $payment_plan_insert_values = "'" . $studentHasIntakeId . "', '" . $paymentPlanData['discount_type_discount_type_id'] . "', '" . $paymentPlanData['amount'] . "', '" . $paymentPlanData['discount'] . "', '" . $addTime . "', '" . $paymentPlanData['intake_has_payment_plan_id'] . "','" . $paymentPlanData['payment_plan_payment_plan_id'] . "','" . $paymentPlanData['payment_category_payment_category_id'] . "'";

                                                if (!empty($paymentPlanData['discount_note'])) {
                                                    $payment_plan_insert_columns .= ", `discount_note`";
                                                    $payment_plan_insert_values .= ", '" . $paymentPlanData['discount_note'] . "'";
                                                }

                                                Database::iud("INSERT INTO `payment_plan_has_students_has_intakes`(" . $payment_plan_insert_columns . ") VALUES(" . $payment_plan_insert_values . ")");
                                                $planId = Database::$connection->insert_id;

                                                if ($studentPlanPayments->num_rows > 0) {
                                                    while ($payment = $studentPlanPayments->fetch_assoc()) {
                                                        $insert_col = "`payment_status_payment_status_id`,`payment_type_payment_type_id`,`refference`,`discount`,
                                                           `amount`,`discount_type_discount_type_id`,`discount_note`,`payment_plan_has_students_has_intakes_id`,
                                                           `panelty_payment_option_panelty_payment_option_id`,`intake_payments_plan_has_payment_id`";
                                                        $insert_val = "1,
                                                           '" . $payment['payment_type_payment_type_id'] . "',
                                                           '" . generateSecureUniqueId() . "',
                                                           '" . $payment['discount'] . "',
                                                           '" . $payment['amount'] . "',
                                                           '" . $payment['discount_type_discount_type_id'] . "',
                                                           '" . $payment['discount_note'] . "',
                                                           '" . $planId . "',
                                                           '" . $payment['panelty_payment_option_panelty_payment_option_id'] . "',
                                                           '" . $payment['intake_payments_plan_has_payment_id'] . "'";

                                                        if (!empty($payment['due_date'])) {
                                                            $insert_col .= ", `due_date`";
                                                            $insert_val .= ", '" . $payment['due_date'] . "'";
                                                        }

                                                        Database::iud("INSERT INTO `course_payments`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                        $paymentId = Database::$connection->insert_id;

                                                        while ($penality = $paymentPlanPaymentsPenalities->fetch_assoc()) {
                                                            if ($penality['intake_payments_plan_has_payment_id'] == $payment['intake_payments_plan_has_payment_id']) {
                                                                $insert_col = "`course_payments_course_payments_id`,`amount`,`days`,`panelty_payment_type_panelty_payment_type_id`";
                                                                $insert_val = "'" . $paymentId . "', '" . $penality['amount'] . "', '" . $penality['days'] . "', '" . $penality['panelty_payment_type_panelty_payment_type_id'] . "'";
                                                                Database::iud("INSERT INTO `course_payments_penalties`(" . $insert_col . ") VALUES(" . $insert_val . ")");
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $status->done = 'Student added successfully';
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
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
