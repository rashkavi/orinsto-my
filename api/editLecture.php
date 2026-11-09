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

            $lecture_id = isset($_POST['lecture_id']) ? trim($_POST['lecture_id']) : 0;
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $lecturer_id = isset($_POST['lecturer_id']) ? trim($_POST['lecturer_id']) : 0;
            $lecture_date = isset($_POST['lecture_date']) ? trim($_POST['lecture_date']) : '';
            $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
            $time_table_status = isset($_POST['status']) ? trim($_POST['status']) : 0;
            $physical = isset($_POST['physical']) ? trim($_POST['physical']) : false;
            $online = isset($_POST['online']) ? trim($_POST['online']) : false;
            $recording = isset($_POST['recording']) ? trim($_POST['recording']) : false;
            $link = isset($_POST['link']) ? trim($_POST['link']) : '';

            // NEW: cancellation reason + postponed date/time fields
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
            $postponed_date = isset($_POST['postponed_date']) ? trim($_POST['postponed_date']) : '';
            $postponed_start_time = isset($_POST['postponed_start_time']) ? trim($_POST['postponed_start_time']) : '';
            $postponed_end_time = isset($_POST['postponed_end_time']) ? trim($_POST['postponed_end_time']) : '';
            $linked_time_table_id = (isset($_POST['linked_time_table_id']) && !empty($_POST['linked_time_table_id'])) ? trim($_POST['linked_time_table_id']) : 0;

            if (empty($lecture_id) || $lecture_id == 0) {
                $status->error = "Invalid lecture Id";
            } else {
                $isValidLecture = Database::search("SELECT * FROM `time_table`
                INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                
                WHERE `time_table_id`='" . $lecture_id . "'");
                if ($isValidLecture->num_rows == 0) {
                    $status->error = "Invalid lecture Id";
                } else {
                    $lectureData = $isValidLecture->fetch_assoc();
                    $subject_id = $lectureData['subjects_subject_id'];

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 10) || UserPermissions::hasPermission('lecture_schedule', 10, $lectureData['subjects_subject_id']))) {
                        $status->error = 'You are not authorized to edit scheduled lectures';
                    } else {

                        // NEW: resolve the selected status id to its label so we know
                        // whether we're dealing with a Cancelled / Postponed lecture.
                        $selectedStatusName = '';
                        if (!empty($time_table_status) && $time_table_status != 0) {
                            $statusNameRow = Database::search("SELECT `time_table_status` FROM `time_table_status` WHERE `time_table_status_id` = '" . Database::escape($time_table_status) . "'");
                            if ($statusNameRow->num_rows > 0) {
                                $selectedStatusName = strtolower(trim($statusNameRow->fetch_assoc()['time_table_status']));
                            }
                        }

                        if (empty($title)) {
                            $status->title = 'Please enter a title';
                        } else if (strlen($title) > 50) {
                            $status->title = 'Title should be less than 50 characters';
                        }

                        if ($lecturer_id == 0) {
                            $status->lecturer = 'Please select a lecturer';
                        } else if ($lecturer_id != 0 && $subject_id != 0) {
                            $isValidLecturer = Database::search("SELECT * FROM `lecturers` WHERE `lecturers_id` = '" . $lecturer_id . "' AND `subjects_subject_id` = '" . $subject_id . "'");
                            if ($isValidLecturer->num_rows == 0) {
                                $status->lecturer = 'This lecturer is not assigned to this subject';
                            } else {
                                $lecturerData = $isValidLecturer->fetch_assoc();
                            }
                        }

                        if (empty($lecture_date)) {
                            $status->date = 'Please select a lecture date';
                        }
                        if (empty($start_time)) {
                            $status->start_time = 'Please select a start time';
                        }
                        if (empty($end_time)) {
                            $status->end_time = 'Please select an end time';
                        }

                        if (($physical === false) && ($online === false) && ($recording === false)) {
                            $status->error = "Please select lecture type";
                        }

                        if ((($online === true) || ($recording === true)) && empty($link)) {
                            $status->link = 'Please enter a link1';
                        }

                        if ($time_table_status == 0) {
                            $status->time_table_status = 'Please select a time table status';
                        }

                        // NEW: Cancelled status requires a reason
                        if ($selectedStatusName === 'cancelled') {
                            if (empty($reason)) {
                                $status->reason = 'Please enter a cancellation reason';
                            } else if (strlen($reason) > 500) {
                                $status->reason = 'Reason should be less than 500 characters';
                            }
                        }

                        // NEW: Postponed status requires a new date/start/end and must not clash
                        // with the same conflict rules used for the main schedule fields.
                        if ($selectedStatusName === 'postponed') {
                            if (empty($postponed_date)) {
                                $status->postponed_date = 'Please select a postponed date';
                            }
                            if (empty($postponed_start_time)) {
                                $status->postponed_start_time = 'Please select a postponed start time';
                            }
                            if (empty($postponed_end_time)) {
                                $status->postponed_end_time = 'Please select a postponed end time';
                            }

                            if (!empty($postponed_date) && !empty($postponed_start_time) && !empty($postponed_end_time)) {
                                if (isset($lecturerData) && $lecturerData) {
                                    $isAvailableLecturerPostponed = Database::search("SELECT COUNT(*) FROM `time_table`
                                        INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                        WHERE `lecturers`.`users_user_id` = '" . Database::escape($lecturerData['users_user_id']) . "'
                                        AND `time_table`.`date` = '" . Database::escape($postponed_date) . "'
                                        AND `time_table_id` != '" . Database::escape($lecture_id) . "'
                                        AND `time_table_id` != '" . Database::escape($linked_time_table_id) . "'
                                        AND ((`time_table`.`start_time` BETWEEN '" . Database::escape($postponed_start_time) . "' AND '" . Database::escape($postponed_end_time) . "')
                                        OR (`time_table`.`end_time` BETWEEN '" . Database::escape($postponed_start_time) . "' AND '" . Database::escape($postponed_end_time) . "'))");
                                    $row = $isAvailableLecturerPostponed->fetch_assoc();
                                    if ($row['COUNT(*)'] > 0) {
                                        $status->postponed_start_time = 'The selected postponed time is already scheduled for this lecturer.';
                                    }
                                }

                                $isAvailableIntakePostponed = Database::search("SELECT COUNT(*) FROM `time_table`
                                    INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                    WHERE `levels`.`level_id` = '" . Database::escape($lectureData['levels_level_id']) . "'
                                    AND `time_table_id` != '" . Database::escape($lecture_id) . "'
                                    AND `time_table_id` != '" . Database::escape($linked_time_table_id) . "'
                                    AND `subject_type_subject_type_id` = '1'
                                    AND `date` = '" . Database::escape($postponed_date) . "'
                                    AND ((`start_time` BETWEEN '" . Database::escape($postponed_start_time) . "' AND '" . Database::escape($postponed_end_time) . "')
                                    OR (`end_time` BETWEEN '" . Database::escape($postponed_start_time) . "' AND '" . Database::escape($postponed_end_time) . "'))");
                                $row = $isAvailableIntakePostponed->fetch_assoc();
                                if ($row['COUNT(*)'] > 0) {
                                    $status->postponed_start_time = 'Postponed time slot already booked for this Intake.';
                                    $status->postponed_end_time = 'Postponed time slot already booked for this Intake.';
                                }

                                $isAvailableSubjectPostponed = Database::search("SELECT COUNT(*) FROM `time_table`
                                    WHERE `subjects_subject_id` = '" . Database::escape($subject_id) . "'
                                    AND `time_table_id` != '" . Database::escape($lecture_id) . "'
                                    AND `time_table_id` != '" . Database::escape($linked_time_table_id) . "'
                                    AND `date` = '" . Database::escape($postponed_date) . "'
                                    AND ((`start_time` BETWEEN '" . Database::escape($postponed_start_time) . "' AND '" . Database::escape($postponed_end_time) . "')
                                    OR (`end_time` BETWEEN '" . Database::escape($postponed_start_time) . "' AND '" . Database::escape($postponed_end_time) . "'))");
                                $row = $isAvailableSubjectPostponed->fetch_assoc();
                                if ($row['COUNT(*)'] > 0) {
                                    $status->postponed_start_time = 'Postponed time slot already booked for this Subject.';
                                    $status->postponed_end_time = 'Postponed time slot already booked for this Subject.';
                                }
                            }
                        }

                        $tutes = array();

                        if (isset($_FILES["tutes"])) {
                            $allowed_tute_extensions = array(
                                "application/pdf",
                            );

                            foreach ($_FILES["tutes"]["name"] as $key => $tute_name) {
                                $tute_ex = $_FILES["tutes"]["type"][$key];

                                if (!in_array($tute_ex, $allowed_tute_extensions)) {
                                    $status->tute[] = "File '$tute_name' is not a valid type. Only PDF, DOC, and DOCX files are allowed.";
                                } else {
                                    $new_tute_extension = '';
                                    switch ($tute_ex) {
                                        case "application/pdf":
                                            $new_tute_extension = ".pdf";
                                            break;
                                        case "application/msword":
                                            $new_tute_extension = ".doc";
                                            break;
                                        case "application/vnd.openxmlformats-officedocument.wordprocessingml.document":
                                            $new_tute_extension = ".docx";
                                            break;
                                    }

                                    if ($_FILES["tutes"]["error"][$key] === 0) {
                                        if ($_FILES["tutes"]["size"][$key] <= 10 * 1024 * 1024) {
                                            $tute = uniqid() . $new_tute_extension;
                                            array_push($tutes, $tute);
                                        } else {
                                            $status->tute[] = "File '$tute_name' exceeds the maximum allowed size of 10MB.";
                                        }
                                    } else {
                                        $status->tute[] = "There was an error uploading the file '$tute_name'.";
                                    }
                                }
                            }
                        }

                        if ($lecturer_id != 0 && !empty($lecture_date) && !empty($start_time) && !empty($end_time)) {

                            $isAvailableLecturer = Database::search("SELECT COUNT(*) FROM `time_table`
                                                                                    INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                                                    WHERE `lecturers`.`users_user_id` = '" . $lecturerData['users_user_id'] . "'
                                                                                    AND `time_table`.`date` = '" . $lecture_date . "'
                                                                                    AND `time_table_id` !='" . $lecture_id . "'
                                                                                    AND ((`time_table`.`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                    OR (`time_table`.`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                    
                                                                                    ");

                            $row = $isAvailableLecturer->fetch_assoc();
                            if ($row['COUNT(*)'] > 0) {
                                $status->lecturer = 'The selected time is already scheduled for this lecturer.';
                            }
                        }

                        if (!empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                            $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) FROM `time_table`
                                                                                            INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                                            INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                                            INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                                            WHERE `levels`.`level_id` = '" . $lectureData['levels_level_id'] . "'
                                                                                            AND `time_table_id` !='" . $lecture_id . "'
                                                                                            AND `subject_type_subject_type_id` = '1'
                                                                                              AND `date` = '" . $lecture_date . "'
                                                                                              AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                              OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                             ");
                            $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                            if ($row['COUNT(*)'] > 0) {
                                $status->start_time = 'Time slot already booked for this Intake.';
                                $status->end_time = 'Time slot already booked for this Intake.';
                            }
                        }

                        if (!empty($lecture_date) && !empty($start_time) && !empty($end_time)) {
                            $isAvailableSelectTimeInIntake = Database::search("SELECT COUNT(*) FROM `time_table`
                                                                                              WHERE `subjects_subject_id` = '" . $subject_id . "'
                                                                                                 AND `time_table_id`!='" . $lecture_id . "' 
                                                                                              AND `date` = '" . $lecture_date . "'
                                                                                              AND ((`start_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "')
                                                                                              OR (`end_time` BETWEEN '" . $start_time . "' AND '" . $end_time . "'))
                                                                                                                                                                             ");
                            $row = $isAvailableSelectTimeInIntake->fetch_assoc();
                            if ($row['COUNT(*)'] > 0) {
                                $status->start_time = 'Time slot already booked for this Subject.';
                                $status->end_time = 'Time slot already booked for this Subject.';
                            }
                        }

                        if (empty(get_object_vars($status))) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $addTime = $dateTime->format('Y-m-d H:i:s');

                            $start = new DateTime($start_time);
                            $end = new DateTime($end_time);

                            $interval = $start->diff($end);

                            $duration = ($interval->h * 60) + $interval->i;

                            // NEW: `reason` is only kept when the lecture is being marked Cancelled,
                            // otherwise it's cleared out.
                            $reasonToStore = ($selectedStatusName === 'cancelled') ? Database::escape($reason) : '';

                            $time_table_update_query = "`lecturers_lecturers_id`='" . $lecturer_id . "',`time_table_status_time_table_status_id`='" . $time_table_status . "'
                            ,`date`='" . $lecture_date . "',`start_time`='" . $start_time . "',`end_time`='" . $end_time . "',`duration`='" . $duration . "',`lecture_title`='" . $title . "',`reason`='" . $reasonToStore . "'";

                            if (filter_var($physical, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='1',`lecture`=''";
                            }

                            if (filter_var($online, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='2',`lecture`='" . $link . "'";
                            }

                            if (filter_var($recording, FILTER_VALIDATE_BOOLEAN)) {
                                $time_table_update_query .= ",`lecture_type_lecture_type_id`='3',`lecture`='" . $link . "'";
                            }

                            try {
                                Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . $lecture_id . "'");

                                // NEW: keep the postponed occurrence (a separate `time_table` row
                                // linked back via `linked_time_table_id`) in sync with the status.
                                if ($selectedStatusName === 'postponed') {
                                    $postponedStartDt = new DateTime($postponed_start_time);
                                    $postponedEndDt = new DateTime($postponed_end_time);
                                    $postponedInterval = $postponedStartDt->diff($postponedEndDt);
                                    $postponedDuration = ($postponedInterval->h * 60) + $postponedInterval->i;

                                    $scheduledStatusId = findStatusIdByName('scheduled', $time_table_status);

                                    if (!empty($linked_time_table_id)) {
                                        $existingLinked = Database::search("SELECT * FROM `time_table` WHERE `time_table_id`='" . Database::escape($linked_time_table_id) . "' AND `linked_time_table_id`='" . Database::escape($lecture_id) . "'");
                                    } else {
                                        $existingLinked = Database::search("SELECT * FROM `time_table` WHERE `linked_time_table_id`='" . Database::escape($lecture_id) . "'");
                                    }

                                    if ($existingLinked->num_rows > 0) {
                                        $existingLinkedData = $existingLinked->fetch_assoc();

                                        Database::iud("UPDATE `time_table` SET
                                            `lecturers_lecturers_id`='" . $lecturer_id . "',
                                            `date`='" . Database::escape($postponed_date) . "',
                                            `start_time`='" . Database::escape($postponed_start_time) . "',
                                            `end_time`='" . Database::escape($postponed_end_time) . "',
                                            `duration`='" . $postponedDuration . "',
                                            `lecture_title`='" . Database::escape($title) . "'
                                            WHERE `time_table_id`='" . $existingLinkedData['time_table_id'] . "'");

                                        Database::iud("INSERT INTO `time_table_log`
                                            (`time_table_time_table_id`,`action`,`field_changed`,`old_value`,`new_value`,`subjects_subject_id`,`changed_by`,`changed_at`,`remarks`)
                                            VALUES
                                            ('" . $lecture_id . "','STATUS_CHANGE','postponed_date/start_time/end_time',
                                            '" . Database::escape($lectureData['date'] . ' ' . $lectureData['start_time'] . '-' . $lectureData['end_time']) . "',
                                            '" . Database::escape($postponed_date . ' ' . $postponed_start_time . '-' . $postponed_end_time) . "',
                                            '" . $subject_id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','Postponed lecture rescheduled')");
                                    } else {
                                        $linkedInsertColumns = "`date`,`start_time`,`end_time`,`duration`,`lecture_type_lecture_type_id`,`lecture`,`subjects_subject_id`,`users_user_id`,`date_added`,`time_table_status_time_table_status_id`,`lecturers_lecturers_id`,`lecture_title`,`linked_time_table_id`";
                                        $linkedInsertValues = "'" . Database::escape($postponed_date) . "',
                                            '" . Database::escape($postponed_start_time) . "',
                                            '" . Database::escape($postponed_end_time) . "',
                                            '" . $postponedDuration . "',
                                            '" . $lectureData['lecture_type_lecture_type_id'] . "',
                                            '" . Database::escape($lectureData['lecture']) . "',
                                            '" . $subject_id . "',
                                            '" . $sessionUserObject['user_id'] . "',
                                            '" . $addTime . "',
                                            '1',
                                            '" . $lecturer_id . "',
                                            '" . Database::escape($title) . "',
                                            '" . $lecture_id . "'";

                                        Database::iud("INSERT INTO `time_table` (" . $linkedInsertColumns . ") VALUES (" . $linkedInsertValues . ")");

                                        Database::iud("INSERT INTO `time_table_log`
                                            (`time_table_time_table_id`,`action`,`field_changed`,`old_value`,`new_value`,`subjects_subject_id`,`changed_by`,`changed_at`,`remarks`)
                                            VALUES
                                            ('" . $lecture_id . "','POSTPONE','date/start_time/end_time',
                                            '" . Database::escape($lectureData['date'] . ' ' . $lectureData['start_time'] . '-' . $lectureData['end_time']) . "',
                                            '" . Database::escape($postponed_date . ' ' . $postponed_start_time . '-' . $postponed_end_time) . "',
                                            '" . $subject_id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','Lecture postponed to a new date/time')");
                                    }
                                } else {
                                    // Status is no longer Postponed — clean up any previously linked occurrence.
                                    $existingLinked = Database::search("SELECT * FROM `time_table` WHERE `linked_time_table_id`='" . Database::escape($lecture_id) . "'");
                                    if ($existingLinked->num_rows > 0) {
                                        $existingLinkedData = $existingLinked->fetch_assoc();

                                        Database::iud("DELETE FROM `time_table` WHERE `time_table_id`='" . $existingLinkedData['time_table_id'] . "'");

                                        Database::iud("INSERT INTO `time_table_log`
                                            (`time_table_time_table_id`,`action`,`field_changed`,`old_value`,`new_value`,`subjects_subject_id`,`changed_by`,`changed_at`,`remarks`)
                                            VALUES
                                            ('" . $lecture_id . "','DELETE','linked_time_table_id','" . $existingLinkedData['time_table_id'] . "','NULL','" . $subject_id . "','" . $sessionUserObject['user_id'] . "','" . $addTime . "','Removed postponed occurrence — status no longer Postponed')");
                                    }
                                }

                                if (isset($_FILES["tutes"])) {
                                    for ($i = 0; $i < count($_FILES["tutes"]["name"]); $i++) {

                                        $originalFileName = $_FILES["tutes"]["name"][$i];

                                        $attachment_insert_columns = "`attachment`,`attachment_name`,`time_table_time_table_id`,`users_user_id`,`date_added`,`attachments_order`";
                                        $attachment_insert_values = "'" . $tutes[$i] . "','" . $originalFileName . "','" . $lecture_id . "','" . $sessionUserObject["user_id"] . "','" . $addTime . "','0'";

                                        Database::iud("INSERT INTO `attachments` (" . $attachment_insert_columns . ") VALUES (" . $attachment_insert_values . ")");

                                        $tute_path = "../assets/uploads/lectures-attachments/" . $tutes[$i];
                                        move_uploaded_file($_FILES["tutes"]["tmp_name"][$i], $tute_path);
                                    }
                                }

                                $status->done = "Lecture has been successfully updated";
                            } catch (Exception $e) {
                                $status->error = $e->getMessage();
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


function checkIfFutureDateTime($lecture_date, $start_time)
{
    if ($lecture_date && $start_time) {
        $startDateTime = new DateTime("$lecture_date $start_time");
        $now = new DateTime();
        $isToday = $startDateTime->format('Y-m-d') === $now->format('Y-m-d');
        if (($isToday && $startDateTime > $now) || $startDateTime > $now) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

// NEW: resolves a `time_table_status` id by its label (case-insensitive),
// falling back to $fallbackId if no matching status row exists.
function findStatusIdByName($name, $fallbackId = null)
{
    $result = Database::search("SELECT `time_table_status_id` FROM `time_table_status` WHERE LOWER(`time_table_status`) = '" . Database::escape(strtolower($name)) . "' LIMIT 1");
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['time_table_status_id'];
    }
    return $fallbackId;
}
