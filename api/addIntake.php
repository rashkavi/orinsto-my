<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $intake_name = isset($_POST['intake_name']) ? $_POST['intake_name'] : '';
            $start = isset($_POST['start']) ? trim($_POST['start']) : '';
            $end = isset($_POST['end']) ? trim($_POST['end']) : '';
            $letter = isset($_POST['letter']) ? trim($_POST['letter']) : '';
            $sequence_number = isset($_POST['sequence_number']) ? $_POST['sequence_number'] : '';
            $programm_id = isset($_POST['programme_id']) ? $_POST['programme_id'] : '';
            $intake_cover = '';

            if (empty($programm_id)) {
                $status->error = "Invalid programm Id";
            } else if (!is_numeric($programm_id)) {
                $status->error = "Invalid programm Id";
            } else {
                $isValidProgramme = Database::search("SELECT COUNT(*) as 'count' FROM `programmes` WHERE `programme_id`='" . $programm_id . "'");
                $row = $isValidProgramme->fetch_array();
                if ($row["count"] == 0) {
                    $status->error = "Invalid programm Id";
                } else {
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('programme', 2, $programm_id))) {
                        $status->error = "You are not authorized to this action.";
                    } else {
                        if (empty($intake_name)) {
                            $status->name =  "Please enter inatke Name";
                        } else if (strlen($intake_name) > 100) {
                            $status->name =  "Intake Name should be less than 100 characters";
                        } else {
                            $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake`='" . $intake_name . "' AND `programmes_programme_id` = '" . $programm_id . "'");
                            $row = $isValidIntake->fetch_array();
                            if ($row["count"] > 0) {
                                $status->name =  "Intake name already exists in this intake";
                            }
                        }

                        if (empty($letter)) {
                            $status->letter =  "Please select inatke Letter";
                        } else if (strlen($letter) > 20) {
                            $status->letter =  "Intake Letter should be less than 20 characters";
                        } else {
                            $isValidLetter = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_prefix`='" . Database::escape($letter) . "' AND `programmes_programme_id` = '" . $programm_id . "'");
                            $row = $isValidLetter->fetch_array();
                            if ($row["count"] > 0) {
                                $status->letter =  "Intake letter already exists in this programme";
                            }
                        }

                        if (empty($sequence_number)) {
                            $status->seq =  "Please enter inatke Sequence Number";
                        }

                        if (isset($_FILES["intake_cover"])) {
                            $allowed_image_extentions = array("image/jpg", "image/jpeg", "image/png", "image/svg+xml");
                            $file_ex = $_FILES["intake_cover"]["type"];
                            if (!in_array($file_ex, $allowed_image_extentions)) {
                                $status->cover = "Only jpg,jpeg,png and svg allowed";
                            } else {
                                $new_file_extention;
                                if ($file_ex == "image/jpg") {
                                    $new_file_extention = ".jpg";
                                } else if ($file_ex == "image/jpeg") {
                                    $new_file_extention = ".jpeg";
                                } else if ($file_ex == "image/png") {
                                    $new_file_extention = ".png";
                                } else if ($file_ex == "image/svg+xml") {
                                    $new_file_extention = ".svg";
                                }
                                if ($_FILES["intake_cover"]["error"] === 0) {
                                    if ($_FILES["intake_cover"]["size"] < 2 * 1024 * 1024) {
                                        if (getimagesize($_FILES["intake_cover"]["tmp_name"])) {
                                            $intake_cover = uniqid() . $new_file_extention;
                                        } else {
                                            $status->cover = "Invalid image file!";
                                        }
                                    } else {
                                        $status->cover = "Maximum allowed file size is 2MB";
                                    }
                                } else {
                                    $status->cover = "There is an error with the selected image";
                                }
                            }
                        } else {
                            $status->cover = "Please select intake cover";
                        }

                        if (empty($start)) {
                            $status->start =  "Please enter start date";
                        } else if (!CheckDateTime::isFutureDate($start)) {
                            $status->start =  "Start date should be in the future";
                        }

                        if (empty($end)) {
                            $status->end =  "Please enter end date";
                        } else if (!CheckDateTime::isFutureDate($end)) {
                            $status->end =  "End date should be in the future";
                        } else if (date('Y-m-d', strtotime($start)) > date('Y-m-d', strtotime($end))) {
                            $status->end =  "End date should be greater than start date";
                        }


                        if (empty(get_object_vars($status))) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $date = $dateTime->format('Y-m-d H:i:s');

                            $intake_insert_columns = "`intake`,`date_added`,`users_user_id`,`programmes_programme_id`,`intakes_order`,`start_date`,`end_date`";
                            $intake_insert_values = "'" . $intake_name . "','" . $date . "','" . $sessionUserObject['user_id'] . "','" . $programm_id . "','0','" . $start . "','" . $end . "'";

                            if (!empty($intake_cover)) {
                                $intake_insert_columns .= ", `intake_cover`";
                                $intake_insert_values .= ",'" . $intake_cover . "'";
                            }

                            $intake_insert_columns .= ", `intake_prefix`";
                            $intake_insert_values .= ",'" . Database::escape($letter) . "'";

                            $intake_insert_columns .= ", `student_id_sequence_start`";
                            $intake_insert_values .= ",'" . Database::escape($sequence_number) . "'";

                            Database::iud("INSERT INTO `intakes` (" . $intake_insert_columns . ") VALUES (" . $intake_insert_values . ")");

                            if (isset($_FILES["intake_cover"])) {
                                $qimg_path = "../assets/imgs/intakes-covers/" . $intake_cover;
                                move_uploaded_file($_FILES["intake_cover"]["tmp_name"], $qimg_path);
                            }
                            $status->done =  "The intake has been successfully added";
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
