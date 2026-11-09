<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/classes/IntakeGroups.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        $totalLecSchedule = "
        SELECT 
            `p`.`programme_id`,
            MAX(`p`.`programme`)                        AS `programme`,
            MAX(`p`.`programme_cover`)                  AS `programme_cover`,
            MAX(`f`.`faculty_id`)                       AS `faculty_id`,
            MAX(`f`.`faculty`)                          AS `faculty`,
            MAX(`subj`.`subject_id`)                    AS `subject_id`,
            MAX(`subj`.`subject`)                       AS `subject`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            MAX(`tt`.`lecture`)                         AS `lecture`,
            MAX(`tt`.`lecture_title`)                   AS `lecture_title`,
            MAX(`tt`.`time_table_id`)                   AS `time_table_id`,
            MAX(`tt`.`reason`)                          AS `reason`,
            MAX(`tt`.`linked_time_table_id`)            AS `own_linked_time_table_id`,
            MAX(`tts`.`time_table_status`)              AS `time_table_status`,
            MAX(`tts`.`time_table_status_id`)           AS `time_table_status_id`,
            MAX(`lt`.`lecture_type`)                    AS `lecture_type`,
            MAX(`lt`.`lecture_type_id`)                 AS `lecture_type_id`,
            MAX(`u`.`user_fname`)                       AS `user_fname`,
            MAX(`u`.`user_lname`)                       AS `user_lname`,
            MAX(`u`.`profile_img`)                      AS `profile_img`,
            MAX(`t`.`title`)                            AS `title`,
            JSON_ARRAYAGG(
                JSON_OBJECT(
                    'lecture_id',  `tt`.`time_table_id`,
                    'batch_id',    `i`.`intake_id`,
                    'batch_name',  `i`.`intake`,
                    'batch_cover', `i`.`intake_cover`,
                    'level',       `lv`.`level`,
                    'intake',      `i`.`intake`,
                    'subject',     `subj`.`subject`,
                    'subject_id',  `subj`.`subject_id`
                )
            ) AS batch_list,
            COUNT(DISTINCT `tt`.`time_table_id`) AS timetable_count
        FROM                    
            `time_table` AS `tt`
        INNER JOIN 
            `subjects` AS `subj` ON `tt`.`subjects_subject_id` = `subj`.`subject_id`
        INNER JOIN 
            `levels` AS `lv` ON `subj`.`levels_level_id` = `lv`.`level_id`
        INNER JOIN 
            `intakes` AS `i` ON `lv`.`intakes_intake_id` = `i`.`intake_id`
        INNER JOIN 
            `programmes` AS `p` ON `i`.`programmes_programme_id` = `p`.`programme_id`
        INNER JOIN
            `faculties` AS `f` ON `p`.`faculties_faculty_id` = `f`.`faculty_id`                
        INNER JOIN
            `time_table_status` AS `tts` ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`
        INNER JOIN
            `lecture_type` AS `lt` ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`        
        INNER JOIN 
            `lecturers` AS `lec` ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
        INNER JOIN 
            `users` AS `u` ON `lec`.`users_user_id` = `u`.`user_id`
        INNER JOIN 
            `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
        ";

        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $totalLecSchedule .= " WHERE 
                u.user_id = '" . $sessionUserObject["user_id"] . "'";
        }

        if (isset($_GET['subject']) && !empty($_GET['subject'])) {
            $totalLecSchedule .= " AND subj.subject_id = '" . $_GET['subject'] . "'";
        }

        if (isset($_GET['text']) && !empty($_GET['text'])) {
            $search_text = $_GET['text'];
            $totalLecSchedule .= " AND 
            (`i`.`intake` LIKE '%" . $search_text . "%' OR 
            CONCAT(`u`.`user_fname`, ' ', `u`.`user_lname`) LIKE '%" . $search_text . "%' OR 
            `subj`.`subject` LIKE '%" . $search_text . "%' OR
            `lv`.`level` LIKE '%" . $search_text . "%')";
        }

        $totalLecSchedule .= " AND lt.lecture_type_id != 3
        GROUP BY
            `p`.`programme_id`,
            `tt`.`date`,
            `tt`.`start_time`,
            `tt`.`end_time`,
            `tts`.`time_table_status_id`,
            `lt`.`lecture_type_id`,
            `u`.`user_id`          
        ORDER BY
            `tt`.`date` ASC,
            `tt`.`start_time` ASC";

        $totalLecSchedule = Database::search($totalLecSchedule);
        $lectures = [];

        if ($totalLecSchedule->num_rows > 0) {
            $date = new DateTime("now", new DateTimeZone("Asia/Colombo"));
            while ($row = $totalLecSchedule->fetch_assoc()) {

                $programmeCover = "./assets/imgs/programmes-covers/" . $row['programme_cover'];
                $defaultImage   = "./assets/imgs/common/empty-thumbnail.webp";

                $hasProgrammeCover = true;
                if (empty($row['programme_cover'])) {
                    $hasProgrammeCover = false;
                } else if (!file_exists("../assets/imgs/programmes-covers/" . $row['programme_cover'])) {
                    $hasProgrammeCover = false;
                }

                $lectureStatus = $row['time_table_status'];

                $attendanceCount = Database::search(
                    "SELECT COUNT(*) AS count FROM `attendance` WHERE `time_table_time_table_id` = '" . $row['time_table_id'] . "'"
                )->fetch_assoc()['count'];

                // ---------------------------------------------------------------
                // Reschedule linkage. `time_table.linked_time_table_id` connects a
                // rescheduled occurrence back to the lecture it replaced, so the
                // relationship has two directions and both are resolved here:
                //
                //   FORWARD  — this lecture was rescheduled and some other row's
                //              `linked_time_table_id` now points AT this row's
                //              `time_table_id`. That other row is where the
                //              lecture actually ended up.
                //   BACKWARD — this lecture IS that "other row": its own
                //              `linked_time_table_id` (aliased above as
                //              `own_linked_time_table_id`, since it can't be
                //              selected directly under the MAX()-per-group
                //              pattern this query already uses) points back at
                //              the original lecture it replaced.
                //
                // Kept as per-row queries rather than folded into the aggregate
                // above, since `time_table_id` is only resolved via MAX() there
                // (concurrent-lecture grouping), so a correlated subquery on it
                // isn't reliable.
                // ---------------------------------------------------------------
                $linkedTimeTableId = null;
                $linkedDate = null;
                $linkedStartTime = null;
                $linkedEndTime = null;
                $linkedDateDisplay = null;
                $linkedStartTimeDisplay = null;
                $linkedEndTimeDisplay = null;
                $linkedLecturer = null;

                $linkedRow = Database::search(
                    "SELECT `tt2`.`time_table_id`, `tt2`.`date`, `tt2`.`start_time`, `tt2`.`end_time`,
                            `t2`.`title` AS `title`, `u2`.`user_fname`, `u2`.`user_lname`
                     FROM `time_table` AS `tt2`
                     INNER JOIN `lecturers` AS `lec2` ON `tt2`.`lecturers_lecturers_id` = `lec2`.`lecturers_id`
                     INNER JOIN `users` AS `u2` ON `lec2`.`users_user_id` = `u2`.`user_id`
                     INNER JOIN `title` AS `t2` ON `u2`.`title_title_id` = `t2`.`title_id`
                     WHERE `tt2`.`linked_time_table_id` = '" . $row['time_table_id'] . "'
                     LIMIT 1"
                );
                if ($linkedRow->num_rows > 0) {
                    $linkedData = $linkedRow->fetch_assoc();
                    $linkedTimeTableId = $linkedData['time_table_id'];
                    // Y-m-d / H:i — kept in this shape because the frontend
                    // feeds these straight into <input type="date">/<input
                    // type="time"> when prefilling the postpone form.
                    $linkedDate = date('Y-m-d', strtotime($linkedData['date']));
                    $linkedStartTime = date('H:i', strtotime($linkedData['start_time']));
                    $linkedEndTime = date('H:i', strtotime($linkedData['end_time']));
                    // Human-readable versions for the reschedule badge/panel.
                    $linkedDateDisplay = date('d F, Y', strtotime($linkedData['date']));
                    $linkedStartTimeDisplay = date('h:i A', strtotime($linkedData['start_time']));
                    $linkedEndTimeDisplay = date('h:i A', strtotime($linkedData['end_time']));
                    $linkedLecturer = $linkedData['title'] . '. ' . $linkedData['user_fname'] . ' ' . $linkedData['user_lname'];
                }

                $originTimeTableId = $row['own_linked_time_table_id'];
                $originDate = null;
                $originStartTime = null;
                $originEndTime = null;
                $originLecturer = null;

                if (!empty($originTimeTableId)) {
                    $originRow = Database::search(
                        "SELECT `tt3`.`date`, `tt3`.`start_time`, `tt3`.`end_time`,
                                `t3`.`title` AS `title`, `u3`.`user_fname`, `u3`.`user_lname`
                         FROM `time_table` AS `tt3`
                         INNER JOIN `lecturers` AS `lec3` ON `tt3`.`lecturers_lecturers_id` = `lec3`.`lecturers_id`
                         INNER JOIN `users` AS `u3` ON `lec3`.`users_user_id` = `u3`.`user_id`
                         INNER JOIN `title` AS `t3` ON `u3`.`title_title_id` = `t3`.`title_id`
                         WHERE `tt3`.`time_table_id` = '" . $originTimeTableId . "'
                         LIMIT 1"
                    );
                    if ($originRow->num_rows > 0) {
                        $originData = $originRow->fetch_assoc();
                        $originDate = date('d F, Y', strtotime($originData['date']));
                        $originStartTime = date('h:i A', strtotime($originData['start_time']));
                        $originEndTime = date('h:i A', strtotime($originData['end_time']));
                        $originLecturer = $originData['title'] . '. ' . $originData['user_fname'] . ' ' . $originData['user_lname'];
                    }
                }

                if ($row['time_table_status_id'] == 1) {
                    if ($row['date'] == $date->format('Y-m-d') && $row['start_time'] < $date->format('H:i:s') && $row['end_time'] > $date->format('H:i:s')) {
                        $lectureStatus = "Ongoing";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['start_time'] > $date->format('H:i:s')) || $row['date'] > $date->format('Y-m-d')) {
                        $lectureStatus = "Upcoming";
                    } else if (($row['date'] == $date->format('Y-m-d') && $row['end_time'] < $date->format('H:i:s')) || $row['date'] < $date->format('Y-m-d')) {
                        $lectureStatus = 'Pending Confirmation';
                    }
                }

                // Use raw DB date as the grouping key before formatting
                $lectureDate = $row['date'];

                $lecture = [
                    'lectureId'                          => $row['time_table_id'],
                    'date'                               => date('d F, Y', strtotime($row['date'])),
                    'programme'                          => htmlspecialchars($row['programme']),
                    'faculty'                            => htmlspecialchars($row['faculty']),
                    'subject'                            => htmlspecialchars($row['subject']),
                    'programmeImage'                     => $hasProgrammeCover ? $programmeCover : $defaultImage,
                    'lecturerName'                       => $row['title'] . '.' . $row['user_fname'] . ' ' . $row['user_lname'],
                    'lecturerProfile'                    => "./assets/imgs/users/" . $row['profile_img'],
                    'joinLink'                           => $row['lecture'],
                    'status'                             => $lectureStatus,
                    'statusId'                             => $row['time_table_status_id'],
                    'timeTableStatusId'                  => (int) $row['time_table_status_id'],
                    'reason'                              => $row['reason'],
                    'startTime'                           => date('h:i A', strtotime($row['start_time'])),
                    'endTime'                             => date('h:i A', strtotime($row['end_time'])),
                    'startTime24'                         => date('H:i', strtotime($row['start_time'])),
                    'endTime24'                           => date('H:i', strtotime($row['end_time'])),
                    'lectureType'                        => $row['lecture_type'],
                    'title'                              => $row['lecture_title'],

                    // Forward link: this lecture was rescheduled TO the occurrence below.
                    // Deliberately keyed off the actual linked_time_table_id value only —
                    // never off `status`/`time_table_status`, so a lecture that merely has
                    // a "Postponed" label but no real linked row is NOT flagged here.
                    'hasRescheduleTarget'                => !empty($linkedTimeTableId),
                    'linkedTimeTableId'                  => $linkedTimeTableId,
                    'linkedDate'                          => $linkedDate,
                    'linkedStartTime'                     => $linkedStartTime,
                    'linkedEndTime'                       => $linkedEndTime,
                    'linkedDateDisplay'                   => $linkedDateDisplay,
                    'linkedStartTimeDisplay'              => $linkedStartTimeDisplay,
                    'linkedEndTimeDisplay'                 => $linkedEndTimeDisplay,
                    'linkedLecturer'                      => $linkedLecturer,

                    // Backward link: this lecture IS the reschedule of the occurrence below.
                    // Same rule: keyed off own_linked_time_table_id only, never off status.
                    'isRescheduled'                       => !empty($originTimeTableId),
                    'originTimeTableId'                   => $originTimeTableId,
                    'originDate'                           => $originDate,
                    'originStartTime'                      => $originStartTime,
                    'originEndTime'                        => $originEndTime,
                    'originLecturer'                       => $originLecturer,

                    'hasPermissionEditPermission'        => SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0),
                    'hasPermissionConfirmLecturerPayment' => SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0),
                    'hasPermissionMarkAttendance'        => SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule', 0),
                    'hasPermissionUploadRecording'       => SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('lecture_schedule'),
                    "hasPermissioncompleteClassUpdateAnotherStatus"   => SessionUser::ifUserIsPro($sessionUserRole),
                    'batchList'                          => json_decode($row['batch_list'], true),
                    'attendanceCount'                    => $attendanceCount,
                ];

                if (!isset($lectures[$lectureDate])) {
                    $lectures[$lectureDate] = [];
                }
                $lectures[$lectureDate][] = $lecture;
            }
        }

        // ---------------------------------------------------------------
        // Lecture audience (intake groups). One query for every time_table
        // row on the calendar. A time_table row with no saved groups is an
        // all-intake lecture. Each batch (= one time_table row / intake)
        // gets its own audience; the calendar item gets a combined label.
        // ---------------------------------------------------------------
        $allTimeTableIds = [];
        foreach ($lectures as $dayLectures) {
            foreach ($dayLectures as $l) {
                foreach (($l['batchList'] ?? []) as $b) {
                    $allTimeTableIds[] = (int)$b['lecture_id'];
                }
            }
        }
        $audienceMap = [];
        try {
            $audienceMap = IntakeGroups::getAudienceMap($allTimeTableIds);
        } catch (Throwable $e) {
            // Table not created yet / transient error: everything shows as all-intake.
            error_log('[getLectureScheduleData audience] ' . $e->getMessage());
        }

        foreach ($lectures as $day => &$dayLectures) {
            foreach ($dayLectures as &$l) {
                $parts = [];
                if (!is_array($l['batchList'] ?? null)) {
                    $l['batchList'] = [];
                }
                $batchCount = count($l['batchList']);
                // Iterate the real array by reference (not a `?? []` temporary),
                // so the audience fields are written onto each batch.
                foreach ($l['batchList'] as &$b) {
                    $groups = $audienceMap[(int)$b['lecture_id']] ?? [];
                    $b['audience_groups'] = $groups;
                    $b['audience_all'] = empty($groups);
                    $text = empty($groups)
                        ? 'All students in this intake'
                        : implode(', ', array_column($groups, 'name'));
                    $parts[] = $batchCount > 1 ? ($b['batch_name'] . ': ' . $text) : $text;
                }
                unset($b);
                $l['audienceAll'] = !array_filter($l['batchList'], function ($b) {
                    return !$b['audience_all'];
                });
                $l['audienceLabel'] = $parts ? implode(' | ', $parts) : 'All students in this intake';
                $l['hasPermissionEditAudience'] = IntakeGroups::canEditLectureAudience($sessionUserRole);
            }
            unset($l);
        }
        unset($dayLectures);

        $status->lectures = $lectures;
        $status->done = true;
    } else {
        http_response_code(400);
        $status->error = 'Invalid JSON';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
