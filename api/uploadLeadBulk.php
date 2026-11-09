<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if (!isset($_FILES["file"])) {
                $status->file = "Please select a file";
            } else {
                $csvMimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/x-csv', 'text/x-csv', 'text/csv', 'application/csv', 'application/excel', 'application/vnd.msexcel', 'text/plain');
                if (empty($_FILES['file']['name']) && !in_array($_FILES['file']['type'], $csvMimes)) {
                    $status->file = "Please select a valid CSV file";
                } else {
                    if (!is_uploaded_file($_FILES['file']['tmp_name'])) {
                        $status->file = "Please select a valid CSV file";
                    } else {
                        $csvFile = fopen($_FILES['file']['tmp_name'], 'r');
                        fgetcsv($csvFile);

                        $preventDuplicates = isset($_POST['prevent_duplicates']) && $_POST['prevent_duplicates'] == '1';

                        $approvedStudebtCount = 0;

                        $csvData = [];

                        while (($line = fgetcsv($csvFile)) !== FALSE) {
                            if (count(array_filter($line)) != 0) {
                                $csvData[] = $line;
                            }
                        }
                        fclose($csvFile);
                        if (count($csvData) == 0) {
                            $status->file = "Please select a valid CSV file";
                        }

                        if (!property_exists($status, 'file') && !property_exists($status, 'error')) {
                            $rowNumber = 2;

                            $linesErrors = [];

                            $programmes = Database::search("SELECT * FROM programmes");
                            $programmesArray = [];
                            while ($programme = $programmes->fetch_assoc()) {
                                $programmesArray[] = $programme["programme_id"];
                            }

                            $leadSource = Database::search("SELECT * FROM lead_source");
                            $leadSourceArray = [];
                            while ($leadSourceData = $leadSource->fetch_assoc()) {
                                $leadSourceArray[] = $leadSourceData["lead_source_id"];
                            }

                            $institutes = Database::search("SELECT * FROM `institutes`");
                            $institutesArray = [];
                            while ($institutesData = $institutes->fetch_assoc()) {
                                $institutesArray[] = $institutesData["institute_id"];
                            }

                            $institutesBranch = Database::search("SELECT * FROM `institute_has_branches`");
                            $institutesBranchArray = [];
                            while ($institutesBranchData = $institutesBranch->fetch_assoc()) {
                                $institutesBranchArray[] = $institutesBranchData["institute_has_branch_id"];
                            }

                            $counsilors = Database::search("SELECT * FROM users WHERE `user_roles_user_role_id` IN (7,8,11,12,21) 
                                    AND `status_status_id`='1'");

                            $counsilorsArray = [];
                            while ($counsilersData = $counsilors->fetch_assoc()) {
                                $counsilorsArray[] = $counsilersData["user_id"];
                            }

                            $mobileNumbers = [];

                            foreach ($csvData as $data) {
                                $fullName = $data[0];
                                $dob = $data[1];
                                $gender = $data[2];
                                $address = $data[3];
                                $email = $data[4];
                                $mobile = $data[5];
                                $whatsapp = $data[6];
                                $school = $data[7];
                                $grade = $data[8];
                                $college = $data[9];
                                $programme = $data[10];
                                $source = $data[11];
                                $institute = $data[12];
                                $branch = $data[13];
                                $note = $data[14];
                                $assignUser = $data[15];

                                $lineErrors = [];

                                $lineErrors = [];

                                // Full Name
                                if (empty($fullName)) {
                                    $lineErrors[] = "Row {$rowNumber}: Full name is required";
                                } else if (strlen($fullName) > 100) {
                                    $lineErrors[] = "Row {$rowNumber}: Full name should be less than 100 characters";
                                }

                                // Address
                                if (!empty($address) && strlen($address) > 150) {
                                    $lineErrors[] = "Row {$rowNumber}: Address should be less than 150 characters";
                                }

                                // School
                                if (!empty($school) && strlen($school) > 100) {
                                    $lineErrors[] = "Row {$rowNumber}: School name should be less than 100 characters";
                                }

                                // Grade
                                if (!empty($grade) && strlen($grade) > 20) {
                                    $lineErrors[] = "Row {$rowNumber}: Grade should be less than 20 characters";
                                }

                                // Email
                                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid email format";
                                }

                                // Mobile
                                if (empty($mobile)) {
                                    $lineErrors[] = "Row {$rowNumber}: Mobile number is required";
                                } else if (!normalizePhoneNumber($mobile)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid mobile number";
                                } else if (in_array($mobile, $mobileNumbers) && !$preventDuplicates) {
                                    $duplicateIndex = array_search($mobile, $mobileNumbers);
                                    $lineErrors[] = "Data Row " . $rowNumber . ": Mobile number already has been used in this CSV at line " . ($duplicateIndex + 1) . " student";
                                } else if (!$preventDuplicates) {
                                    $isValidMobile = Database::search("SELECT * FROM `leads` WHERE `contact`='" . $mobile . "'");
                                    if ($isValidMobile->num_rows > 0) {
                                        $lineErrors[] = "Row {$rowNumber}: Mobile number already exists";
                                    }
                                }

                                // WhatsApp
                                if (!empty($whatsapp) && !normalizePhoneNumber($whatsapp)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid WhatsApp number";
                                }

                                // DOB (optional but recommended)
                                if (!empty($dob) && !strtotime($dob)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid date of birth";
                                }

                                // Gender (assuming 1=Male, 2=Female, 3=Other)
                                if (!empty($gender) && !in_array($gender, ['1', '2'])) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid gender value";
                                }

                                // Note (optional limit)
                                if (!empty($note) && strlen($note) > 255) {
                                    $lineErrors[] = "Row {$rowNumber}: Note should be less than 255 characters";
                                }

                                if (!empty($programme) && !in_array($programme, $programmesArray)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid Programme ID";
                                }

                                if (!empty($source) && !in_array($source, $leadSourceArray)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid Lead Source ID";
                                }

                                if ((int)$source == 13 && !empty($institute) && !in_array($institute, $institutesArray)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid Institute ID";
                                }

                                if ($source == 13 && $institute == 3 && !empty($branch) && !in_array($branch, $institutesBranch)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid Institute Branch ID";
                                }

                                if (!empty($assignUser) && !in_array($assignUser, $counsilorsArray)) {
                                    $lineErrors[] = "Row {$rowNumber}: Invalid Counsiler ID";
                                }

                                $rowNumber++;
                                if (count($lineErrors) > 0) {
                                    $linesErrors[] = $lineErrors;
                                }

                                $mobileNumbers[] = $mobile;
                            }
                            if (!empty($linesErrors)) {
                                $status->dataErrors = $linesErrors;
                            } else {

                                $userIsMarketingOfficer = Database::search("SELECT * FROM `users` 
                                WHERE `user_id`='" . $sessionUserObject['user_id'] . "' AND `user_roles_user_role_id` IN  (7,8,11,12,21)");

                                $userIsMarketingOfficer = $userIsMarketingOfficer->num_rows > 0 ? true : false;

                                foreach ($csvData as $data) {

                                    $d = new DateTime();
                                    $tz = new DateTimeZone("Asia/Colombo");
                                    $d->setTimezone($tz);
                                    $date = $d->format("Y-m-d H:i:s");

                                    $fullName = $data[0];
                                    $dob = $data[1];
                                    $gender = $data[2];
                                    $address = $data[3];
                                    $email = $data[4];
                                    $mobile = $data[5];
                                    $whatsapp = $data[6];
                                    $school = $data[7];
                                    $grade = $data[8];
                                    $college = $data[9];
                                    $programme = $data[10];
                                    $source = $data[11];
                                    $institute = $data[12];
                                    $branch = $data[13];
                                    $note = $data[14];
                                    $assignUser = $data[15];

                                    $columns = "`name`,`lead_status_lead_status_id`,`date_added`,`added_by`";
                                    $values  = "'" . $fullName . "','1','" . $date . "','" . $sessionUserObject['user_id'] . "'";

                                    if (!empty($gender)) {
                                        $columns .= ",`gender_gender_id`";
                                        $values  .= ",'" . $gender . "'";
                                    }

                                    if (!empty($dob)) {
                                        $columns .= ",`date_of_birth`";
                                        $values  .= ",'" . $dob . "'";
                                    }

                                    if (!empty($address)) {
                                        $columns .= ",`address`";
                                        $values  .= ",'" . $address . "'";
                                    }

                                    if (!empty($email)) {
                                        $columns .= ",`email`";
                                        $values  .= ",'" . $email . "'";
                                    }

                                    if (!empty($mobile)) {
                                        $columns .= ",`contact`";
                                        $values  .= ",'" . normalizePhoneNumber($mobile) . "'";
                                    }

                                    if (!empty($whatsapp)) {
                                        $columns .= ",`whatsapp`";
                                        $values  .= ",'" . normalizePhoneNumber($whatsapp) . "'";
                                    }

                                    if (!empty($school)) {
                                        $columns .= ",`school`";
                                        $values  .= ",'" . $school . "'";
                                    }

                                    if (!empty($grade)) {
                                        $columns .= ",`grade`";
                                        $values  .= ",'" . $grade . "'";
                                    }

                                    if (!empty($college)) {
                                        $columns .= ",`college`";
                                        $values  .= ",'" . $college . "'";
                                    }

                                    if (!empty($programme)) {
                                        $columns .= ",`programmes_programme_id`";
                                        $values  .= ",'" . $programme . "'";
                                    }

                                    if (!empty($source)) {
                                        $columns .= ",`lead_source_lead_source_id`";
                                        $values  .= ",'" . $source . "'";
                                    }

                                    if (!empty($note)) {
                                        $columns .= ",`note`";
                                        $values  .= ",'" . Database::escape($note) . "'";
                                    }

                                    if ($source == 13 && !empty($institute)) {
                                        $columns .= ",`institutes_institute_id`";
                                        $values  .= ",'" . $institute . "'";
                                    }

                                    if ($source == 13 && $institute == 3 && !empty($branch)) {
                                        $columns .= ",`institute_has_branches_institute_has_branch_id`";
                                        $values  .= ",'" . $branch . "'";
                                    }

                                    if (!empty($assignUser)) {
                                        $columns .= ",`users_user_id`";
                                        $values  .= ",'" . $assignUser . "'";
                                    } else  if ($userIsMarketingOfficer) {
                                        $columns .= ",`users_user_id`";
                                        $values  .= ",'" . $sessionUserObject['user_id'] . "'";
                                    }

                                    Database::iud("INSERT INTO `leads` (" . $columns . ") VALUES (" . $values . ")");
                                }

                                $status->done = "All leads added successfully";
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

function normalizePhoneNumber(string $number, string $defaultCountryCode = '94')
{
    $number = preg_replace('/[^\d+]/', '', $number);

    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    if ($defaultCountryCode === '94' && preg_match('/^0[7][0-9]{8}$/', $number)) {
        return '+94' . substr($number, 1);
    }

    if (preg_match('/^[1-9][0-9]{7,14}$/', $number)) {
        return '+' . $number;
    }
    if (preg_match('/^\+[1-9][0-9]{7,14}$/', $number)) {
        return $number;
    }

    return false;
}
