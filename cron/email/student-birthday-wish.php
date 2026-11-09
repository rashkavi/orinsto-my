<?php

declare(strict_types=1);

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


$logFile = __DIR__ . '/logs/student-birthday-notification-log.txt';

function writeLog(string $message, string $logFile): void
{
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    $query = "
        SELECT 
            `students`.`student_id` AS `sId`,
            `login_details`.`first_name`,
            `login_details`.`last_name`,
            `students`.`date_of_birth`,
          
            `login_details`.`email`,
           
            `title`.`title`
          
        FROM `students`
        INNER JOIN `login_details`
            ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
        INNER JOIN `title`
            ON `students`.`title_title_id` = `title`.`title_id`
        
        WHERE 
            `students`.`date_of_birth` IS NOT NULL
            AND MONTH(`students`.`date_of_birth`) = '" . $todayMonth . "'
            AND DAY(`students`.`date_of_birth`) = '" . $todayDay . "'
            
           AND `login_details`.`status_status_id` = 1
        ORDER BY `students`.`student_id` DESC
    ";

    $result = Database::search($query);

    if (!$result) {
        writeLog("Query failed.", $logFile);
        exit;
    }

    $sentCount = 0;
    $failCount = 0;

    while ($student = $result->fetch_assoc()) {
        try {
            $firstName = $student['first_name'];
            $lastName = $student['last_name'];
            $studentName = $student['title'] . ' ' . $firstName . ' ' . $lastName;
            $studentId = $student['sId'];

            // Build birthday email
            $emailData = EmailNotificationService::build(
                EmailTemplateContent::BIRTHDAY_WISH,
                ['student_name' => $studentName]
            );

            // Send email
            if (sendMail($student['email'] ?? '', $studentName, $emailData['subject'], $emailData['body'])) {
                // Optional: Send WhatsApp notification too
                // WhatsAppService::sendBirthdayWish($whatsapp, $studentName);

                $sentCount++;
                writeLog("Birthday email sent to {$studentName} ({$studentId})", $logFile);
            } else {
                $failCount++;
                writeLog("Failed to send birthday email to {$studentName} ({$studentId})", $logFile);
            }
        } catch (Throwable $e) {
            $failCount++;
            writeLog("Error processing student {$student['sId']}: " . $e->getMessage(), $logFile);
        }
    }

    writeLog("Completed. Sent: {$sentCount}, Failed: {$failCount}", $logFile);
    echo "Completed. Sent: {$sentCount}, Failed: {$failCount}" . PHP_EOL;
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
