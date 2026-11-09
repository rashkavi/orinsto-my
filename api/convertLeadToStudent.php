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
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 2))) {
                $status->error = 'You are not authorized to add student';
            } else {
                date_default_timezone_set('Asia/Colombo');
                $leadId = isset($_POST['lead_id']) ? trim($_POST['lead_id']) : '';

                if (empty($leadId)) {
                    $status->error = 'Please select lead id';
                } else {

                    $validLead = Database::search("
                    SELECT * FROM `leads` l
                    WHERE l.`lead_id` = '" . $leadId . "'
                    AND NOT EXISTS (
                        SELECT 1 
                        FROM `students_has_intakes` s 
                        WHERE s.`leads_lead_id` = l.`lead_id`
                    )
                ");
                    if ($validLead->num_rows == 0) {
                        $status->error = 'Invalid lead id or lead is already converted to student';
                    } else {

                        $leadData = $validLead->fetch_assoc();

                        $fname = isset($_POST['fname']) ? trim($_POST['fname']) : '';
                        $lname = isset($_POST['lname']) ? trim($_POST['lname']) : '';
                        $fullname = isset($_POST['fullname']) ? trim($_POST['fullname']) : '';
                        $nwi = isset($_POST['nwi']) ? trim($_POST['nwi']) : '';
                        $title = isset($_POST['title']) ? trim($_POST['title']) : 0;
                        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : 0;
                        $marital_status = isset($_POST['marital_status']) ? trim($_POST['marital_status']) : 0;
                        $nationality = isset($_POST['nationality']) ? trim($_POST['nationality']) : '';
                        $nic = isset($_POST['nic']) ? trim($_POST['nic']) : '';
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
                        $gtitle = isset($_POST['gtitle']) ? trim($_POST['gtitle']) : 0;
                        $grelationship = isset($_POST['grelationship']) ? trim($_POST['grelationship']) : 0;
                        $gfname = isset($_POST['gfname']) ? trim($_POST['gfname']) : '';
                        $glname = isset($_POST['glname']) ? trim($_POST['glname']) : '';
                        $gemail = isset($_POST['gemail']) ? trim($_POST['gemail']) : '';
                        $gmobile = isset($_POST['gmobile']) ? trim($_POST['gmobile']) : '';
                        $reg_payment = isset($_POST['reg_payment']) ? (int)$_POST['reg_payment'] : 0;
                        $reg_amount = isset($_POST['reg_amount']) ? trim($_POST['reg_amount']) : '';
                        $custom_reg_amount = isset($_POST['custom_reg_amount']) ? trim($_POST['custom_reg_amount']) : '';
                        $note = isset($_POST['note']) ? trim($_POST['note']) : '';

                        $image;

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
                        if ($marital_status == 0) {
                            $status->marital_status =  "Please select student marital status";
                        }

                        if (empty($nic)) {
                            $status->nic =  "Please enter student nic";
                        } else if (strlen($nic) > 45) {
                            $status->nic =  "NIC should be less than 45 characters";
                        } else if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                            $status->nic =  "Please enter a valid NIC number";
                        } else {
                            $isValidNic = Database::search("SELECT * FROM `students` WHERE `nic` = '" . $nic . "'");
                            if ($isValidNic->num_rows > 0) {
                                $status->nic =  "NIC already exists";
                            }
                        }

                        if (empty($dob)) {
                            $status->dob =  "Please enter student date of birth";
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
                        $normalizeMobile = normalizePhoneNumber($mobile);
                        if (empty($mobile)) {
                            $status->mobile =  "Please enter student mobile";
                        } else if (!normalizePhoneNumber($mobile)) {
                            $status->mobile =  "Please enter a valid contact number";
                        } else {
                            $isValidMobile = Database::search("SELECT COUNT(*) as 'count' FROM `students` WHERE `mobile` = '" . $mobile . "' OR `mobile` = '" . $normalizeMobile . "'");
                            $row = $isValidMobile->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->mobile =  "Contact number already exists";
                            }
                        }

                        $normalizeWhatsapp = normalizePhoneNumber($whatsapp);
                        if (empty($whatsapp)) {
                            $status->whatsapp =  "Please enter student whatsapp number";
                        } else if (!normalizePhoneNumber($whatsapp)) {
                            $status->whatsapp =  "Please enter a valid whatsapp number";
                        } else {
                            $isValidWhatsapp = Database::search("SELECT COUNT(*) as 'count' FROM `login_details` WHERE `whatsapp` = '" . $whatsapp . "' OR `whatsapp` = '" . $normalizeWhatsapp . "'");
                            $row = $isValidWhatsapp->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->whatsapp =  "Whatsapp number already exists";
                            }
                        }

                        $invalid_chars = '/[\'\";\-]/';
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

                        if (empty($faculty)) {
                            $status->faculty =  "Please select student faculty";
                        }

                        if (empty($programme)) {
                            $status->programme =  "Please select student programme";
                        } else {
                            $isValidProgramme = Database::search("SELECT * FROM `programmes` WHERE `programme_id` = '" . $programme . "'");
                            if ($isValidProgramme->num_rows == 0) {
                                $status->programme =  "Programme does not exist";
                            } else {
                                $programmeData = $isValidProgramme->fetch_assoc();
                                if (empty($programmeData['programme_letter'])) {
                                    $status->programme = "Programme letter is not defined for the selected programme. It is required for Student ID generation. Please configure it and continue.";
                                }
                            }
                        }

                        if (!empty($intake)) {
                            $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id` = '" . $intake . "'");
                            if ($isValidIntake->num_rows == 0) {
                                $status->intake =  "Intake does not exist";
                            } else {
                                $intakeData = $isValidIntake->fetch_assoc();
                                if (empty($intakeData['intake_prefix'])) {
                                    $status->intake = "Intake letter is not defined for the selected intake. It is required for Student ID generation. Please configure it and continue.";
                                }
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

                            $normalizeGMobile = normalizePhoneNumber($gmobile);
                            if (empty($gmobile)) {
                                $status->gmobile =  "Please enter student guardian mobile";
                            } else if (!normalizePhoneNumber($gmobile)) {
                                $status->gcontact =  "Please enter a valid contact number";
                            }
                        }

                        if ($reg_payment == 1) {
                            if (empty($reg_amount)) {
                                $status->reg_amount = "Please select registration payment amount";
                            } else if ($reg_amount == 'Other' && empty($custom_reg_amount)) {
                                $status->custom_reg_amount = "Please enter registration payment amount";
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

                        if (empty(get_object_vars($status))) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $registerTime = $dateTime->format('Y-m-d H:i:s');

                            $timestamp = strtotime($dob);
                            $dob = date("Y-m-d", $timestamp);

                            $bytes = random_bytes(75);
                            $token = substr(str_replace(['+', '/', '='], '', base64_encode($bytes)), 0, 100);

                            $password = rand(1000, 9999);
                            $hashPassword = password_hash($password, PASSWORD_BCRYPT);

                            $login_details_columns = " `first_name`, `last_name`, `email`, `whatsapp`, `password`, `date_registered`,`status_status_id`";
                            $login_details_values = "'" . $fname . "','" . $lname . "','" . $email . "','" . $normalizeWhatsapp . "','" . $hashPassword . "','" . $registerTime . "','1'";

                            Database::iud("INSERT INTO `login_details` (" . $login_details_columns . ") VALUES (" . $login_details_values . ")");
                            $login_details_id = Database::$connection->insert_id;

                            $student_table_insert_columns = "`full_name`,`name_with_initials`,`date_of_birth`,`nic`,`title_title_id`,`marital_status_marital_status_id`,`mobile`,`date_added`,`gender_gender_id`,`users_user_id`,`login_details_login_details_id`";
                            $student_table_insert_values = "'" . $fullname . "','" . $nwi . "','" . $dob . "','" . $nic . "','" . $title . "','" . $marital_status . "','" . $normalizeMobile . "','" . $registerTime . "','" . $gender . "','" . $sessionUserObject['user_id'] . "','" . $login_details_id . "'";

                            if (isset($_FILES["image"])) {
                                $student_table_insert_columns .= ", `profile_img`";
                                $student_table_insert_values .= ",'" . $image . "'";
                            }

                            if (!empty($nationality)) {
                                $student_table_insert_columns .= ", `nationality`";
                                $student_table_insert_values .= ",'" . $nationality . "'";
                            } else {
                                $student_table_insert_columns .= ", `nationality`";
                                $student_table_insert_values .= ",'69'";
                            }
                            if (!empty($cob)) {
                                $student_table_insert_columns .= ", `country_of_birth`";
                                $student_table_insert_values .= ",'" . $cob . "'";
                            } else {
                                $student_table_insert_columns .= ", `country_of_birth`";
                                $student_table_insert_values .= ",'69'";
                            }
                            if (!empty($cor)) {
                                $student_table_insert_columns .= ", `country_of_residence`";
                                $student_table_insert_values .= ",'" . $cor . "'";
                            } else {
                                $student_table_insert_columns .= ", `country_of_residence`";
                                $student_table_insert_values .= ",'69'";
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


                            $shi_table_insert_columns = "`students_student_id`,`addmission_date`,`status_status_id`,`users_user_id`,`leads_lead_id`";
                            $shi_table_insert_values = "'" . $studentId . "','" . $registerTime . "','1','" . $sessionUserObject['user_id'] . "','" . $leadId . "'";

                            if (!empty($leadData['users_user_id'])) {
                                $shi_table_insert_columns .= ", `assigned_user_id`";
                                $shi_table_insert_values .= ",'" . $leadData['users_user_id'] . "'";
                            } else {
                                $shi_table_insert_columns .= ", `assigned_user_id`";
                                $shi_table_insert_values .= ",NULL";
                            }
                            $intakeStudentId = "";
                            if (!empty($intake)) {
                                $shi_table_insert_columns .= ", `intakes_intake_id`";
                                $shi_table_insert_values .= ",'" . $intake . "'";

                                $intakeStudentId = StudentIdGenerator::generateByIntakeId($intake);
                                $shi_table_insert_columns .= ", `student_id`";
                                $shi_table_insert_values .= ",'" . $intakeStudentId . "'";
                            }

                            Database::iud("INSERT INTO `students_has_intakes` (" . $shi_table_insert_columns . ") VALUES (" . $shi_table_insert_values . ")");
                            $studentIntakeId = Database::$connection->insert_id;

                            $load_log_insert_columns = "`date_updated`,`leads_id`,`users_user_id`,`lead_student_reg_status_lead_student_reg_status_id`,`lead_status_lead_status_id`";
                            $load_log_insert_values = "'" . $registerTime . "','" . $leadId . "','" . $sessionUserObject['user_id'] . "','4','6'";

                            Database::iud("INSERT INTO `lead_logs` (" . $load_log_insert_columns . ") 
                                        VALUES (" . $load_log_insert_values . ")");

                            Database::iud("UPDATE `leads` SET `lead_status_lead_status_id`='6',`lead_student_reg_status_lead_student_reg_status_id`='4' WHERE `lead_id`='" . $leadId . "'");

                            if (!empty($programme)) {
                                Database::iud("UPDATE `leads` SET `programmes_programme_id`='" . $programme . "' WHERE `lead_id`='" . $leadId . "'");
                            }
                            $studnet_log_insert_columns = "`date_updated`,`students_has_intakes_students_has_intakes_id`,`users_user_id`,`log`";
                            $student_log_insert_values = "'" . $registerTime . "','" . $studentIntakeId . "','" . $sessionUserObject['user_id'] . "','Add student from lead'";

                            Database::iud("INSERT INTO `students_has_intakes_logs` (" . $studnet_log_insert_columns . ") 
                                                    VALUES (" . $student_log_insert_values . ")");

                            if ($gtitle != 0) {
                                $guardian_table_insert_columns = "`guardian_relation_guardian_relation_id`,`first_name`,`last_name`,`email`,`mobile`,`title_title_id`,`students_student_id`";
                                $guardian_table_insert_values = "'" . $grelationship . "','" . $gfname . "','" . $glname . "','" . $gemail . "','" . $normalizeGMobile . "','" . $gtitle . "','" . $studentId . "'";
                                Database::iud("INSERT INTO `guardian_details` (" . $guardian_table_insert_columns . ") VALUES (" . $guardian_table_insert_values . ")");
                            }

                            $regis_amount = $reg_amount;
                            if ($reg_amount == 'Other') {
                                $regis_amount = $custom_reg_amount;
                            }


                            uploadStudentDocuments($studentId, $studentDocuments, $studentIntakeId, $reg_payment, $regis_amount, $note, $sessionUserObject);

                            if (isset($_FILES["image"])) {
                                $qimg_path = "../assets/imgs/students/" . $image;
                                move_uploaded_file($_FILES["image"]["tmp_name"], $qimg_path);
                            }

                            if ($reg_payment) {

                                Database::iud("UPDATE `leads` SET `reg_payment_enabled`='" . ($reg_payment ? 1 : 0) . "',`reg_payment_amount`='" . $regis_amount . "' WHERE `lead_id`='" . $leadId . "'");

                                $isCreateRegistrationPayment = Database::search("SELECT * FROM `course_payments` 
                                WHERE `students_has_intakes_students_has_intakes_id`='" . $studentIntakeId . "'");

                                if ($isCreateRegistrationPayment->num_rows == 0) {
                                    Database::iud("
                            INSERT INTO `course_payments`
                            (
                                `payment_status_payment_status_id`,
                                `payment_type_payment_type_id`,
                                `refference`,
                                `discount`,
                                `amount`,
                                `discount_type_discount_type_id`,
                                `panelty_payment_option_panelty_payment_option_id`,
                                `students_has_intakes_students_has_intakes_id`
                            )
                            VALUES
                            (
                                '1',
                                '18',
                                '" . generateSecureUniqueId() . "',
                                '0',
                                '" . $reg_amount . "',
                                '1',
                                '1',
                                '" . $studentIntakeId . "'
                            )
                        ");

                                    $paymentId = Database::$connection->insert_id;
                                    $logMsg = "Registration Payment Scheduled";
                                    $date = date("Y-m-d H:i:s");
                                    Database::iud("INSERT INTO `payment_logs` 
                                    (`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`note`,`log`) VALUES 
                                    ('" . $paymentId . "','" . $sessionUserObject['user_id'] . "','" . $date . "','" . Database::escape($note) . "','" . $logMsg . "')");
                                }
                            }

                            if (!empty($intake)) {
                                $title = Database::search("SELECT * FROM `title` WHERE `title_id`='" . $title . "'")->fetch_assoc();

                                $userName = $title['title'] . "." . $fname . " " . $lname;
                                $mail = EmailNotificationService::build(
                                    EmailTemplateContent::WELCOME_STUDENT,
                                    [
                                        'action_url' => Project::getBasePortalUrl() . "/setup-password?token=" . $token,
                                        'program' => $programmeData['programme'],
                                        'intake' => $intakeData['intake'],
                                        'student_id' => $intakeStudentId
                                    ]
                                );

                                try {
                                    sendMail($email, $userName, $mail['subject'], $mail['body']);
                                    $status->done =  "Student Added Successfully";
                                } catch (Exception $e) {
                                    $status->warning =  "Student Added Successfully. Failed to send email to student";
                                }
                            } else {
                                $status->done =  "Student Added Successfully";
                            }
                        }
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
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}

function validateStudentDocuments()
{
    $maxSize = 2 * 1024 * 1024; // 2MB

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
                $errors[$fieldName] = "File size must be less than 2MB";
                continue;
            }

            $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

            if (!in_array($extension, $allowedExtensions)) {
                $errors[$fieldName] = "Invalid file type";
                continue;
            }

            // ── Duplicate file check via SHA-256 hash ──────────────────────
            $fileHash = hash_file('sha256', $file["tmp_name"]);

            if ($config["type_id"] == 5) {
                // Payment slip: check against course_payment_slips
                $isValidSlip = Database::search(
                    "SELECT `reference_number` FROM `course_payment_slips`
                     WHERE `slip_hash` = '" . Database::escape($fileHash) . "'"
                );
                if ($isValidSlip->num_rows > 0) {
                    $slipRow = $isValidSlip->fetch_assoc();
                    $errors[$fieldName] = "Duplicate receipt detected. This payment slip was already submitted (Ref: " . $slipRow['reference_number'] . ").";
                    continue;
                }
            }
            // ───────────────────────────────────────────────────────────────

            $validFiles[] = [
                "field_name" => $fieldName,
                "type_id"    => $config["type_id"],
                "folder"     => $config["folder"],
                "prefix"     => $config["prefix"],
                "file"       => $file,
                "extension"  => $extension,
                "file_hash"  => $fileHash,   // carry the hash forward so uploadStudentDocuments doesn't re-hash
            ];
        }
    }

    return [
        "files"  => $validFiles,
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
        "academic_documents" => [
            "type_id" => 3,
            "folder" => "academic-documents",
            "prefix" => "ACADEMIC_DOCUMENT"
        ],
        "certificates" => [
            "type_id" => 4,
            "folder" => "certificates",
            "prefix" => "CERTIFICATE"
        ],
        "payment_slip" => [
            "type_id" => 5,
            "folder" => "payment-slips",
            "prefix" => "PAYMENT_SLIP"
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

function uploadStudentDocuments($studentId, $studentDocuments, $studentIntakeId, $regPayment, $regAmount, $note, $sessionUserObject)
{
    $baseUploadDir = "../assets/uploads/student-identity-documents/";
    $basePaymentDir = "../assets/uploads/";

    foreach ($studentDocuments["files"] as $doc) {

        $typeId   = $doc["type_id"];
        $folder   = $doc["folder"];
        $prefix   = $doc["prefix"];
        $file     = $doc["file"];
        $extension = $doc["extension"];

        if ($typeId == 5) {
            $uploadDir = $basePaymentDir . $folder . "/";
        } else {
            $uploadDir = $baseUploadDir . $folder . "/";
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // ── Duplicate-image check (hash the tmp file before moving it) ──────
        $fileHash = hash_file('sha256', $file["tmp_name"]);

        if ($typeId == 5) {
            // Check against payment slip hashes
            $isValidSlip = Database::search(
                "SELECT * FROM `course_payment_slips`
                 WHERE `slip_hash` = '" . Database::escape($fileHash) . "'"
            );
            if ($isValidSlip->num_rows > 0) {
                // Skip this file – duplicate slip
                continue;
            }
        }
        // ────────────────────────────────────────────────────────────────────

        if ($typeId == 5) {
            if ($regPayment) {
                // 1. Create the course_payments record
                Database::iud("
                INSERT INTO `course_payments`
                (
                    `payment_status_payment_status_id`,
                    `payment_type_payment_type_id`,
                    `refference`,
                    `discount`,
                    `amount`,
                    `discount_type_discount_type_id`,
                    `panelty_payment_option_panelty_payment_option_id`,
                    `students_has_intakes_students_has_intakes_id`,
                   
                )
                VALUES
                (
                    '1',
                    '18',
                    '" . generateSecureUniqueId() . "',
                    '0',
                    '" . $regAmount . "',
                    '1',
                    '1',
                    '" . $studentIntakeId . "',
                  
                )
            ");

                $coursePaymentId = Database::$connection->insert_id;

                $logMsg = "Registration Payment Scheduled";
                $date = date("Y-m-d H:i:s");
                Database::iud("INSERT INTO `payment_logs` 
                                    (`course_payments_course_payments_id`,`users_user_id`,`date_updated`,`note`,`log`) VALUES 
                                    ('" . $coursePaymentId . "','" . $sessionUserObject['user_id'] . "','" . $date . "','" . Database::escape($note) . "','" . $logMsg . "')");

                // 2. Create the course_payment_has_payments record
                Database::iud("
                INSERT INTO `course_payment_has_payments`
                (
                    `course_payments_course_payments_id`,
                    `payment_method_payment_method_id`,
                    `date_paid`,
                    `paid_amount`,
                    `discount_amount`,
                    `payment_status_payment_status_id`,
                    `date_added`
                )
                VALUES
                (
                    '" . $coursePaymentId . "',
                    '1',
                    '" . date('Y-m-d') . "',
                    '" . $regAmount . "',
                    '0',
                    '2',
                    '" . date('Y-m-d H:i:s') . "'
                )
            ");
                $coursePaymentHasPaymentId = Database::$connection->insert_id;

                $logMsg = "Upload Direct Payment Receipt";
                $date = date("Y-m-d H:i:s");
                Database::iud("INSERT INTO `payment_logs` 
                                    (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`note`,`log`) VALUES 
                                    ('" . $coursePaymentId . "','" . $coursePaymentHasPaymentId . "','" . $sessionUserObject['user_id'] . "','" . $date . "','" . Database::escape($note) . "','" . $logMsg . "')");


                $newFileName = 'R' . $coursePaymentHasPaymentId . '_' . uniqid() . '.' . $extension;
                $serverPath  = $uploadDir . $newFileName;

                if (move_uploaded_file($file["tmp_name"], $serverPath)) {
                    // Build a human-readable reference from the hash
                    $reference = strtoupper(substr($fileHash, 0, 10));

                    // Insert the slip record (with hash for future duplicate checks)
                    Database::iud("
                    INSERT INTO `course_payment_slips`
                    (
                        `reference_number`,
                        `slip_hash`,
                        `slip_name`,
                        `course_payment_has_payment_id`
                    )
                    VALUES
                    (
                        '" . Database::escape($reference) . "',
                        '" . Database::escape($fileHash) . "',
                        '" . Database::escape($newFileName) . "',
                        '" . $coursePaymentHasPaymentId . "'
                    )
                ");
                }
            }
        } else {
            $newFileName = $prefix . "_" . uniqid() . "_" . rand(1000, 9999) . "." . $extension;
            $serverPath  = $uploadDir . $newFileName;

            if (move_uploaded_file($file["tmp_name"], $serverPath)) {
                // Identity / academic document
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
            return (int)$row['id'];
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
        return (int)$bestMatch['id'];
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
