<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $id = isset($_POST['id']) ? trim($_POST['id']) : '';

            if (empty($id)) {
                $status->error = "Invalid student Id";
            } else {
                $isHasId = Database::search("SELECT `student_id`,`profile_img`,`login_details_login_details_id` FROM `students` WHERE `student_id`='" . $id . "'");

                if ($isHasId->num_rows == 0) {
                    $status->error = "Invalid student Id";
                } else {

                    $student = $isHasId->fetch_assoc();

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
                        $status->fname =  "Please enter user first name";
                    } else if (strlen($fname) > 45) {
                        $status->fname =  "First Name should be less than 45 characters";
                    }
                    if (empty($lname)) {
                        $status->lname =  "Please enter user last name";
                    } else if (strlen($lname) > 45) {
                        $status->lname =  "Last Name should be less than 45 characters";
                    }
                    if (empty($fullname)) {
                        $status->fullname =  "Please enter user full name";
                    } else if (strlen($fullname) > 150) {
                        $status->fullname =  "Full Name should be less than 150 characters";
                    }
                    if (empty($nwi)) {
                        $status->nwi =  "Please enter user name with initials";
                    } else if (strlen($nwi) > 45) {
                        $status->nwi =  "Name with initials should be less than 45 characters";
                    }
                    if ($title == 0) {
                        $status->title =  "Please select user title";
                    }
                    if ($gender == 0) {
                        $status->gender =  "Please select user gender";
                    }
                    if ($marital_status == 0) {
                        $status->marital_status =  "Please select user marital status";
                    }
                    if (empty($nationality)) {
                        $status->nationality =  "Please enter user nationality";
                    } else if (strlen($nationality) > 45) {
                        $status->nationality =  "Nationality should be less than 45 characters";
                    }
                    if (!empty($nic)) {
                        if (strlen($nic) > 45) {
                            $status->nic =  "NIC should be less than 45 characters";
                        } else if (!preg_match('/^(?:[0-9]{9}[vV]|[0-9]{12})$/', $nic)) {
                            $status->nic =  "Please enter a valid NIC number";
                        }
                    }
                    if (empty($cob)) {
                        $status->cob =  "Please enter user country of birth";
                    } else if (strlen($cob) > 45) {
                        $status->cob =  "Country of Birth should be less than 45 characters";
                    }
                    if (empty($cor)) {
                        $status->cor =  "Please enter user country of residence";
                    } else if (strlen($cor) > 45) {
                        $status->cor =  "Country of Residence should be less than 45 characters";
                    }
                    if (empty($dob)) {
                        $status->dob =  "Please enter user date of birth";
                    }
                    if (empty($email)) {
                        $status->email =  "Please enter user email";
                    } else if (strlen($email) > 100) {
                        $status->email =  "Email should be less than 100 characters";
                    } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $status->test = $email;
                        $status->email =  "Please enter a valid email";
                    } else {
                        $emailHasStudent = Database::search("SELECT `email` FROM `login_details`
                        INNER JOIN `students` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                         WHERE `email` = '" . $email . "' AND `student_id` != '" . $student["student_id"] . "'");
                        if ($emailHasStudent->num_rows > 0) {
                            $status->email =  "Email already exists";
                        }
                    }
                    if (empty($mobile)) {
                        $status->mobile =  "Please enter user mobile";
                    } else if (!$formattedMobile) {
                        $status->mobile =  "Please enter a valid contact number";
                    } else {
                        $mobileHasStudent = Database::search("SELECT `mobile` FROM `students` WHERE (`mobile` = '" . $mobile . "' OR `mobile` = '" . $formattedMobile . "') AND `student_id` != '" . $student["student_id"] . "'");
                        if ($mobileHasStudent->num_rows > 0) {
                            $status->mobile =  "Contact number already exists";
                        }
                    }
                    if (empty($whatsapp)) {
                        $status->whatsapp =  "Please enter user mobile";
                    } else if (!$formattedWhatsapp) {
                        $status->whatsapp =  "Please enter a valid whatsapp number";
                    } else {
                        $whatsappHasStudent = Database::search("SELECT `whatsapp` FROM `login_details`
                        INNER JOIN `students` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id` 
                        WHERE (`whatsapp` = '" . $whatsapp . "' OR `whatsapp` = '" . $formattedWhatsapp . "') AND `student_id` != '" . $student["student_id"] . "'");
                        if ($whatsappHasStudent->num_rows > 0) {
                            $status->whatsapp =  "Whatsapp number already exists";
                        }
                    }

                    if (!empty($address) || !empty($address2) || !$city == 0 || !empty($pcode)) {
                        if (empty($address)) {
                            $status->address =  "Please enter user address";
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

                        if ($city == 0) {
                            $status->city =  "Please select user city";
                        }
                        if (empty($pcode)) {
                            $status->pcode =  "Please enter user city postal code";
                        }
                        if (!empty($pcode) && !strlen($pcode) > 5) {
                            $status->pcode =  "Postcode should be less than 5 characters";
                        }
                    }


                    if ($gtitle != 0 || $grelationship != 0 || !empty($gfname) || !empty($glname) || !empty($gemail) || !empty($gmobile)) {
                        if ($gtitle == 0) {
                            $status->gtitle =  "Please select user guardian title";
                        }
                        if ($grelationship == 0) {
                            $status->grelationship =  "Please select user guardian relationship";
                        }
                        if (empty($gfname)) {
                            $status->gfname =  "Please enter user guardian first name";
                        } else if (strlen($gfname) > 45) {
                            $status->gfname =  "First Name should be less than 45 characters";
                        }
                        if (empty($glname)) {
                            $status->glname =  "Please enter user guardian last name";
                        } else if (strlen($glname) > 45) {
                            $status->glname =  "Last Name should be less than 45 characters";
                        }
                        if (empty($gemail)) {
                            $status->gemail =  "Please enter user guardian email";
                        } else if (strlen($gemail) > 100) {
                            $status->gemail =  "Email should be less than 100 characters";
                        } else if (!filter_var($gemail, FILTER_VALIDATE_EMAIL)) {
                            $status->gemail =  "Please enter a valid email";
                        }
                        if (empty($gmobile)) {
                            $status->gmobile =  "Please enter user guardian mobile";
                        } else if (!normalizePhoneNumber($gmobile)) {
                            $status->gmobile =  "Please enter a valid contact number";
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

                    $allFieldsPresent = true;
                    if (empty(get_object_vars($status))) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $resetTime = $dateTime->format('Y-m-d H:i:s');

                        $timestamp = strtotime($dob);
                        $dob = date("Y-m-d", $timestamp);

                        $login_table_update = "`first_name`='" . $fname . "',`last_name`='" . $lname . "',`whatsapp`='" . $formattedWhatsapp . "',`email`='" . $email . "'";

                        $student_table_update = "`full_name`='" . $fullname . "',`name_with_initials`='" . $nwi . "'
                        ,`date_of_birth`='" . $dob . "',`nic`='" . $nic . "',`title_title_id`='" . $title . "',`marital_status_marital_status_id`='" . $marital_status . "'
                        ,`mobile`='" . $formattedMobile . "',`gender_gender_id`='" . $gender . "',`nationality`='" . $nationality . "'
                        ,`country_of_birth`='" . $cob . "',`country_of_residence`='" . $cor . "'
                        ";

                        if (isset($_FILES["image"])) {
                            $student_table_update .= ",`profile_img`='" . $image . "'";
                        }

                        Database::iud("UPDATE `login_details` SET " . $login_table_update . " WHERE `login_details_id` = '" . $student["login_details_login_details_id"] . "'");
                        Database::iud("UPDATE `students` SET " . $student_table_update . " WHERE `student_id` = '" . $student["student_id"] . "'");

                        if (!empty($address)) {
                            $studentHasAddress = Database::search("SELECT * FROM `student_has_address` WHERE `students_student_id`='" . $student["student_id"] . "'");
                            if ($studentHasAddress->num_rows == 0) {

                                $address_table_columns = "`students_student_id`,`address_line_1`,`city_city_id`,`postal_code`";
                                $address_table_values = "'" . $student["student_id"] . "','" . Database::escape($address) . "','" . $city . "','" . $pcode . "'";

                                if (!empty($address2)) {
                                    $address_table_columns .= ",`address_line_2`";
                                    $address_table_values .= ",'" . Database::escape($address2) . "'";
                                }

                                Database::iud("INSERT INTO `student_has_address` (" . $address_table_columns . ") VALUES (" . $address_table_values . ")");
                            } else {
                                $address_table_update = "`address_line_1`='" . Database::escape($address) . "',`city_city_id`='" . $city . "',`postal_code`='" . $pcode . "'";

                                if (!empty($address2)) {
                                    $address_table_update .= ",`address_line_2`='" . Database::escape($address2) . "'";
                                }

                                Database::iud("UPDATE `student_has_address` SET " . $address_table_update . " WHERE `students_student_id` = '" . $student["student_id"] . "'");
                            }
                        }


                        if ($gtitle != 0) {
                            $studnetHasGuardian = Database::search("SELECT * FROM `guardian_details` WHERE `students_student_id`='" . $student["student_id"] . "'");
                            if ($studnetHasGuardian->num_rows == 0) {
                                $guardian_table_columns = "`students_student_id`,`first_name`,`last_name`,`guardian_relation_guardian_relation_id`,`title_title_id`,`email`,`mobile`";
                                $guardian_table_values = "'" . $student["student_id"] . "','" . $gfname . "','" . $glname . "','" . $grelationship . "','" . $gtitle . "','" . $gemail . "','" . normalizePhoneNumber($gmobile) . "'";
                                Database::iud("INSERT INTO `guardian_details` (" . $guardian_table_columns . ") VALUES (" . $guardian_table_values . ")");
                            } else {

                                $guardian_table_update = "`first_name`='" . $gfname . "',`last_name`='" . $glname . "',`guardian_relation_guardian_relation_id`='" . $grelationship . "',`title_title_id`='" . $gtitle . "',`email`='" . $gemail . "',`mobile`='" . normalizePhoneNumber($gmobile) . "'";
                                Database::iud("UPDATE `guardian_details` SET " . $guardian_table_update . " WHERE `students_student_id` = '" . $student["student_id"] . "'");
                            }
                        }
                        if (isset($_FILES["image"])) {
                            $image_path = "../assets/imgs/students/" . $image;
                            move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);

                            if (!empty($student["profile_img"])) {
                                if (file_exists("../assets/imgs/students/" . $student["profile_img"])) {
                                    unlink("../assets/imgs/students/" . $student["profile_img"]);
                                }
                            }
                        }

                        if (isset($_POST["removed_documents"]) && is_array($_POST["removed_documents"])) {

                            $docFolderMap = [
                                1 => "birth-certificates",
                                2 => "nic-documents",
                                3 => "academic-documents",
                                4 => "certificates",
                            ];

                            foreach ($_POST["removed_documents"] as $docId) {
                                $docId = (int)$docId;

                                if ($docId <= 0) {
                                    continue;
                                }

                                $docRs = Database::search("
                                    SELECT 
                                        `students_identity_document_id`,
                                        `students_identity_documents_type_id`,
                                        `document`
                                    FROM `students_identity_documents`
                                    WHERE `students_identity_document_id` = '" . $docId . "'
                                      AND `students_student_id` = '" . $student["sId"] . "'
                                    LIMIT 1
                                ");

                                if ($docRs->num_rows == 0) {
                                    continue;
                                }

                                $doc = $docRs->fetch_assoc();

                                $typeId = (int)$doc["students_identity_documents_type_id"];
                                $fileName = basename($doc["document"]);

                                if (isset($docFolderMap[$typeId])) {
                                    $filePath = "../assets/uploads/student-identity-documents/" . $docFolderMap[$typeId] . "/" . $fileName;

                                    if (is_file($filePath)) {
                                        unlink($filePath);
                                    }
                                }

                                Database::iud("
                                    DELETE FROM `students_identity_documents`
                                    WHERE `students_identity_document_id` = '" . $docId . "'
                                      AND `students_student_id` = '" . $student["sId"] . "'
                                ");
                            }
                        }

                        uploadStudentDocuments($student["student_id"], $studentDocuments);

                        if (isset($_POST['physical_documents'])) {
                            $physicalDocs = json_decode($_POST['physical_documents'], true);
                            if (is_array($physicalDocs)) {
                                syncStudentPhysicalDocuments($id, $physicalDocs, $sessionUserObject['user_id']);
                            }
                        }

                        $status->done =  "Student Update Successfully";
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


function syncStudentPhysicalDocuments($studentId, array $selectedTypeIds, $userId)
{
    $studentId = (int)$studentId;
    $userId = (int)$userId;

    // Clean the posted ids: ints only, no duplicates
    $selected = [];
    foreach ($selectedTypeIds as $t) {
        $t = (int)$t;
        if ($t > 0) {
            $selected[$t] = true;
        }
    }

    $now = (new DateTime("now", new DateTimeZone("Asia/Colombo")))->format('Y-m-d H:i:s');

    // Current saved state: type_id => is_received
    $current = [];
    $currentRs = Database::search("SELECT `students_identity_documents_type_id`, `is_received`
        FROM `students_has_physical_documents`
        WHERE `students_student_id` = '" . $studentId . "'");
    while ($row = $currentRs->fetch_assoc()) {
        $current[(int)$row['students_identity_documents_type_id']] = (int)$row['is_received'];
    }

    // Only valid document types are processed, so a tampered id is ignored
    $typesRs = Database::search("SELECT `students_identity_documents_type_id` FROM `students_identity_documents_type`");
    while ($type = $typesRs->fetch_assoc()) {
        $typeId = (int)$type['students_identity_documents_type_id'];
        $want = isset($selected[$typeId]) ? 1 : 0;
        $exists = array_key_exists($typeId, $current);

        if (!$exists && $want === 1) {
            // Newly ticked, no record yet
            Database::iud("INSERT INTO `students_has_physical_documents`
                (`students_student_id`, `students_identity_documents_type_id`, `is_received`, `received_date`, `users_user_id`)
                VALUES ('" . $studentId . "', '" . $typeId . "', 1, '" . $now . "', '" . $userId . "')");
        } elseif ($exists && $current[$typeId] !== $want) {
            // State changed (ticked or unticked): update the record, date and staff member
            Database::iud("UPDATE `students_has_physical_documents` SET
                `is_received` = '" . $want . "',
                `received_date` = " . ($want ? "'" . $now . "'" : "NULL") . ",
                `users_user_id` = '" . $userId . "'
                WHERE `students_student_id` = '" . $studentId . "'
                AND `students_identity_documents_type_id` = '" . $typeId . "'");
        }
        // Unchanged: leave the row alone, so the original date and staff member stay
    }
}
