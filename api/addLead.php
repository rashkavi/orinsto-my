<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $full_name        = isset($decoded['full_name']) ? trim($decoded['full_name']) : '';
                    $dob              = isset($decoded['dob']) ? $decoded['dob'] : '';
                    $gender           = isset($decoded['gender']) ? $decoded['gender'] : '0';
                    $address          = isset($decoded['address']) ? trim($decoded['address']) : '';
                    $email            = isset($decoded['email']) ? trim($decoded['email']) : '';
                    $mobile           = isset($decoded['mobile']) ? trim($decoded['mobile']) : '';
                    $whatsapp         = isset($decoded['whatsapp']) ? trim($decoded['whatsapp']) : '';

                    $school           = isset($decoded['school']) ? trim($decoded['school']) : '';
                    $grade            = isset($decoded['grade']) ? trim($decoded['grade']) : '';
                    $college          = isset($decoded['college']) ? trim($decoded['college']) : '';

                    $programme_id     = isset($decoded['programme_id']) ? $decoded['programme_id'] : '0';
                    $lead_source_id   = isset($decoded['lead_source_id']) ? $decoded['lead_source_id'] : '0';
                    $institute        = isset($decoded['institute']) ? $decoded['institute'] : '0';
                    $institute_branch = isset($decoded['institute_branch']) ? $decoded['institute_branch'] : '0';
                    $user_id          = isset($decoded['user_id']) ? $decoded['user_id'] : '0';

                    $note             = isset($decoded['note']) ? trim($decoded['note']) : '';

                    if (empty($full_name)) {
                        $status->full_name = "Full name is required";
                    } else if (strlen($full_name) > 100) {
                        $status->full_name = "Full name should be less than 100 characters";
                    }

                    if (!empty($address) && strlen($address) > 150) {
                        $status->address = "Address should be less than 150 characters";
                    }

                    if (!empty($school) && strlen($school) > 100) {
                        $status->school = "School name should be less than 100 characters";
                    }

                    if (!empty($grade) && strlen($grade) > 20) {
                        $status->grade = "Grade should be less than 20 characters";
                    }

                    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $status->email = "Invalid email format";
                    }

                    if (empty($mobile)) {
                        $status->mobile = "Please enter mobile number";
                    } else if (!empty($mobile) && !normalizePhoneNumber($mobile)) {
                        $status->mobile = "Invalid mobile number";
                    }

                    if (!empty($whatsapp) && !normalizePhoneNumber($whatsapp)) {
                        $status->whatsapp = "Invalid whatsapp number";
                    }

                    if (!empty($user_id)) {
                        $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` 
                        WHERE `user_id` = '" . $user_id . "' AND `user_roles_user_role_id` IN (7,8,11,12,21) AND `status_status_id` = '1'");
                        $row = $isValidUser->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->user_id = "Please select a valid user";
                        }
                    }

                    if (empty($lead_source_id)) {
                        $status->lead_source_id = "Please select a lead source";
                    }
                    if ($lead_source_id == 13) {
                        if (empty($institute)) {
                            $status->institute = "Please select an institute";
                        } else {
                            $isInstituteHasBranch = Database::search("SELECT COUNT(*) as 'count' FROM `institute_has_branches` WHERE `institutes_institute_id` = '" . $institute . "'");
                            $row = $isInstituteHasBranch->fetch_assoc();
                            if ($row['count'] > 0 && empty($institute_branch)) {
                                $status->institute_branch = "Please select a institute location";
                            }
                        }
                    }

                    $requiredProperties = [
                        'full_name',
                        'dob',
                        'gender',
                        'address',
                        'email',
                        'mobile',
                        'whatsapp',
                        'school',
                        'grade',
                        'college',
                        'programme_id',
                        'lead_source_id',
                        'lead_category_id',
                        'user_id',
                        'institute',
                        'institute_branch'
                    ];

                    $allFieldsPresent = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allFieldsPresent = false;
                            break;
                        }
                    }

                    if ($allFieldsPresent) {
                        $d = new DateTime();
                        $tz = new DateTimeZone("Asia/Colombo");
                        $d->setTimezone($tz);
                        $date = $d->format("Y-m-d H:i:s");

                        $columns = [];
                        $values  = [];

                        // required
                        $columns[] = "`name`";
                        $values[]  = "'" . Database::escape($full_name) . "'";

                        $columns[] = "`lead_status_lead_status_id`";
                        $values[]  = "'1'";

                        $columns[] = "`date_added`";
                        $values[]  = "'" . Database::escape($date) . "'";

                        $columns[] = "`added_by`";
                        $values[]  = "'" . Database::escape($sessionUserObject['user_id']) . "'";

                        // optional fields
                        if (!empty($gender)) {
                            $columns[] = "`gender_gender_id`";
                            $values[]  = "'" . Database::escape($gender) . "'";
                        }

                        if (!empty($dob)) {
                            $columns[] = "`date_of_birth`";
                            $values[]  = "'" . Database::escape($dob) . "'";
                        }

                        if (!empty($address)) {
                            $columns[] = "`address`";
                            $values[]  = "'" . Database::escape($address) . "'";
                        }

                        if (!empty($email)) {
                            $columns[] = "`email`";
                            $values[]  = "'" . Database::escape($email) . "'";
                        }

                        if (!empty($mobile)) {
                            $columns[] = "`contact`";
                            $values[]  = "'" . normalizePhoneNumber($mobile) . "'";
                        }

                        if (!empty($whatsapp)) {
                            $columns[] = "`whatsapp`";
                            $values[]  = "'" . normalizePhoneNumber($whatsapp) . "'";
                        }

                        if (!empty($school)) {
                            $columns[] = "`school`";
                            $values[]  = "'" . Database::escape($school) . "'";
                        }

                        if (!empty($grade)) {
                            $columns[] = "`grade`";
                            $values[]  = "'" . Database::escape($grade) . "'";
                        }

                        if (!empty($college)) {
                            $columns[] = "`college`";
                            $values[]  = "'" . Database::escape($college) . "'";
                        }

                        if (!empty($programme_id)) {
                            $columns[] = "`programmes_programme_id`";
                            $values[]  = "'" . Database::escape($programme_id) . "'";
                        }

                        if (!empty($lead_source_id)) {
                            $columns[] = "`lead_source_lead_source_id`";
                            $values[]  = "'" . Database::escape($lead_source_id) . "'";

                            if ($lead_source_id == 13) {
                                $columns[] = "`institutes_institute_id`";
                                $values[]  = "'" . Database::escape($institute) . "'";

                                if (!empty($institute_branch)) {
                                    $columns[] = "`institute_has_branches_institute_has_branch_id`";
                                    $values[]  = "'" . Database::escape($institute_branch) . "'";
                                }
                            }
                        }

                        if (!empty($note)) {
                            $columns[] = "`note`";
                            $values[]  = "'" . Database::escape($note) . "'";
                        }

                        if (!empty($user_id)) {
                            $columns[] = "`users_user_id`";
                            $values[]  = "'" . $user_id . "'";
                        }

                        $query = "INSERT INTO `leads` (" . implode(",", $columns) . ")
                                  VALUES (" . implode(",", $values) . ")";

                        Database::iud($query);
                        $status->done = 'Lead Added Successfully!';
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid request method';
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
