<?php

date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../../includes/connection.php");
require_once(__DIR__ . "/../../includes/UserClass.php");
require_once(__DIR__ . "/../../includes/Project.php");
require_once(__DIR__ . "/../../includes/helpers/SmsHelper.php");


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

$todayMonth = date('m');
$todayDay   = date('d');
$todayDate  = date('Y-m-d');
$currentYear = date('Y');

// Optional log file
$logFile = __DIR__ . '/logs/payment-reminder-cron.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    $query = "
    SELECT 
        cp.amount,
        cp.due_date,
        cp.refference,
        s.student_id,
        s.mobile,
        ld.first_name,
        ld.last_name,
        ld.email,
        ld.whatsapp,
        i.intake,
        ROW_NUMBER() OVER (
            PARTITION BY cp.students_has_intakes_students_has_intakes_id
            ORDER BY cp.due_date ASC, cp.course_payments_id ASC
        ) AS installment_no
    FROM course_payments AS cp
    INNER JOIN payment_plan_has_students_has_intakes AS pphi 
        ON cp.payment_plan_has_students_has_intakes_id = pphi.payment_plan_has_students_has_intakes_id
    INNER JOIN students_has_intakes AS shi 
        ON pphi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN students AS s 
        ON shi.students_student_id = s.student_id
    INNER JOIN login_details AS ld 
        ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN intakes AS i 
        ON shi.intakes_intake_id = i.intake_id
    WHERE cp.payment_status_payment_status_id = '1'
     AND `shi`.`status_status_id` != 3
    AND (
        cp.due_date = DATE_ADD(CURDATE(), INTERVAL 5 DAY)
        OR cp.due_date = DATE_SUB(CURDATE(), INTERVAL 3 DAY)
    )
";

    $result = Database::search($query);

    if (!$result) {
        writeLog("Query failed.", $logFile);
        exit;
    }

    // Collected here as we loop, then sent as one bulk-different SMS batch at the end.
    // Each student gets a different message (their own amount/due date), so this is
    // SEND_BULK_DIFFERENT, not SEND_BULK_SAME.
    $smsMessages = [];
    // Parallel to $smsMessages (same index): student_id + mobile used for logging only.
    $smsLogEntries = [];
    $skippedNoMobile = 0;

    while ($student = $result->fetch_assoc()) {
        $amount = "Rs. " . number_format((float)$student['amount'], 2);
        $dueDate = date('d-M-y', strtotime($student['due_date']));

        $isOverdue = false;
        $isDueSoon = false;

        if ($student['due_date'] === date('Y-m-d', strtotime('+5 days'))) {
            $isDueSoon = true;
        } elseif ($student['due_date'] === date('Y-m-d', strtotime('-3 days'))) {
            $isOverdue = true;
        }

        if (!$isDueSoon && !$isOverdue) {
            continue;
        }

        if (empty($student['mobile'])) {
            $skippedNoMobile++;
            writeLog("No mobile number on file for student_id {$student['student_id']} - SMS skipped", $logFile);
            continue;
        }

        $smsText = $isOverdue
            ? "Payment Overdue: Installment {$student['installment_no']} of {$amount} for {$student['intake']} was due on {$dueDate}. Ref: {$student['refference']}. Please pay via " . Project::getBasePortalUrl()
            : "Payment Reminder: Installment {$student['installment_no']} of {$amount} for {$student['intake']} is due on {$dueDate}. Ref: {$student['refference']}. Pay via " . Project::getBasePortalUrl();

        $formattedMobile = formatMobileForSms($student['mobile']);

        $smsMessages[] = [
            'to'  => $formattedMobile,
            'msg' => $smsText,
        ];

        $smsLogEntries[] = [
            'student_id' => $student['student_id'],
            'mobile'     => $formattedMobile,
            'type'       => $isOverdue ? 'overdue' : 'reminder',
        ];
    }

    // ---------- Send all queued SMS reminders ----------
    $smsSentBatches = 0;
    $smsFailedBatches = 0;

    if (!empty($smsMessages)) {
        try {
            $sms = new SmsHelper();

            if (count($smsMessages) == 1) {
                $smsResult = $sms->sendSingle($smsMessages[0]['to'], $smsMessages[0]['msg']);

                if ($smsResult['success']) {
                    $smsSentBatches++;
                    $status = 'SENT';
                } else {
                    $smsFailedBatches++;
                    $status = 'FAILED';
                    writeLog("SMS failed: " . ($smsResult['raw'] ?? json_encode($smsResult)), $logFile);
                }

                $entry = $smsLogEntries[0];
                writeLog("SMS {$status} ({$entry['type']}) - student_id: {$entry['student_id']}, mobile: {$entry['mobile']}", $logFile);
            } else {
                $smsResults = $sms->sendBulkDifferent($smsMessages);

                $allSuccess = true;
                foreach ($smsResults as $batchResult) {
                    if ($batchResult['success']) {
                        $smsSentBatches++;
                    } else {
                        $smsFailedBatches++;
                        $allSuccess = false;
                        writeLog("SMS batch failed: " . ($batchResult['raw'] ?? json_encode($batchResult)), $logFile);
                    }
                }

                // Log every recipient (the gateway reports per batch, not per recipient)
                $status = $allSuccess ? 'SENT' : 'SENT/FAILED (check batch errors above)';
                foreach ($smsLogEntries as $entry) {
                    writeLog("SMS {$status} ({$entry['type']}) - student_id: {$entry['student_id']}, mobile: {$entry['mobile']}", $logFile);
                }

                writeLog("SMS: " . count($smsMessages) . " reminder(s) queued across "
                    . count($smsResults) . " batch(es). Sent: {$smsSentBatches}, Failed: {$smsFailedBatches}", $logFile);
            }
        } catch (Exception $e) {
            writeLog("SMS error: " . $e->getMessage(), $logFile);
        }
    } else {
        writeLog("No SMS reminders to send.", $logFile);
    }

    writeLog("Completed. SMS batches sent: {$smsSentBatches}, failed: {$smsFailedBatches}. Skipped (no mobile): {$skippedNoMobile}", $logFile);
    echo "Completed. SMS batches sent: {$smsSentBatches}, failed: {$smsFailedBatches}. Skipped (no mobile): {$skippedNoMobile}" . PHP_EOL;
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
