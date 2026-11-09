<?php

date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../../includes/connection.php");
require_once(__DIR__ . "/../../includes/UserClass.php");
require_once(__DIR__ . "/../../includes/Project.php");
require_once(__DIR__ . "/../../includes/helpers/SmsHelper.php");

if (php_sapi_name() !== 'cli') {
    exit("Access denied");
}

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
$logFile = __DIR__ . '/logs/lecturer-reminder-cron.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    // This cron is expected to run every 15 minutes. It picks up any lecture
    // whose start time falls between (NOW + 9h) and (NOW + 9h + 15min), so each
    // lecture is only caught once across consecutive runs.
    // TODO: if your actual cron schedule is not every 15 minutes, change the
    // INTERVAL 15 MINUTE below to match, or duplicates/misses can happen.
    $query = "
        SELECT
            tt.time_table_id,
            tt.date,
            tt.start_time,
            tt.end_time,
            TIME_FORMAT(tt.start_time, '%h:%i %p') AS start_time_fmt,
            TIME_FORMAT(tt.end_time, '%h:%i %p') AS end_time_fmt,
            sub.subject,
            title.title,
            u.user_fname,
            u.user_lname,
            u.user_mobile,
            prog.programme,
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
        INNER JOIN programmes AS prog
            ON i.programmes_programme_id = prog.programme_id
        WHERE TIMESTAMP(tt.date, tt.start_time) BETWEEN
            DATE_ADD(NOW(), INTERVAL 9 HOUR)
            AND DATE_ADD(DATE_ADD(NOW(), INTERVAL 9 HOUR), INTERVAL 15 MINUTE)
        GROUP BY tt.time_table_id
    ";
    // TODO: confirm u.mobile is where the lecturer's mobile number actually
    // lives (some schemas put it on a separate login_details/profile table,
    // the way students.mobile does for students).
    // TODO: confirm the intakes -> programmes FK column name
    // (i.programmes_programme_id -> programmes.programme_id) matches your schema.

    $result = Database::search($query);

    if (!$result) {
        writeLog("Query failed.", $logFile);
        exit;
    }

    // Collected here as we loop, then sent as one bulk-different SMS batch at the end.
    // Each lecturer gets a different message (their own subject/time), so this is
    // SEND_BULK_DIFFERENT, not SEND_BULK_SAME.
    $smsMessages = [];
    $skippedNoMobile = 0;
    $lectureCount = 0;

    while ($row = $result->fetch_assoc()) {
        $lectureCount++;
        $lectureDate = date('d-M-y', strtotime($row['date']));

        if (empty($row['user_mobile'])) {
            $skippedNoMobile++;
            writeLog("No mobile number on file for lecturer (time_table_id {$row['time_table_id']}) - SMS skipped", $logFile);
            continue;
        }

        $smsText = "Lecture Reminder: You have a lecture on {$row['subject']} for {$row['programme']} - {$row['intake']} on {$lectureDate} from {$row['start_time_fmt']} to {$row['end_time_fmt']}.";

        $smsMessages[] = [
            'to'  => formatMobileForSms($row['user_mobile']),
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

                writeLog("SMS: " . count($smsMessages) . " reminder(s) queued across "
                    . count($smsResults) . " batch(es). Sent: {$smsSentBatches}, Failed: {$smsFailedBatches}", $logFile);
            } catch (Exception $e) {
                writeLog("SMS error: " . $e->getMessage(), $logFile);
            }
        }
    } else {
        writeLog("No SMS reminders to send.", $logFile);
    }

    writeLog("Completed. Lectures matched: {$lectureCount}. SMS batches sent: {$smsSentBatches}, failed: {$smsFailedBatches}. Skipped (no mobile): {$skippedNoMobile}", $logFile);
    echo "Completed. Lectures matched: {$lectureCount}. SMS batches sent: {$smsSentBatches}, failed: {$smsFailedBatches}. Skipped (no mobile): {$skippedNoMobile}" . PHP_EOL;
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
