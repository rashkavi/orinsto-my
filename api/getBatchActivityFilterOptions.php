<?php

/**
 * api/getBatchActivityFilterOptions.php
 * ---------------------------------------
 * Faculty -> Programme -> Intake cascade for batch-activity-report.php,
 * same shape as your existing api/getLectureFeedbackFilterOptions.php
 * (referenced but not attached, so this is written to match the join
 * chain used in lecture-feedback-report.php / attendance-breakdown.php:
 * faculties -> programmes -> intakes -> levels -> subjects). Confirm the
 * programmes/intakes column names line up with your actual schema.
 */

require_once './../includes/UserClass.php';
require_once './../includes/UserPermissionsClass.php';
require_once "./../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$level = $_GET['level'] ?? '';

try {
    if ($level === 'programme') {
        $facultyId = (int)($_GET['faculty-id'] ?? 0);
        if ($facultyId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing faculty-id.']);
            exit;
        }
        $query = "
            SELECT programme_id, programme
            FROM programmes
            WHERE faculties_faculty_id = '{$facultyId}'
            ORDER BY programme ASC
        ";
        $programmes = Database::search($query)->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'programmes' => $programmes]);
        exit;
    }

    if ($level === 'intake') {
        $programmeId = (int)($_GET['programme-id'] ?? 0);
        if ($programmeId <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing programme-id.']);
            exit;
        }
        $query = "
            SELECT intake_id, intake
            FROM intakes
            WHERE programmes_programme_id = '{$programmeId}'
            ORDER BY intake DESC
        ";
        $intakes = Database::search($query)->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['status' => 'success', 'intakes' => $intakes]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'Unknown level parameter.']);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load filter options: ' . $e->getMessage()]);
}
