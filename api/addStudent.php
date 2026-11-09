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
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
                $status->error = 'You are not authorized to add student';
            } else {
                $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
                $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
                $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
                $nwi = isset($_POST['nwi']) ? trim($_POST['nwi']) : '';
                $title = isset($_POST['title']) ? trim($_POST['title']) : 0;
                $gender = isset($_POST['gender']) ? trim($_POST['gender']) : 0;
                $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : 0;
                $nationality = isset($_POST['nationality']) ? trim($_POST['nationality']) : '';
                $nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';
                $passport = isset($_POST['passport']) ? trim($_POST['passport']) : '';
                $cob = isset($_POST['cob']) ? trim($_POST['cob']) : '';
                $cor = isset($_POST['cor']) ? trim($_POST['cor']) : '';
                $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
                $whatsapp = isset($_POST['whatsapp']) ? trim($_POST['whatsapp']) : '';
                $address = isset($_POST['address']) ? trim($_POST['address']) : '';
                $address2 = isset($_POST['address2']) ? trim($_POST['address2']) : '';
                $city = isset($_POST['city']) ? trim($_POST['city']) : '';
                $pcode = isset($_POST['pcode']) ? trim($_POST['pcode']) : '';
                $faculty = isset($_POST['faculty']) ? trim($_POST['faculty']) : 0;
                $programme = isset($_POST['programme']) ? trim($_POST['programme']) : 0;
                $intake = isset($_POST['intake']) ? trim($_POST['intake']) : 0;
                $counselor = isset($_POST['counselor']) ? trim($_POST['counselor']) : 0;
                $gtitle = isset($_POST['gtitle']) ? trim($_POST['gtitle']) : 0;
                $grelationship = isset($_POST['grelationship']) ? trim($_POST['grelationship']) : 0;
                $gfname = isset($_POST['gfname']) ? trim($_POST['gfname']) : '';
                $glname = isset($_POST['glname']) ? trim($_POST['glname']) : '';
                $gemail = isset($_POST['gemail']) ? trim($_POST['gemail']) : '';
                $gmobile = isset($_POST['gmobile']) ? trim($_POST['gmobile']) : '';
                $physicalDocs = isset($_POST['physical_documents']) ? json_decode($_POST['physical_documents'], true) : [];
                $image = '';

                $formattedMobile = normalizePhoneNumber($mobile);
                $formattedWhatsapp = normalizePhoneNumber($whatsapp);

                if (empty($fname)) {
                    $status->fname =  "Please enter student first name";
                } else if (strlen($fname) > 45) {
                    $status->fname =  "First Name should be less than 45 characters";
                }
                if (empty($lname)) {
                    $status->lname =  "Please enter student last name";
                } else if (strlen($lname) > 45) {
                    $status->lname =  "Last Name should be less than 45 characters";
                }
                if (empty($fullname)) {
                    $status->fullname =  "Please enter student full name";
                } else if (strlen($fullname) > 150) {
                    $status->fullname =  "Full Name should be less than 150 characters";
                }
                if (empty($nwi)) {
                    $status->nwi =  "Please enter student name with initials";
                } else if (strlen($nwi) > 45) {
                    $status->nwi =  "Name with initials should be less than 45 characters";
                }
                if ($title == 0) {
                    $status->title =  "Please select student title";
                }
                if ($gender == 0) {
                    $status->gender =  "Please select student gender";
                }

                if (empty($nationality)) {
                    $status->nationality =  "Please enter student nationality";
                }
                if (!empty($nic)) {
                    if (strlen($nic) > 45) {
                        $status->nic =  "NIC should be less than 45 characters";
                    } else if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                        $status->nic =  "Please enter a valid NIC number";
                    } else {
                        $isValidNic = Database::search("SELECT * FROM `students` WHERE `nic` = '" . $nic . "'");
                        if ($isValidNic->num_rows > 0) {
                            $status->nic =  "NIC already exists";
                        }
                    }
                }
                if (!empty($passport)) {
                    if (strlen($passport) > 20) {
                        $status->passport = "Passport number should be less than 20 characters";
                    } else if (!preg_match('/^[A-Z]{1,2}[0-9]{6,8}$/', strtoupper($passport))) {
                        $status->passport = "Please enter a valid passport number";
                    } else {
                        $isValidPassport = Database::search(
                            "SELECT * FROM `students` WHERE `passport` = '" . $passport . "'"
                        );

                        if ($isValidPassport->num_rows > 0) {
                            $status->passport = "Passport number already exists";
                        }
                    }
                }
                if (empty($cob) || $cob == 0) {
                    $status->cob =  "Please select student country of birth";
                }

                if (empty($email)) {
                    $status->email =  "Please enter student email";
                } else if (strlen($email) > 100) {
                    $status->email =  "Email should be less than 100 characters";
                } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $status->test = $email;
                    $status->email =  "Please enter a valid email";
                } else {
                    $isValidEmail = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `email` = '" . $email . "'");
                    $row = $isValidEmail->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->email =  "Email already exists";
                    }
                }
                if (empty($mobile)) {
                    $status->mobile =  "Please enter student mobile";
                } else if (!$formattedMobile) {
                    $status->mobile =  "Please enter a valid contact number";
                } else {
                    $isValidMobile = Database::search("SELECT COUNT(*) as 'count' FROM `students` WHERE (`mobile` = '" . $mobile . "' OR `mobile`='" . $formattedMobile . "')");
                    $row = $isValidMobile->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->mobile =  "Contact number already exists";
                    }
                }
                if (empty($whatsapp)) {
                    $status->whatsapp =  "Please enter student whatsapp number";
                } else if (!$formattedWhatsapp) {
                    $status->whatsapp =  "Please enter a valid whatsapp number";
                } else {
                    $isValidWhatsapp = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE (`whatsapp` = '" . $whatsapp . "' OR `whatsapp`='" . $formattedWhatsapp . "')");
                    $row = $isValidWhatsapp->fetch_assoc();
                    if ($row['count'] > 0) {
                        $status->whatsapp =  "Whatsapp number already exists";
                    }
                }



                if (!empty($address)) {
                    if (!strlen($address) > 100) {
                        $status->address1 =  "Address should be less than 100 characters";
                    }
                }
                if (!empty($address2)) {
                    if (!strlen($address2) > 100) {
                        $status->address2 =  "Address should be less than 100 characters";
                    }
                }
                if (!empty($address) || !$city == 0 || !empty($pcode)) {
                    if ($city == 0) {
                        $status->city =  "Please select student city";
                    }

                    if (empty($pcode)) {
                        $status->pcode =  "Please enter student city postal code";
                    }
                    if (empty($address)) {
                        $status->address =  "Please enter student address";
                    }
                }

                if (!empty($pcode) && !strlen($pcode) > 5) {
                    $status->pcode =  "Postcode should be less than 5 characters";
                }

                if (!empty($faculty) ||  !empty($programme) || !empty($intake) || !empty($id)) {

                    if (empty($faculty)) {
                        $status->faculty =  "Please select student faculty";
                    }

                    if (empty($programme)) {
                        $status->programme =  "Please select student programme";
                    } else {
                        $isValidProgramme = Database::search("SELECT * FROM `programmes` WHERE `programme_id` = '" . $programme . "'");
                        if ($isValidProgramme->num_rows == 0) {
                            $status->programme =  "Programme does not exist";
                        }
                    }

                    if (empty($intake)) {
                        $status->intake =  "Please select student intake";
                    } else {
                        $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_id` = '" . $intake . "'");
                        $row = $isValidIntake->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->intake =  "Intake does not exist";
                        }
                    }

                    if (empty($counselor)) {
                        $status->counselor =  "Please select student counselor";
                    }
                }

                if ($gtitle != 0 || $grelationship != 0 || !empty($gfname) || !empty($glname) || !empty($gemail) || !empty($gmobile)) {
                    if ($gtitle == 0) {
                        $status->gtitle =  "Please select student guardian title";
                    }
                    if ($grelationship == 0) {
                        $status->grelationship =  "Please select student guardian relationship";
                    }
                    if (empty($gfname)) {
                        $status->gfname =  "Please enter student guardian first name";
                    } else if (strlen($gfname) > 45) {
                        $status->gfname =  "First Name should be less than 45 characters";
                    }
                    if (empty($glname)) {
                        $status->glname =  "Please enter student guardian last name";
                    } else if (strlen($glname) > 45) {
                        $status->glname =  "Last Name should be less than 45 characters";
                    }
                    if (empty($gemail)) {
                        $status->gemail =  "Please enter student guardian email";
                    } else if (strlen($gemail) > 100) {
                        $status->gemail =  "Email should be less than 100 characters";
                    } else if (!filter_var($gemail, FILTER_VALIDATE_EMAIL)) {
                        $status->gemail =  "Please enter a valid email";
                    }
                    if (empty($gmobile)) {
                        $status->gmobile =  "Please enter student guardian mobile";
                    } else if (!normalizePhoneNumber($gmobile)) {
                        $status->gcontact =  "Please enter a valid contact number";
                    }
                }

                if (isset($_FILES["image"])) {
                    $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                    $file_ex = $_FILES["image"]["type"];
                    if (!in_array($file_ex, $allowed_image_extentions)) {
                        $status->image = "Only jpg,jpeg,png and svg allowed";
                    } else {
                        $new_file_extention;
                        switch ($file_ex) {
                            case "image/jpg":
                                $new_file_extention = ".jpg";
                                break;
                            case "image/jpeg":
                                $new_file_extention = ".jpeg";
                                break;
                            case "image/png":
                                $new_file_extention = ".png";
                                break;
                            case "image/svg+xml":
                                $new_file_extention = ".svg";
                                break;
                        }

                        if ($_FILES["image"]["error"] === 0) {
                            if ($_FILES["image"]["size"] <= 2 * 1024 * 1024) {
                                if (getimagesize($_FILES["image"]["tmp_name"])) {
                                    $image = uniqid() . $new_file_extention;
                                } else {
                                    $status->image = "Invalid image file!";
                                }
                            } else {
                                $status->image = "Maximum allowed file size is 2MB";
                            }
                        } else {
                            $status->image = "There is an error with the selected image";
                        }
                    }
                }

                $studentDocuments = validateStudentDocuments();

                foreach ($studentDocuments["errors"] as $key => $message) {
                    $status->$key = $message;
                }

                if (count((array)$status) > 0) {
                    echo json_encode($status);
                    exit;
                }
                $allFieldsPresent = true;

                if ($allFieldsPresent) {
                    $timezone = new DateTimeZone("Asia/Colombo");
                    $dateTime = new DateTime("now", $timezone);
                    $registerTime = $dateTime->format('Y-m-d H:i:s');

                    $timestamp = strtotime($dob);
                    $dob = date("Y-m-d", $timestamp);

                    $bytes = random_bytes(75);
                    $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                    $password = rand(1000, 9999);
                    $hashPassword = password_hash($password, PASSWORD_BCRYPT);

                    $login_details_columns = " `first_name`, `last_name`, `email`, `whatsapp`, `password`, `date_registered`,  `status_status_id`,`verification`";
                    $login_details_values = "'" . $fname . "','" . $lname . "','" . $email . "','" . $formattedWhatsapp . "','" . $hashPassword . "','" . $registerTime . "','1','" . $token . "'";

                    Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
                    $login_details_id = Database::$connection->insert_id;

                    $student_table_insert_columns = "`full_name`,`name_with_initials`,`title_title_id`,`mobile`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
                    $student_table_insert_values = "'" . $fullname . "','" . $nwi . "','" . $title . "','" . $formattedMobile . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

                    if (isset($_FILES["image"])) {
                        $student_table_insert_columns .= ", `profile_img`";
                        $student_table_insert_values .= ",'" . $image . "'";
                    }

                    if (!empty($nic)) {
                        $student_table_insert_columns .= ", `nic`";
                        $student_table_insert_values .= ",'" . $nic . "'";
                    }

                    if (!empty($passport)) {
                        $student_table_insert_columns .= ", `passport`";
                        $student_table_insert_values .= ",'" . $passport . "'";
                    }

                    if (!empty($dob)) {
                        $student_table_insert_columns .= ", `date_of_birth`";
                        $student_table_insert_values .= ",'" . $dob . "'";
                    }

                    if (!empty($nationality)) {
                        $student_table_insert_columns .= ", `nationality`";
                        $student_table_insert_values .= ",'" . $nationality . "'";
                    } else {
                        $student_table_insert_columns .= ", `nationality`";
                        $student_table_insert_values .= ",69'";
                    }
                    if (!empty($cob)) {
                        $student_table_insert_columns .= ", `country_of_birth`";
                        $student_table_insert_values .= ",'" . $cob . "'";
                    } else {
                        $student_table_insert_columns .= ", `country_of_birth`";
                        $student_table_insert_values .= ",69'";
                    }
                    if (!empty($cor)) {
                        $student_table_insert_columns .= ", `country_of_residence`";
                        $student_table_insert_values .= ",'" . $cor . "'";
                    } else {
                        $student_table_insert_columns .= ", `country_of_residence`";
                        $student_table_insert_values .= ",69'";
                    }

                    if ($marital_status != 0) {
                        $student_table_insert_columns .= ", `marital_status_marital_status_id`";
                        $student_table_insert_values .= ",'" . $marital_status . "'";
                    }

                    Database::iud("INSERT INTO `students` (" . $student_table_insert_columns . ") VALUES (" . $student_table_insert_values . ")");

                    $studentId = Database::$connection->insert_id;

                    if (!empty($address)) {
                        $address_table_insert_columns = "`address_line_1`,`city_city_id`,`postal_code`,`students_student_id`";
                        $address_table_insert_values = "'" . Database::escape($address) . "','" . $city . "','" . $pcode . "','" . $studentId . "'";

                        if (!empty($address2)) {
                            $address_table_insert_columns .= ", `address_line_2`";
                            $address_table_insert_values .= ",'" . Database::escape($address2) . "'";
                        }

                        Database::iud("INSERT INTO `student_has_address` (" . $address_table_insert_columns . ") VALUES (" . $address_table_insert_values . ")");
                    }

                    if (!empty($faculty)) {
                        $intakeStudentId = StudentIdGenerator::generateByIntakeId($intake);

                        $shi_table_insert_columns = "`students_student_id`, `student_id`,`addmission_date`,`status_status_id`,`users_user_id`,`intakes_intake_id`,`counselor_user_id`";
                        $shi_table_insert_values = "'" . $studentId . "', '" . $intakeStudentId . "','" . $registerTime . "','1','" . $sessionUserObject['user_id'] . "','" . $intake . "','" . $counselor . "'";

                        Database::iud("INSERT INTO `students_has_intakes` (" . $shi_table_insert_columns . ") VALUES (" . $shi_table_insert_values . ")");
                        $studentHasIntakeId = Database::$connection->insert_id;

                        $intakePaymentPlan = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intakes_intake_id`='" . $intake . "' AND `is_default_plan`='1'");
                        if ($intakePaymentPlan->num_rows > 0) {
                            $paymentPlanData = $intakePaymentPlan->fetch_assoc();

                            $studentPlanPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments` 
                                                INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id`=`payment_type`.`payment_type_id`
                                                WHERE `intake_has_payment_plan_id`='" . $paymentPlanData['intake_has_payment_plan_id'] . "'
                                                ");

                            $paymentPlanPaymentsPenalities = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` IN (SELECT `intake_payments_plan_has_payment_id` FROM `intake_payments_plan_has_payments` WHERE `intake_has_payment_plan_id` = '" . $paymentPlanData['intake_has_payment_plan_id'] . "')");

                            if ($studentPlanPayments->num_rows > 0) {
                                $payment_plan_insert_columns = "`students_has_intakes_students_has_intakes_id`,  `discount_type_discount_type_id`, `amount`, `discount`, `date_added`,`intake_has_payment_plan_id`,`payment_plan_payment_plan_id`,`payment_category_payment_category_id`";
                                $payment_plan_insert_values = "'" . $studentHasIntakeId . "', '" . $paymentPlanData['discount_type_discount_type_id'] . "', '" . $paymentPlanData['amount'] . "', '" . $paymentPlanData['discount'] . "', '" . $registerTime . "', '" . $paymentPlanData['intake_has_payment_plan_id'] . "','" . $paymentPlanData['payment_plan_payment_plan_id'] . "','" . $paymentPlanData['payment_category_payment_category_id'] . "'";

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
                    }

                    if ($gtitle != 0) {
                        $guardian_table_columns = "`students_student_id`,`first_name`,`last_name`,`guardian_relation_guardian_relation_id`,`title_title_id`,`email`,`mobile`";
                        $guardian_table_values = "'" . $studentId . "','" . $gfname . "','" . $glname . "','" . $grelationship . "','" . $gtitle . "','" . $gemail . "','" . normalizePhoneNumber($gmobile) . "'";
                        Database::iud("INSERT INTO `guardian_details` (" . $guardian_table_columns . ") VALUES (" . $guardian_table_values . ")");
                    }

                    if (isset($_FILES["image"])) {
                        $qimg_path = "../assets/imgs/students/" . $image;
                        move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                    }

                    uploadStudentDocuments($studentId, $studentDocuments);

                    if (is_array($physicalDocs)) {
                        foreach ($physicalDocs as $physicalTypeId) {
                            $physicalTypeId = intval($physicalTypeId);
                            if ($physicalTypeId > 0) {
                                // SELECT from the type table so an invalid type id inserts nothing
                                Database::iud("INSERT INTO `students_has_physical_documents`
                                (`students_student_id`, `students_identity_documents_type_id`, `is_received`, `received_date`, `users_user_id`)
                                SELECT '" . $studentId . "', `students_identity_documents_type_id`, 1, '" . $registerTime . "', '" . $sessionUserObject['user_id'] . "'
                                FROM `students_identity_documents_type`
                                WHERE `students_identity_documents_type_id` = '" . $physicalTypeId . "'");
                            }
                        }
                    }

                    $title = Database::search("SELECT * FROM `title` WHERE `title_id` = '" . $title . "'")->fetch_assoc();

                    $userName = $title['title'] . "." . $fname . " " . $lname;
                    $mail = EmailNotificationService::build(
                        EmailTemplateContent::PASSWORD_SETUP,
                        [
                            'action_url' => Project::getBasePortalUrl() . "/setup-password?token=" . $token
                        ]
                    );



                    try {
                        sendMail($email, $userName, $mail['subject'], $mail['body']);
                        $status->done =  "Student Added Successfully";
                    } catch (Exception $e) {
                        $status->done =  "Student Added Successfully but mail could not be sent";
                    }
                }
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

function normalizePhoneNumber($number)
{
    // Remove spaces, dashes, brackets, etc. (keep +)
    $number = trim($number);
    $number = preg_replace('/[^\d+]/', '', $number);

    // Already in international format
    if (preg_match('/^\+\d{8,15}$/', $number)) {
        return $number;
    }

    // Sri Lanka: +947XXXXXXXX
    if (preg_match('/^\+947\d{8}$/', $number)) {
        return $number;
    }

    // Sri Lanka: 947XXXXXXXX
    if (preg_match('/^947\d{8}$/', $number)) {
        return '+' . $number;
    }

    // Sri Lanka: 07XXXXXXXX
    if (preg_match('/^07\d{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    // Sri Lanka: 7XXXXXXXX
    if (preg_match('/^7\d{8}$/', $number)) {
        return '+94' . $number;
    }

    // Any other international number (8-15 digits)
    if (preg_match('/^\d{8,15}$/', $number)) {
        return '+' . $number;
    }

    // Invalid phone number
    return false;
}

function validateStudentDocuments()
{
    $maxSize = 8 * 1024 * 1024;

    $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

    $documentTypes = getStudentDocumentTypeConfig();

    $validFiles = [];
    $errors = [];

    foreach ($documentTypes as $fieldName => $config) {
        if (!isset($_FILES[$fieldName])) {
            continue;
        }

        $files = normalizeFilesArray($_FILES[$fieldName]);

        foreach ($files as $file) {
            if ($file["error"] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($file["error"] !== UPLOAD_ERR_OK) {
                $errors[$fieldName] = "Upload error occurred";
                continue;
            }

            if ($file["size"] > $maxSize) {
                $errors[$fieldName] = "File size must be less than 8MB";
                continue;
            }

            $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                $errors[$fieldName] = "Invalid file type";
                continue;
            }

            $validFiles[] = [
                "field_name" => $fieldName,
                "type_id" => $config["type_id"],
                "folder" => $config["folder"],
                "prefix" => $config["prefix"],
                "file" => $file,
                "extension" => $extension
            ];
        }
    }

    return [
        "files" => $validFiles,
        "errors" => $errors
    ];
}

function getStudentDocumentTypeConfig()
{
    return [
        "birth_certificate" => [
            "type_id" => 1,
            "folder" => "birth-certificates",
            "prefix" => "BIRTH_CERTIFICATE"
        ],
        "nic_passport" => [
            "type_id" => 2,
            "folder" => "nic-documents",
            "prefix" => "NIC_PASSPORT"
        ],
        "ol_result_sheet" => [
            "type_id" => 3,
            "folder" => "ol-result-sheets",
            "prefix" => "OL_RESULT_SHEET"
        ],
        "certificates" => [
            "type_id" => 4,
            "folder" => "certificates",
            "prefix" => "CERTIFICATE"
        ],
        "grama_niladhari" => [
            "type_id" => 5,
            "folder" => "grama-niladhari-letters",
            "prefix" => "GN_LETTER"
        ],
        "guardian_nic" => [
            "type_id" => 6,
            "folder" => "guardian-nic",
            "prefix" => "GUARDIAN_NIC"
        ],
        "nic_size_photos" => [
            "type_id" => 7,
            "folder" => "nic-size-photos",
            "prefix" => "NIC_SIZE_PHOTO",
            "extensions" => ["jpg", "jpeg", "png"]
        ],
        "student_photo" => [
            "type_id" => 8,
            "folder" => "student-photo-pdf",
            "prefix" => "STUDENT_PHOTO_PDF",
            "extensions" => ["pdf"]
        ],
        "academic_documents" => [
            "type_id" => 9,
            "folder" => "academic-documents",
            "prefix" => "ACADEMIC_DOCUMENT"
        ],
    ];
}

function normalizeFilesArray($fileInput)
{
    $files = [];

    if (is_array($fileInput["name"])) {
        foreach ($fileInput["name"] as $index => $name) {
            $files[] = [
                "name" => $fileInput["name"][$index],
                "type" => $fileInput["type"][$index],
                "tmp_name" => $fileInput["tmp_name"][$index],
                "error" => $fileInput["error"][$index],
                "size" => $fileInput["size"][$index],
            ];
        }
    } else {
        $files[] = $fileInput;
    }

    return $files;
}

function uploadStudentDocuments($studentId, $studentDocuments)
{
    $baseUploadDir = "../assets/uploads/student-identity-documents/";

    foreach ($studentDocuments["files"] as $doc) {
        $typeId = $doc["type_id"];
        $folder = $doc["folder"];
        $prefix = $doc["prefix"];
        $file = $doc["file"];
        $extension = $doc["extension"];

        $uploadDir = $baseUploadDir . $folder . "/";

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = $prefix . "_" . uniqid() . "_" . rand(1000, 9999) . "." . $extension;
        $serverPath = $uploadDir . $newFileName;

        if (move_uploaded_file($file["tmp_name"], $serverPath)) {
            Database::iud("
                INSERT INTO `students_identity_documents`
                (
                    `students_student_id`,
                    `students_identity_documents_type_id`,
                    `document`,
                    `date_added`
                )
                VALUES
                (
                    '" . $studentId . "',
                    '" . $typeId . "',
                    '" . Database::escape($newFileName) . "',
                    NOW()
                )
            ");
        }
    }
}

function normalizeText($text)
{
    $text = trim($text);
    $text = mb_strtolower($text);
    $text = preg_replace('/[^a-z\s]/u', '', $text);

    return $text;
}

function getOrCreateLocationId(
    string $table,
    string $nameColumn,
    string $parentColumn,
    int $parentId,
    string $inputText,
    int $distanceThreshold = 2
): int {
    $normalizedInput = normalizeText($inputText);

    if (strlen($normalizedInput) < 3) {
        throw new Exception("Location name too short or unclear");
    }

    $rows = Database::search("
        SELECT {$nameColumn}, {$table}_id AS id
        FROM {$table}
        WHERE {$parentColumn} = '{$parentId}'
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $row) {
        if (normalizeText($row[$nameColumn]) === $normalizedInput) {
            return (int)$row["id"];
        }
    }

    $bestMatch = null;
    $lowestDistance = PHP_INT_MAX;

    foreach ($rows as $row) {
        $distance = levenshtein(
            $normalizedInput,
            normalizeText($row[$nameColumn])
        );

        if ($distance < $lowestDistance) {
            $lowestDistance = $distance;
            $bestMatch = $row;
        }
    }

    if ($bestMatch && $lowestDistance <= $distanceThreshold) {
        return (int)$bestMatch["id"];
    }

    Database::iud("
        INSERT INTO {$table} ({$nameColumn}, {$parentColumn})
        VALUES (
            '" . Database::escape($inputText) . "',
            '{$parentId}'
        )
    ");

    return (int)Database::$connection->insert_id;
}
function generateSecureUniqueId()
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';

    for ($i = 0; $i < 8; $i++) {
        $randomString .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $randomString;
}
