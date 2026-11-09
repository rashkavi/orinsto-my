<?php

/**
 * markAttendance.php  (Fingerprint-only, Hikvision DS-K1T320MFWX)
 *
 * Receives real-time fingerprint punch events pushed by the terminal via
 * ISAPI "HTTP Listening" mode and marks user attendance automatically.
 *
 * ------------------------------------------------------------------
 * SCHEMA REQUIREMENT (run sql/readd_marked_by_nullable.sql first)
 * ------------------------------------------------------------------
 * marked_by must be NULLABLE. Fingerprint punches insert it as NULL -
 * MySQL/InnoDB skips the foreign key check for NULL values, so no dummy
 * "system user" row is needed. Manual dashboard check-ins still set a
 * real users.user_id. This is also how the dashboard tells them apart:
 *   marked_by IS NULL      -> punched via the fingerprint device
 *   marked_by IS NOT NULL  -> marked manually by a staff member
 *
 * ------------------------------------------------------------------
 * HOW THE DEVICE TALKS TO THIS FILE
 * ------------------------------------------------------------------
 * On the terminal (or via its web UI at http://<device-ip>):
 *   Configuration > Network > Advanced Settings > HTTP Listening
 *     Destination IP Address : your server's IP/domain
 *     URL                    : /path/to/markAttendance.php
 *     Port                   : 80 or 443
 *     Protocol               : HTTP or HTTPS
 *
 * Whenever someone authenticates (fingerprint match), the device POSTs a
 * multipart/form-data request whose "event_log" part contains XML like:
 *
 *   <EventNotificationAlert>
 *     <dateTime>2026-08-19T09:02:11+05:30</dateTime>
 *     <eventType>AccessControllerEvent</eventType>
 *     <AccessControllerEvent>
 *       <employeeNoString>7</employeeNoString>
 *       <name>John Doe</name>
 *       <currentVerifyMode>fingerPrint</currentVerifyMode>
 *     </AccessControllerEvent>
 *   </EventNotificationAlert>
 *
 * `employeeNoString` is the Employee No set when enrolling the person's
 * fingerprint on the device. We match it against users.fingerprint_employee_no
 * first, then fall back to treating it as users.user_id directly if no
 * mapping row exists - so this works whether or not you've added that
 * mapping column.
 *
 * Some firmware sends plain XML in the raw POST body instead of
 * multipart - this file handles both.
 */

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");

// Shared secret - only requests carrying this exact key are accepted.
// Set the SAME value here and in the URL you configure on the device's
// HTTP Listening page, e.g.:
//   /api/markAttendance.php?key=REPLACE_WITH_A_LONG_RANDOM_STRING
// Generate a random one with: php -r "echo bin2hex(random_bytes(24));"
define('FP_SHARED_SECRET', '0ff1bacbfd96d36b5bdf673c706b20496919ea5752bee3c6');

header('Content-Type: text/xml');

if (!isset($_GET['key']) || !hash_equals(FP_SHARED_SECRET, $_GET['key'])) {
    http_response_code(403);
    error_log("markAttendance: rejected request with missing/invalid key");
    echo '<ResponseStatus><statusCode>0</statusCode><statusString>Unauthorized</statusString></ResponseStatus>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Device sometimes does a GET to check the listener is alive
    echo '<ResponseStatus><statusCode>1</statusCode><statusString>OK</statusString></ResponseStatus>';
    exit();
}

$xml = extractEventXml();

if ($xml === null) {
    http_response_code(400);
    error_log("markAttendance: could not extract event XML from Hikvision push");
    echo '<ResponseStatus><statusCode>0</statusCode><statusString>No event data</statusString></ResponseStatus>';
    exit();
}

$eventType = (string)($xml->eventType ?? '');

if ($eventType !== 'AccessControllerEvent') {
    // Ignore heartbeats, tamper alarms, video loss, etc.
    echo '<ResponseStatus><statusCode>1</statusCode><statusString>Ignored</statusString></ResponseStatus>';
    exit();
}

$ace = $xml->AccessControllerEvent ?? null;

if ($ace === null || empty((string)$ace->employeeNoString)) {
    echo '<ResponseStatus><statusCode>1</statusCode><statusString>No employee match</statusString></ResponseStatus>';
    exit();
}

$employeeNo = trim((string)$ace->employeeNoString);
$eventDateTime = (string)$xml->dateTime;

$result = markUserAttendanceFromFingerprint($employeeNo, $eventDateTime);

if ($result === true) {
    error_log("markAttendance: punch recorded for employeeNo {$employeeNo}");
} else {
    error_log("markAttendance: punch skipped for employeeNo {$employeeNo}: {$result}");
}

echo '<ResponseStatus><statusCode>1</statusCode><statusString>OK</statusString></ResponseStatus>';
exit();


function extractEventXml()
{
    $raw = file_get_contents('php://input');

    if (empty($raw)) {
        return null;
    }

    $trimmed = ltrim($raw);
    if (strpos($trimmed, '<?xml') === 0 || strpos($trimmed, '<EventNotificationAlert') === 0) {
        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($trimmed);
        return $parsed !== false ? $parsed : null;
    }

    if (preg_match('/<EventNotificationAlert.*?<\/EventNotificationAlert>/s', $raw, $matches)) {
        libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($matches[0]);
        return $parsed !== false ? $parsed : null;
    }

    return null;
}

function markUserAttendanceFromFingerprint($employeeNo, $eventDateTimeRaw)
{
    $employeeNoEscaped = Database::$connection->real_escape_string($employeeNo);
    $userId = null;

    // Try the mapping column first, if users.fingerprint_employee_no exists
    // and has a matching row.
    $userLookup = @Database::search("
        SELECT user_id FROM users
        WHERE fingerprint_employee_no = '" . $employeeNoEscaped . "'
        LIMIT 1
    ");
    if ($userLookup && $userLookup->num_rows > 0) {
        $userId = $userLookup->fetch_assoc()['user_id'];
    } elseif (ctype_digit($employeeNo)) {
        // Fallback: treat the device's Employee No as the user_id directly.
        $directLookup = Database::search("
            SELECT user_id FROM users WHERE user_id = '" . $employeeNoEscaped . "' LIMIT 1
        ");
        if ($directLookup->num_rows > 0) {
            $userId = $directLookup->fetch_assoc()['user_id'];
        }
    }

    if ($userId === null) {
        return "No staff member matched for Employee No {$employeeNo}";
    }

    $timezone = new DateTimeZone("Asia/Colombo");

    if (!empty($eventDateTimeRaw)) {
        try {
            $dateTime = new DateTime($eventDateTimeRaw);
            $dateTime->setTimezone($timezone);
        } catch (Exception $e) {
            $dateTime = new DateTime("now", $timezone);
        }
    } else {
        $dateTime = new DateTime("now", $timezone);
    }

    $markTime = $dateTime->format('Y-m-d H:i:s');
    $date     = $dateTime->format('Y-m-d');
    $nextDate = (clone $dateTime)->modify('+1 day')->format('Y-m-d');

    // The single cutoff that decides ON_TIME vs LATE_COMING for check-ins.
    $nineAM = new DateTime($date . ' 09:00:00', $timezone);

    // Duplicate-punch guard - Hikvision can resend the same event on
    // network retry, and a person can also double-tap the sensor.
    $dupCheck = Database::search("
        SELECT user_in_out_record_id FROM user_in_out_records
        WHERE users_user_id = '" . $userId . "'
        AND date_updated >= DATE_SUB('" . $markTime . "', INTERVAL 30 SECOND)
        AND date_updated <= DATE_ADD('" . $markTime . "', INTERVAL 30 SECOND)
        LIMIT 1
    ");
    if ($dupCheck->num_rows > 0) {
        return true; // treat as already recorded, not an error
    }

    // Direction: toggle off today's real count, exactly like the QR flow -
    // never trust anything the device itself reports as "in" vs "out".
    $countToday = Database::search("
        SELECT COUNT(*) AS count FROM user_in_out_records
        WHERE users_user_id = '" . $userId . "'
        AND date_updated >= '" . $date . "'
        AND date_updated < '" . $nextDate . "'
    ")->fetch_assoc();

    $aStatus = ($countToday['count'] % 2 == 0) ? 1 : 2; // 1 = check-in, 2 = check-out

    // marked_by is deliberately omitted here - it's nullable, and NULL is
    // exactly how the dashboard identifies a fingerprint-originated punch.
    if ($aStatus == 1) {
        $checkInStatusValue = ($dateTime <= $nineAM) ? 'ON_TIME' : 'LATE_COMING';

        Database::iud("
            INSERT INTO user_in_out_records
                (users_user_id, student_in_out_status_student_in_out_status_id,
                 date_updated, check_in_status)
            VALUES
                ('" . $userId . "',
                 '1',
                 '" . $markTime . "',
                 '" . $checkInStatusValue . "')
        ");
    } else {
        Database::iud("
            INSERT INTO user_in_out_records
                (users_user_id, student_in_out_status_student_in_out_status_id,
                 date_updated)
            VALUES
                ('" . $userId . "',
                 '2',
                 '" . $markTime . "')
        ");
    }

    if (Database::$connection->errno) {
        error_log("markAttendance: DB error - " . Database::$connection->error);
        return "DB error: " . Database::$connection->error;
    }

    return true;
}