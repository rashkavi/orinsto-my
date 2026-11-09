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
            $time_table_status = isset($_POST['status']) ? trim($_POST['status']) : 0;

            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
            $postponed_date = isset($_POST['postponed_date']) ? trim($_POST['postponed_date']) : '';
            $postponed_start_time = isset($_POST['postponed_start_time']) ? trim($_POST['postponed_start_time']) : '';
            $postponed_end_time = isset($_POST['postponed_end_time']) ? trim($_POST['postponed_end_time']) : '';
            $linked_time_table_id = (isset($_POST['linked_time_table_id']) && !empty($_POST['linked_time_table_id'])) ? trim($_POST['linked_time_table_id']) : 0;

            // actual start/end time, only used when marking Completed
            $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
            $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';

            if (empty($lecture_id) || $lecture_id == 0) {
                $status->error = "Invalid lecture Id";
            } else {
                $isValidLecture = Database::search("SELECT *,`lecturers`.`users_user_id` AS lecturer_user_id FROM `time_table`
                INNER JOIN `subjects` ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                WHERE `time_table_id`='" . Database::escape($lecture_id) . "'");

                if ($isValidLecture->num_rows == 0) {
                    $status->error = "Invalid lecture Id";
                } else {
                    $lectureData = $isValidLecture->fetch_assoc();
                    $subject_id = $lectureData['subjects_subject_id'];

                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('super_lecture_schedule', 10) || UserPermissions::hasPermission('lecture_schedule', 10, $subject_id))) {
                        $status->error = 'You are not authorized to edit scheduled lectures';
                    } else {

                        if ($time_table_status == 0) {
                            $status->time_table_status = 'Please select a status';
                        }

                        $selectedStatusName = '';
                        if (!empty($time_table_status) && $time_table_status != 0) {
                            $statusNameRow = Database::search("SELECT `time_table_status` FROM `time_table_status` WHERE `time_table_status_id` = '" . Database::escape($time_table_status) . "'");
                            if ($statusNameRow->num_rows > 0) {
                                $selectedStatusName = strtolower(trim($statusNameRow->fetch_assoc()['time_table_status']));
                            } else {
                                $status->time_table_status = 'Invalid status selected';
                            }
                        }

                        // Cancelled requires a reason
                        if ($selectedStatusName === 'cancelled') {
                            if (empty($reason)) {
                                $status->reason = 'Please enter a cancellation reason';
                            } else if (strlen($reason) > 500) {
                                $status->reason = 'Reason should be less than 500 characters';
                            }
                        }

                        // Lecturer row for the current lecture — needed for conflict checks
                        // and payment calculation below
                        $lecturerRow = Database::search("SELECT * FROM `lecturers` WHERE `lecturers_id` = '" . Database::escape($lectureData['lecturers_lecturers_id']) . "'");
                        $lecturerData = $lecturerRow->num_rows > 0 ? $lecturerRow->fetch_assoc() : null;

                        // Postponed requires a new date/start/end and must not clash with existing bookings
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
                                if ($lecturerData) {
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

                        // Completed requires actual start/end time and must not clash either
                        if ($selectedStatusName === 'completed') {
                            if (empty($start_time)) {
                                $status->start_time = 'Please select a start time';
                            }
                            if (empty($end_time)) {
                                $status->end_time = 'Please select an end time';
                            }
                        }

                        if (empty(get_object_vars($status))) {
                            $timezone = new DateTimeZone("Asia/Colombo");
                            $dateTime = new DateTime("now", $timezone);
                            $addTime = $dateTime->format('Y-m-d H:i:s');

                            $reasonToStore = ($selectedStatusName === 'cancelled') ? Database::escape($reason) : '';

                            $time_table_update_query = "`time_table_status_time_table_status_id`='" . Database::escape($time_table_status) . "', `reason`='" . $reasonToStore . "'";

                            $duration = null;
                            if ($selectedStatusName === 'completed') {
                                $start = new DateTime($start_time);
                                $end = new DateTime($end_time);
                                $interval = $start->diff($end);
                                $duration = ($interval->h * 60) + $interval->i;

                                $time_table_update_query .= ", `start_time`='" . Database::escape($start_time) . "', `end_time`='" . Database::escape($end_time) . "', `duration`='" . $duration . "'";
                            }

                            try {
                                $batchIdsResult = Database::search("
                                    SELECT time_table_id FROM time_table
                                    INNER JOIN lecturers ON lecturers.lecturers_id = time_table.lecturers_lecturers_id
                                    WHERE date = '" . Database::escape($lectureData['date']) . "'
                                    AND start_time = '" . Database::escape($lectureData['start_time']) . "'
                                    AND end_time = '" . Database::escape($lectureData['end_time']) . "'
                                    AND lecturers.users_user_id = '" . Database::escape($lectureData['lecturer_user_id']) . "'
                                    AND time_table_status_time_table_status_id = 1
                                    AND lecture_type_lecture_type_id != 3
                                ");
                                while ($b = $batchIdsResult->fetch_assoc()) {

                                    Database::iud("UPDATE `time_table` SET " . $time_table_update_query . " WHERE `time_table_id` = '" . Database::escape($b['time_table_id']) . "'");

                                    Database::iud("INSERT INTO `time_table_log`
                                    (`time_table_time_table_id`,`action`,`field_changed`,`old_value`,`new_value`,`subjects_subject_id`,`changed_by`,`changed_at`,`remarks`)
                                    VALUES
                                    ('" . Database::escape($b['time_table_id']) . "','STATUS_CHANGE','time_table_status_time_table_status_id',
                                    '" . Database::escape($lectureData['time_table_status_time_table_status_id']) . "',
                                    '" . Database::escape($time_table_status) . "',
                                    '" . Database::escape($subject_id) . "','" . Database::escape($sessionUserObject['user_id']) . "','" . $addTime . "','Status updated via Update Status modal')");

                                    if ($selectedStatusName === 'postponed') {
                                        $postponedStartDt = new DateTime($postponed_start_time);
                                        $postponedEndDt = new DateTime($postponed_end_time);
                                        $postponedInterval = $postponedStartDt->diff($postponedEndDt);
                                        $postponedDuration = ($postponedInterval->h * 60) + $postponedInterval->i;

                                        if (!empty($linked_time_table_id)) {
                                            $existingLinked = Database::search("SELECT * FROM `time_table` WHERE `time_table_id`='" . Database::escape($linked_time_table_id) . "' AND `linked_time_table_id`='" . Database::escape($b['time_table_id']) . "'");
                                        } else {
                                            $existingLinked = Database::search("SELECT * FROM `time_table` WHERE `linked_time_table_id`='" . Database::escape($b['time_table_id']) . "'");
                                        }

                                        if ($existingLinked->num_rows > 0) {
                                            $existingLinkedData = $existingLinked->fetch_assoc();

                                            Database::iud("UPDATE `time_table` SET
                                            `date`='" . Database::escape($postponed_date) . "',
                                            `start_time`='" . Database::escape($postponed_start_time) . "',
                                            `end_time`='" . Database::escape($postponed_end_time) . "',
                                            `duration`='" . $postponedDuration . "'
                                            WHERE `time_table_id`='" . Database::escape($existingLinkedData['time_table_id']) . "'");
                                        } else {
                                            $linkedInsertColumns = "`date`,`start_time`,`end_time`,`duration`,`lecture_type_lecture_type_id`,`lecture`,`subjects_subject_id`,`users_user_id`,`date_added`,`time_table_status_time_table_status_id`,`lecturers_lecturers_id`,`lecture_title`,`linked_time_table_id`";
                                            $linkedInsertValues = "'" . Database::escape($postponed_date) . "',
                                            '" . Database::escape($postponed_start_time) . "',
                                            '" . Database::escape($postponed_end_time) . "',
                                            '" . $postponedDuration . "',
                                            '" . Database::escape($lectureData['lecture_type_lecture_type_id']) . "',
                                            '" . Database::escape($lectureData['lecture']) . "',
                                            '" . Database::escape($subject_id) . "',
                                            '" . Database::escape($sessionUserObject['user_id']) . "',
                                            '" . $addTime . "',
                                            '1',
                                            '" . Database::escape($lectureData['lecturers_lecturers_id']) . "',
                                            '" . Database::escape($lectureData['lecture_title']) . "',
                                            '" . Database::escape($b['time_table_id']) . "'";

                                            Database::iud("INSERT INTO `time_table` (" . $linkedInsertColumns . ") VALUES (" . $linkedInsertValues . ")");
                                        }
                                    } else {
                                        // No longer Postponed — remove any previously linked occurrence
                                        $existingLinked = Database::search("SELECT * FROM `time_table` WHERE `linked_time_table_id`='" . Database::escape($b['time_table_id']) . "'");
                                        if ($existingLinked->num_rows > 0) {
                                            $existingLinkedData = $existingLinked->fetch_assoc();
                                            Database::iud("DELETE FROM `time_table` WHERE `time_table_id`='" . Database::escape($existingLinkedData['time_table_id']) . "'");
                                        }
                                    }
                                }

                                // NEW: Completed → create/update the lecturer's payment for this
                                // lecture. Anything else → void a still-Pending auto-payment, if any.
                                if ($selectedStatusName === 'completed') {
                                    if ($lecturerData) {
                                        syncLecturePaymentForCompletion($lecture_id, $lectureData['date'], $lectureData['start_time'], $lectureData['end_time'], $subject_id, $lecturerData, $duration, $sessionUserObject, $addTime, $status);
                                    }
                                } else {
                                    voidPendingLecturePayment($lecture_id);
                                }

                                $status->done = "Lecture status has been successfully updated";
                            } catch (Exception $e) {
                                $status->error = "An error occurred while updating the lecture status: " . $e->getMessage();
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

// NEW: creates or updates the lecturer's payment record when a lecture is
// marked Completed. Relies on `lecturer_payments.time_table_time_table_id`
// linking a payment row back to this specific lecture — add that column if
// it doesn't exist yet, or duplicate/stale payment rows become possible.
function syncLecturePaymentForCompletion($lecture_id, $date, $start_time, $end_time, $subject_id, $lecturerData, $durationMinutes, $sessionUserObject, $addTime, &$status)
{
    $actualHours = $durationMinutes / 60;
    $minimumHours = isset($lecturerData['minimum_payable_hours']) ? (float) $lecturerData['minimum_payable_hours'] : 0;
    $payableHours = max($actualHours, $minimumHours);

    $rateUsed = (float) $lecturerData['lecture_rate'];
    $amount = round($payableHours * $rateUsed, 2);

    $contractId = $lecturerData['lecturer_contracts_lecturer_contract_id'];
    // NOTE: this is the lecturer's own configured payment type — column name
    // has a literal space in it in your `lecturers` schema.
    $paymentTypeId = $lecturerData['lecture_payment _type_lecture_payment _type_id'];

    $pendingStatusRow = Database::search("SELECT `earning_status_id` FROM `earning_status` WHERE LOWER(`earning_status`) = 'scheduled' LIMIT 1");
    if ($pendingStatusRow->num_rows == 0) {
        $status->paymentWarning = "Lecture status updated, but no payment record was created — a 'Pending' earning status could not be found.";
        return;
    }
    $pendingStatusId = $pendingStatusRow->fetch_assoc()['earning_status_id'];

    $note = "Auto-generated for completed lecture #" . $lecture_id;

    $existingPayment = Database::search("SELECT * FROM `lecturer_payments_has_lectures` `lphl`
        INNER JOIN `lecturer_payments` `lp` ON `lphl`.`lecturer_payments_lecturer_payments_id` = `lp`.`lecturer_payments_id`
        WHERE `lphl`.`time_table_time_table_id` = '" . Database::escape($lecture_id) . "'");

    if ($existingPayment->num_rows > 0) {
        $existingPaymentData = $existingPayment->fetch_assoc();

        $currentStatusRow = Database::search("SELECT `earning_status` FROM `earning_status` WHERE `earning_status_id` = '" . Database::escape($existingPaymentData['earning_status_earning_status_id']) . "'");
        $currentStatusName = $currentStatusRow->num_rows > 0 ? strtolower($currentStatusRow->fetch_assoc()['earning_status']) : '';

        if ($currentStatusName === 'pending') {
            Database::iud("UPDATE `lecturer_payments` SET
                `units` = '" . Database::escape($payableHours) . "',
                `rate_used` = '" . Database::escape($rateUsed) . "',
                `amount` = '" . Database::escape($amount) . "',
                `duration` = '" . Database::escape($durationMinutes) . "',
                `payment_note` = '" . Database::escape($note) . "'
                WHERE `lecturer_payments_id` = '" . Database::escape($existingPaymentData['lecturer_payments_id']) . "'");
        } else {
            $status->paymentWarning = "Lecture time updated, but its payment record is no longer Pending and was left unchanged.";
        }
    } else {
        Database::iud("
            INSERT INTO `lecturer_payments`
                (`users_user_id`, `lecturer_contracts_lecturer_contract_id`, `subjects_subject_id`,
                 `lecture_payment_type_id`, `units`, `rate_used`, `amount`, `duration`, `payment_note`,
                 `earning_status_earning_status_id`,  `added_by`, `added_at`)
            VALUES (
                '" . Database::escape($lecturerData['users_user_id']) . "',
                " . ($contractId ? "'" . Database::escape($contractId) . "'" : "NULL") . ",
                '" . Database::escape($subject_id) . "',
                '" . Database::escape($paymentTypeId) . "',
                '" . Database::escape($payableHours) . "',
                '" . Database::escape($rateUsed) . "',
                '" . Database::escape($amount) . "',
                '" . Database::escape($durationMinutes) . "',
                '" . Database::escape($note) . "',
                '" . Database::escape($pendingStatusId) . "',
                '" . Database::escape($sessionUserObject['user_id']) . "', '" . $addTime . "'
            )
        ");

        $paymentId = Database::$connection->insert_id;

        $batchIdsResult = Database::search("
    SELECT time_table_id FROM time_table
    INNER JOIN lecturers ON lecturers.lecturers_id = time_table.lecturers_lecturers_id
    WHERE date = '" . Database::escape($date) . "'
      AND start_time = '" . Database::escape($start_time) . "'
      AND end_time = '" . Database::escape($end_time) . "'
      AND lecturers.users_user_id = '" . Database::escape($lecturerData['users_user_id']) . "'
      AND time_table_status_time_table_status_id IN (1,5)
      AND lecture_type_lecture_type_id != 3
");
        while ($b = $batchIdsResult->fetch_assoc()) {
            Database::iud("
        INSERT INTO lecturer_payments_has_lectures
            (lecturer_payments_lecturer_payments_id, time_table_time_table_id)
        VALUES (" . Database::escape($paymentId) . ", " . Database::escape($b['time_table_id']) . ")
    ");
        }
    }
}

// NEW: called when a lecture's status changes to something other than
// Completed. Removes its auto-generated payment only while it's still
// Pending — an approved/paid record is left untouched.
function voidPendingLecturePayment($lecture_id)
{
    $existingPayment = Database::search("SELECT `lp`.*, `es`.`earning_status` FROM `lecturer_payments_has_lectures` `lphl`
        INNER JOIN `lecturer_payments` `lp` ON `lphl`.`lecturer_payments_lecturer_payments_id` = `lp`.`lecturer_payments_id`
        INNER JOIN `earning_status` `es` ON `lp`.`earning_status_earning_status_id` = `es`.`earning_status_id`
        WHERE `lphl`.`time_table_time_table_id` = '" . Database::escape($lecture_id) . "'");

    if ($existingPayment->num_rows > 0) {
        $existingPaymentData = $existingPayment->fetch_assoc();
        if (strtolower($existingPaymentData['earning_status']) === 'pending') {
            Database::iud("DELETE FROM `lecturer_payments` WHERE `lecturer_payments_id` = '" . Database::escape($existingPaymentData['lecturer_payments_id']) . "'");
        }
    }
}
