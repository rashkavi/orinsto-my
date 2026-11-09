<?php



date_default_timezone_set('Asia/Colombo');

require_once(__DIR__ . "/../../includes/connection.php");
require_once(__DIR__ . "/../../includes/UserClass.php");
require_once(__DIR__ . "/../../includes/Project.php");
require_once(__DIR__ . "/../../includes/phpmailer/EmailLayoutRenderer.php");
require_once(__DIR__ . "/../../includes/phpmailer/EmailTemplateContent.php");
require_once(__DIR__ . "/../../includes/phpmailer/EmailNotificationService.php");
require_once(__DIR__ . "/../../includes/phpmailer/mail-process.php");



if (php_sapi_name() !== 'cli') {
    exit("Access denied");
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
    INNER JOIN students_has_intakes AS shi 
        ON cp.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
    INNER JOIN students AS s 
        ON shi.students_student_id = s.student_id
    INNER JOIN login_details AS ld 
        ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN intakes AS i 
        ON shi.intakes_intake_id = i.intake_id
    WHERE cp.payment_status_payment_status_id = '1'
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

    $sentCount = 0;
    $failCount = 0;

    while ($student = $result->fetch_assoc()) {
        $studentName = trim(($student['first_name'] ?? '') . ' ' . ($student['last_name'] ?? ''));
        $amount = "Rs. " . number_format((float)$student['amount'], 2);
        $dueDate = date('d-M-y', strtotime($student['due_date']));

        $templateType = null;

        if ($student['due_date'] === date('Y-m-d', strtotime('+5 days'))) {
            $templateType = EmailTemplateContent::PAYMENT_REMINDER_5_DAYS;
        } elseif ($student['due_date'] === date('Y-m-d', strtotime('-3 days'))) {
            $templateType = EmailTemplateContent::PAYMENT_OVERDUE_3_DAYS;
        }

        if ($templateType === null) {
            continue;
        }

        $mail = EmailNotificationService::build(
            $templateType,
            [
                'student_name'   => $studentName,
                'installment_no' => $student['installment_no'],
                'amount'         => $amount,
                'due_date'       => $dueDate,
                'batch'          => $student['intake'],
                'reference'      => $student['refference'],
                'portal_url'     => Project::getBasePortalUrl()
            ]
        );

        try {
            sendMail($student['email'], $studentName, $mail['subject'], $mail['body']);
            $sentCount++;
            writeLog("Sent to {$student['email']} using template {$templateType}", $logFile);
        } catch (Exception $e) {
            $failCount++;
            writeLog("Failed for {$student['email']} - " . $e->getMessage(), $logFile);
        }
    }

    writeLog("Completed. Sent: {$sentCount}, Failed: {$failCount}", $logFile);
    echo "Completed. Sent: {$sentCount}, Failed: {$failCount}" . PHP_EOL;
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
