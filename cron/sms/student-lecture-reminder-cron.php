<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../../includes/connection.php");
require_once(__DIR__ . "/../../includes/UserClass.php");
require_once(__DIR__ . "/../../includes/Project.php");
require_once(__DIR__ . "/../../includes/helpers/SmsHelper.php");
require_once(__DIR__ . "/../../includes/classes/IntakeGroups.php");

// if (php_sapi_name() !== 'cli') {
//     exit("Access denied");
// }

/**
 * Convert a stored mobile number (e.g. +94769679778) into the local
 * 0XXXXXXXXX format the SMS gateway expects (e.g. 0769679778).
 * Leaves already-local numbers (07XXXXXXXX) untouched.
 */
function formatMobileForSms($number)
{
    $number = preg_replace('/[^0-9+]/', '', trim((string) $number));

    if (strpos($number, '+94') === 0) {
        return '0' . substr($number, 3);
    }

    if (strpos($number, '94') === 0 && strlen($number) === 11) {
        return '0' . substr($number, 2);
    }

    return $number;
}

// Optional log file
$logFile = __DIR__ . '/logs/student-lecture-reminder-cron.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    // Lecture audience (intake groups):
    //  - lecture with NO groups saved  → every eligible student in the intake (as before)
    //  - lecture WITH groups saved     → only students whose registration for this
    //                                    intake (shi) is in one of those groups
    // Same rule the student portal uses: IntakeGroups::studentVisibilityCondition().
    // If the group tables haven't been created yet, fall back to the old behaviour
    // instead of failing the whole run.
    $audienceCondition = '';
    $groupTablesExist = Database::search("SHOW TABLES LIKE 'time_table_has_intake_groups'");
    if ($groupTablesExist && $groupTablesExist->num_rows > 0) {
        $audienceCondition = " AND " . IntakeGroups::studentVisibilityCondition('tt', 'shi');
    } else {
        writeLog("Warning: intake group tables not found - sending to all intake students (no group filtering).", $logFile);
    }

    // Physical lectures (lecture_type_lecture_type_id = 1) happening 2 days or 7 days from now.
    // For each lecture we pull every enrolled student:
    //  - compulsory subjects (subject_type_subject_type_id = 1): every active student on that intake
    //  - elective subjects (subject_type_subject_type_id = 2): only students in students_has_subjects
    // days_before tells us which reminder this row is (2 = two days before, 7 = one week before).
    $query = "
        SELECT
            tt.time_table_id,
            tt.date,
            DATEDIFF(tt.date, CURDATE()) AS days_before,
            tt.start_time,
            tt.end_time,
            TIME_FORMAT(tt.start_time, '%h:%i %p') AS start_time_fmt,
            TIME_FORMAT(tt.end_time, '%h:%i %p') AS end_time_fmt,
            sub.subject,
            title.title,
            u.user_fname,
            u.user_lname,
            s.student_id,
            s.mobile,
            ld.first_name,
            ld.last_name,
            i.intake
        FROM time_table AS tt
        INNER JOIN subjects AS sub
            ON tt.subjects_subject_id = sub.subject_id
        INNER JOIN lecturers AS lec
            ON tt.lecturers_lecturers_id = lec.lecturers_id
        INNER JOIN users AS u
            ON lec.users_user_id = u.user_id
        INNER JOIN title
            ON u.title_title_id = title.title_id
        INNER JOIN levels AS lvl
            ON sub.levels_level_id = lvl.level_id
        INNER JOIN intakes AS i
            ON lvl.intakes_intake_id = i.intake_id
        INNER JOIN students_has_intakes AS shi
            ON shi.intakes_intake_id = i.intake_id
        INNER JOIN students AS s
            ON shi.students_student_id = s.student_id
        INNER JOIN login_details AS ld
            ON s.login_details_login_details_id = ld.login_details_id
        WHERE tt.date IN (
            DATE_ADD(CURDATE(), INTERVAL 2 DAY),
            DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        )
        AND tt.lecture_type_lecture_type_id = 1
        AND shi.status_status_id = 1
        AND (
            sub.subject_type_subject_type_id = 1
            OR (
                sub.subject_type_subject_type_id = 2
                AND EXISTS (
                    SELECT 1
                    FROM students_has_subjects
                    WHERE students_student_id = s.student_id
                    AND subjects_subject_id = sub.subject_id
                )
            )
        )
        {$audienceCondition}
    ";

    $result = Database::search($query);

    if (!$result) {
        writeLog("Query failed.", $logFile);
        exit;
    }

    // Collected here as we loop, then sent as one bulk-different SMS batch at the end.
    // Each student gets a different message (their own subject/time), so this is
    // SEND_BULK_DIFFERENT, not SEND_BULK_SAME.
    $smsMessages = [];
    $skippedNoMobile = 0;
    $lectureCount = 0;
    $weekReminders = 0;
    $twoDayReminders = 0;

    while ($row = $result->fetch_assoc()) {
        $lectureCount++;
        $lectureDate = date('d-M-y', strtotime($row['date']));
        $lecturerName = trim($row['title'] . ' ' . $row['user_fname'] . ' ' . $row['user_lname']);
        $daysBefore = (int) $row['days_before'];

        if (empty($row['mobile'])) {
            $skippedNoMobile++;
            writeLog("No mobile number on file for student_id {$row['student_id']} - SMS skipped (time_table_id {$row['time_table_id']})", $logFile);
            continue;
        }

        if ($daysBefore === 7) {
            $whenText = 'in 1 week';
            $weekReminders++;
        } else {
            $whenText = 'in 2 days';
            $twoDayReminders++;
        }

        $smsText = "Lecture Reminder: You have a physical lecture {$whenText} - {$row['subject']} with {$lecturerName} on {$lectureDate} at {$row['start_time_fmt']}. Please bring your ID card. Scan your ID when you arrive so your arrival time is recorded, and scan it again when you leave so your departure time is recorded.";

        $smsMessages[] = [
            'to'  => formatMobileForSms($row['mobile']),
            'msg' => $smsText,
        ];
    }

    // ---------- Send all queued SMS reminders ----------
    $smsSentBatches = 0;
    $smsFailedBatches = 0;

    if (!empty($smsMessages)) {
        if (count($smsMessages) == 1) {
            try {
                $sms = new SmsHelper();
                $smsResult = $sms->sendSingle($smsMessages[0]['to'], $smsMessages[0]['msg']);
                if ($smsResult['success']) {
                    $smsSentBatches++;
                } else {
                    $smsFailedBatches++;
                    writeLog("SMS failed: " . ($smsResult['raw'] ?? json_encode($smsResult)), $logFile);
                }
            } catch (Exception $e) {
                writeLog("SMS error: " . $e->getMessage(), $logFile);
            }
        } else {
            try {
                $sms = new SmsHelper();
                $smsResults = $sms->sendBulkDifferent($smsMessages);

                foreach ($smsResults as $batchResult) {
                    if ($batchResult['success']) {
                        $smsSentBatches++;
                    } else {
                        $smsFailedBatches++;
                        writeLog("SMS batch failed: " . ($batchResult['raw'] ?? json_encode($batchResult)), $logFile);
                    }
                }

                writeLog("SMS: " . count($smsMessages) . " reminder(s) queued ({$weekReminders} one-week, {$twoDayReminders} two-day) across "
                    . count($smsResults) . " batch(es). Sent: {$smsSentBatches}, Failed: {$smsFailedBatches}", $logFile);
            } catch (Exception $e) {
                writeLog("SMS error: " . $e->getMessage(), $logFile);
            }
        }
    } else {
        writeLog("No SMS reminders to send.", $logFile);
    }

    $summary = "Completed. Lectures matched: {$lectureCount} (1-week: {$weekReminders}, 2-day: {$twoDayReminders}). SMS batches sent: {$smsSentBatches}, failed: {$smsFailedBatches}. Skipped (no mobile): {$skippedNoMobile}";
    writeLog($summary, $logFile);
    echo $summary . PHP_EOL;
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
