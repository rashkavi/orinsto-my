<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/classes/StudentIdUpdater.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $intake_id = isset($_POST['intake_id']) ? $_POST['intake_id'] : '';
            if (empty($intake_id)) {
                $status->error = "Invalid intake Id";
            } else if (!is_numeric($intake_id)) {
                $status->error = "Invalid intake Id";
            } else {
                $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id`='" . $intake_id . "'");
                if ($isValidIntake->num_rows == 0) {
                    $status->error = "Invalid intake Id";
                } else {
                    $intakeData = $isValidIntake->fetch_assoc();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('programme', 10, $intakeData['programmes_programme_id']))) {
                        $status->error = "You are not authorized to this action.";
                    } else {
                        $intake_name = isset($_POST['intake_name']) ? trim($_POST['intake_name']) : '';
                        $start = isset($_POST['start']) ? trim($_POST['start']) : '';
                        $end = isset($_POST['end']) ? trim($_POST['end']) : '';
                        $letter = isset($_POST['letter']) ? trim($_POST['letter']) : '';
                        $sequence_number = isset($_POST['sequence_number']) ? $_POST['sequence_number'] : '';
                        $intake_cover;
                        if (empty($intake_name)) {
                            $status->name =  "Please enter batch Name";
                        } else if (strlen($intake_name) > 100) {
                            $status->name =  "Batch name should be less than 100 characters";
                        } else {

                            $isValidIntake = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake`='" . $intake_name . "' AND `intake_id` != '" . $intake_id . "' AND `programmes_programme_id` = '" . $intakeData['programmes_programme_id'] . "'");
                            $row = $isValidIntake->fetch_assoc();
                            if ($row['count'] > 0) {
                                $status->name =  "Batch name already exists in this intake";
                            }
                        }

                        if (empty($start)) {
                            $status->start =  "Please enter start date";
                        } else if ($intakeData['start_date'] != $start && !CheckDateTime::isFutureDate($start)) {
                            $status->start =  "Start date should be in the future";
                        }

                        if (empty($end)) {
                            $status->end =  "Please enter end date";
                        } else if ($intakeData['end_date'] != $end && !CheckDateTime::isFutureDate($end)) {
                            $status->end =  "End date should be in the future";
                        } else if (date('Y-m-d', strtotime($start)) > date('Y-m-d', strtotime($end))) {
                            $status->end =  "End date should be greater than start date";
                        }

                        if (empty($letter)) {
                            $status->letter =  "Please select inatke Letter";
                        } else if (strlen($letter) > 20) {
                            $status->letter =  "Intake Letter should be less than 20 characters";
                        } else {
                            $isValidLetter = Database::search("SELECT COUNT(*) as 'count' FROM `intakes` WHERE `intake_prefix`='" . Database::escape($letter) . "' AND `intake_id` != '" . $intake_id . "'");
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
                        }

                        if (empty(get_object_vars($status))) {
                            $intake_update_query = "`intake`='" . $intake_name . "', `start_date`='" . $start . "', `end_date`='" . $end . "'";

                            if (!empty($intake_cover)) {
                                $intake_update_query .= ", `intake_cover`='" . $intake_cover . "'";
                            }

                            $intake_update_query .= ",`intake_prefix`='" . Database::escape($letter) . "'";
                            $intake_update_query .= ",`student_id_sequence_start`='" . Database::escape($sequence_number) . "'";

                            Database::iud("UPDATE `intakes` SET " . $intake_update_query . " WHERE `intake_id`='" . $intake_id . "'");

                            if (isset($_FILES["intake_cover"])) {
                                $cover_path = "../assets/imgs/intakes-covers/";
                                $qimg_path = $cover_path . $intake_cover;
                                move_uploaded_file($_FILES["intake_cover"]["tmp_name"], $qimg_path);

                                if (!empty($intakeData['intake_cover']) && file_exists($cover_path . $intakeData['intake_cover'])) {
                                    unlink($cover_path . $intakeData['intake_cover']);
                                }
                            }

                            if ($intakeData['intake_prefix'] != $letter || $intakeData['student_id_sequence_start'] != $sequence_number) {
                                // ── Before saving the intake ──────────────────────────────────────
                                $oldPrefix        = $intakeData['intake_prefix'];
                                $oldSequenceStart = (int)$intakeData['student_id_sequence_start'];

                                // ... your existing intake UPDATE query here ...

                                // ── After saving the intake ───────────────────────────────────────
                                $newPrefix        = $letter;
                                $newSequenceStart = (int)$sequence_number;

                                $result = StudentIdUpdater::onIntakeLetterChange(
                                    $intake_id,
                                    $oldPrefix,
                                    $newPrefix,
                                    $oldSequenceStart,
                                    $newSequenceStart
                                );
                            }
                            $status->done =  "The intake has been successfully updated";
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
