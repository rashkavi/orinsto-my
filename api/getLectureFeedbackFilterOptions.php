<?php

/**
 * getLectureFeedbackFilterOptions.php
 * -------------------------------------
 * Cascading Programme/Intake/Subject options for lecture-feedback-report.php,
 * mirroring the pattern in your existing api/getCounselorFilterOptions.php
 * but without counselor scoping (admin-wide).
 *
 * POST body: { stage: 'programmes'|'intakes'|'subjects', faculty_id, programme_id, intake_id }
 */
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once '../includes/connection.php';

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication Failed!']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$stage = $input['stage'] ?? '';
$facultyId = (int) ($input['faculty_id'] ?? 0);
$programmeId = (int) ($input['programme_id'] ?? 0);
$intakeId = (int) ($input['intake_id'] ?? 0);
$subjectId = (int) ($input['subject_id'] ?? 0);

$response = new stdClass();

if ($stage === 'programmes') {
    $sql = "SELECT p.programme_id, p.programme
            FROM programmes p
            WHERE 1=1 " .
        ($facultyId > 0 ? "AND p.faculties_faculty_id = '" . Database::escape($facultyId) . "' " : "") .
        "ORDER BY p.programme ASC";
    $response->programmes = Database::search($sql)->fetch_all(MYSQLI_ASSOC);
} elseif ($stage === 'intakes') {
    $sql = "SELECT i.intake_id, i.intake
            FROM intakes i
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            WHERE 1=1 " .
        ($facultyId > 0 ? "AND p.faculties_faculty_id = '" . Database::escape($facultyId) . "' " : "") .
        ($programmeId > 0 ? "AND i.programmes_programme_id = '" . Database::escape($programmeId) . "' " : "") .
        "ORDER BY i.intake ASC";
    $response->intakes = Database::search($sql)->fetch_all(MYSQLI_ASSOC);
} elseif ($stage === 'subjects') {
    $sql = "SELECT s.subject_id, s.subject
            FROM subjects s
            INNER JOIN levels l ON s.levels_level_id = l.level_id
            INNER JOIN intakes i ON l.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            WHERE 1=1 " .
        ($facultyId > 0 ? "AND p.faculties_faculty_id = '" . Database::escape($facultyId) . "' " : "") .
        ($programmeId > 0 ? "AND i.programmes_programme_id = '" . Database::escape($programmeId) . "' " : "") .
        ($intakeId > 0 ? "AND i.intake_id = '" . Database::escape($intakeId) . "' " : "") .
        "GROUP BY s.subject_id, s.subject
            ORDER BY s.subject ASC";
    $response->subjects = Database::search($sql)->fetch_all(MYSQLI_ASSOC);
} elseif ($stage === 'lectures') {
    // Distinct time_table sessions for the selected subject — `tt.lecture`
    // is the actual per-session title text (e.g. "Introduction to Cognitive
    // Psychology"), separate from `lecture_type` (Physical/Online). This is
    // what populates the new Lecture dropdown and what it filters by
    // (tt.time_table_id) once one is picked.
    if ($subjectId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'A subject must be selected before loading lectures.']);
        exit;
    }
    $sql = "SELECT tt.time_table_id, tt.lecture, tt.date
            FROM time_table tt
            WHERE tt.subjects_subject_id = '" . Database::escape($subjectId) . "'
              AND tt.lecture_type_lecture_type_id != 3
            ORDER BY tt.date DESC, tt.start_time DESC";
    $lectures = Database::search($sql)->fetch_all(MYSQLI_ASSOC);
    foreach ($lectures as &$lec) {
        $lec['lecture'] = htmlspecialchars($lec['lecture']);
    }
    $response->lectures = $lectures;
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown stage']);
    exit;
}

echo json_encode($response);
