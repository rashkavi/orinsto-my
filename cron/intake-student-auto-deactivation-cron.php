<?php

/**
 * cron/intake-student-auto-deactivation-cron.php
 * ------------------------------------------------------------------
 * Ends temporary activations: intake students activated from intake-details
 * (Activate → deactivate date) are set back to Inactive on that date.
 * Rows come from students_has_intakes_temp_activations.
 *
 * Run daily, shortly after midnight Sri Lanka time, e.g.:
 *   5 0 * * *  php /path/to/cron/intake-student-auto-deactivation-cron.php
 * (Missed days are caught up: every PENDING row with deactivate_date <= today is processed.)
 *
 * Per row, in its own transaction:
 *   student Active        → set Inactive, log "Status changed from Active to Inactive (auto-deactivated)", DONE
 *   student already Inactive → DONE ("Already inactive")
 *   student Discontinued  → CANCELLED ("Student discontinued")
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Colombo');

// Same include layout as cron/student-lecture-reminder-cron.php — adjust if this cron lives elsewhere.
require_once(__DIR__ . "/../../includes/connection.php");

if (php_sapi_name() !== 'cli') {
    exit("Access denied");
}

$logFile = __DIR__ . '/logs/intakeStudentAutoDeactivation-cron.txt';
function iad_log(string $message, string $logFile): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    echo $line;
    if (is_dir(dirname($logFile)) || @mkdir(dirname($logFile), 0775, true)) {
        @file_put_contents($logFile, $line, FILE_APPEND);
    }
}

if (!Database::$connection) {
    Database::search("SELECT 1");
}
$conn = Database::$connection;
$today = date('Y-m-d');

$res = $conn->query(
    "SELECT `id` FROM `students_has_intakes_temp_activations`
     WHERE `schedule_status` = 'PENDING' AND `deactivate_date` <= '" . $conn->real_escape_string($today) . "'
     ORDER BY `deactivate_date`, `students_has_intakes_temp_activation_id`"
);
if (!$res) {
    iad_log('Query failed: ' . $conn->error, $logFile);
    exit(1);
}
$ids = [];
while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
iad_log(count($ids) . ' auto-deactivation(s) due on or before ' . $today, $logFile);

$done = $skipped = $failed = 0;
foreach ($ids as $rowId) {
    $conn->begin_transaction();
    try {
        // Lock the row + the student's intake row.
        $st = $conn->prepare(
            "SELECT a.`id`, a.`deactivate_date`, a.`note`, a.`created_by`, a.`schedule_status`,
                    shi.`students_has_intakes_id` AS `shi_id`, shi.`status_status_id` AS `status_id`
             FROM `students_has_intakes_temp_activations` a
             INNER JOIN `students_has_intakes` shi
                     ON shi.`students_has_intakes_id` = a.`students_has_intakes_students_has_intakes_id`
             WHERE a.`students_has_intakes_temp_activation_id` = ? FOR UPDATE"
        );
        $st->bind_param('i', $rowId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row || $row['schedule_status'] !== 'PENDING') {
            $conn->rollback();
            continue; // handled by someone else meanwhile
        }

        $now = date('Y-m-d H:i:s');
        $shiId = (int)$row['shi_id'];
        $statusId = (int)$row['status_id'];

        if ($statusId === 1) {
            $u = $conn->prepare("UPDATE `students_has_intakes` SET `status_status_id` = 2 WHERE `students_has_intakes_id` = ? AND `status_status_id` = 1");
            $u->bind_param('i', $shiId);
            if (!$u->execute()) throw new Exception($u->error);
            $u->close();

            // Logged under the user who did the temporary activation.
            $log = 'Status changed from Active to Inactive (auto-deactivated)';
            $userId = (int)$row['created_by'];
            $note = 'Temporary activation ended on ' . $row['deactivate_date'] . '.';
            $l = $conn->prepare(
                "INSERT INTO `students_has_intakes_logs`
                    (`students_has_intakes_students_has_intakes_id`, `users_user_id`, `date_updated`, `log`, `note`,
                     `students_inactive_reasons_students_inactive_reasons_id`)
                 VALUES (?, ?, ?, ?, ?, NULL)"
            );
            $l->bind_param('iisss', $shiId, $userId, $now, $log, $note);
            if (!$l->execute()) throw new Exception($l->error);
            $l->close();
            $newStatus = 'DONE';
            $why = 'Deactivated';
            $done++;
        } elseif ($statusId === 2) {
            $newStatus = 'DONE';
            $why = 'Already inactive';
            $skipped++;
        } else {
            $newStatus = 'CANCELLED';
            $why = 'Already Paid';
            $skipped++;
        }

        $m = $conn->prepare("UPDATE `students_has_intakes_temp_activations` SET `schedule_status` = ?, `processed_at` = ?, `process_note` = ? WHERE `id` = ?");
        $m->bind_param('sssi', $newStatus, $now, $why, $rowId);
        if (!$m->execute()) throw new Exception($m->error);
        $m->close();

        $conn->commit();
        iad_log("Row #$rowId (shi $shiId): $why", $logFile);
    } catch (Throwable $e) {
        $conn->rollback();
        $failed++;
        iad_log("Row #$rowId FAILED: " . $e->getMessage() . ' (left PENDING, will retry next run)', $logFile);
    }
}

iad_log("Finished: $done deactivated, $skipped skipped, $failed failed", $logFile);
