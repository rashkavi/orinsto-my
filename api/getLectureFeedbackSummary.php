<?php
/**
 * getLectureFeedbackSummary.php
 * --------------------------------
 * Returns summary cards + a paginated, filterable list of lecture-feedback
 * rows for lecture-feedback-report.php.
 *
 * A "row" is a (student, completed lecture) pair. It's either:
 *  - a submitted feedback record (joined from `lecture_feedback`), or
 *  - a still-pending one (a completed lecture the student attended/was
 *    scheduled for, with no matching feedback row) — only produced when
 *    status filter is "all" or "not_submitted".
 *
 * ASSUMPTIONS — same schema notes as the report page:
 * - `lecture_feedback`: lecture_feedback_id, time_table_time_table_id,
 *   students_student_id, rating, remark, created_at
 * - Eligible students per lecture resolved via `students_has_subjects`
 *   (already used in your lecture-schedule query) for elective subjects,
 *   and via `students_has_intakes` + `levels` for core subjects — mirrors
 *   the WHERE clause structure from get_lecture_schedule_with_feedback_check.php.
 * - `students` table: student_id, sId, first_name, last_name, title_title_id,
 *   profile_img (title text resolved via `title` table, same as your
 *   lecture-schedule query's lecturer title join)
 *
 * If any of these table/column names are off, this query needs a quick
 * pass against your actual schema — the report page and JS don't care,
 * they just consume whatever this returns in the shape below.
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

$facultyId   = (int) ($input['faculty_id'] ?? 0);
$programmeId = (int) ($input['programme_id'] ?? 0);
$intakeId    = (int) ($input['intake_id'] ?? 0);
$subjectId   = (int) ($input['subject_id'] ?? 0);
$lectureId   = (int) ($input['lecture_id'] ?? 0);
$status      = $input['status'] ?? 'all'; // all | submitted | not_submitted
$rating      = (int) ($input['rating'] ?? 0); // 0 = all ratings, 1-10 = exact match
$search      = trim($input['search'] ?? '');
$startDate   = $input['start_date'] ?? '';
$endDate     = $input['end_date'] ?? '';
$page        = max(1, (int) ($input['page'] ?? 1));
$perPage     = max(1, min(100, (int) ($input['per_page'] ?? 25)));
$offset      = ($page - 1) * $perPage;

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a valid date range.']);
    exit;
}
$startDateEsc = Database::escape($startDate);
$endDateEsc   = Database::escape($endDate);

// Shared filter fragment applied to every branch below
$sharedWhere = "
    tt.date BETWEEN '" . $startDateEsc . "' AND '" . $endDateEsc . "'
    AND tt.time_table_status_time_table_status_id = 1
    AND tt.lecture_type_lecture_type_id != 3
    " . ($facultyId > 0 ? "AND f.faculty_id = '" . Database::escape($facultyId) . "' " : "") .
    ($programmeId > 0 ? "AND p.programme_id = '" . Database::escape($programmeId) . "' " : "") .
    ($intakeId > 0 ? "AND i.intake_id = '" . Database::escape($intakeId) . "' " : "") .
    ($subjectId > 0 ? "AND s.subject_id = '" . Database::escape($subjectId) . "' " : "") .
    ($lectureId > 0 ? "AND tt.time_table_id = '" . Database::escape($lectureId) . "' " : "") .
    // Exact 1-10 rating filter — only matches submitted rows (lf.rating is
    // NULL for anything pending, so this naturally excludes those too).
    ($rating >= 1 && $rating <= 10 ? "AND lf.rating = '" . Database::escape($rating) . "' " : "") .
    // Search by student name or student ID, same free-text box as the students list page.
    ($search !== '' ? "AND (
        shi.student_id AS sId LIKE '%" . Database::escape($search) . "%'
        OR ld.first_name LIKE '%" . Database::escape($search) . "%'
        OR ld.last_name LIKE '%" . Database::escape($search) . "%'
        OR CONCAT(ld.first_name, ' ', ld.last_name) LIKE '%" . Database::escape($search) . "%'
    ) " : "");

// Base FROM/JOIN chain: every (student, completed lecture) pair eligible for feedback
$baseJoins = "
    FROM time_table tt
    INNER JOIN subjects s ON tt.subjects_subject_id = s.subject_id
    INNER JOIN lecture_type lt ON tt.lecture_type_lecture_type_id = lt.lecture_type_id
    INNER JOIN levels l ON s.levels_level_id = l.level_id
    INNER JOIN intakes i ON l.intakes_intake_id = i.intake_id
    INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
    INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
    INNER JOIN students_has_intakes shi ON shi.intakes_intake_id = i.intake_id AND shi.status_status_id = 1
    INNER JOIN students st ON st.student_id = shi.students_student_id
    INNER JOIN login_details ld ON st.login_details_login_details_id = ld.login_details_id
    INNER JOIN title ti ON st.title_title_id = ti.title_id
    LEFT JOIN lecture_feedback lf ON lf.time_table_time_table_id = tt.time_table_id AND lf.students_student_id = st.student_id
    WHERE " . $sharedWhere . "
    AND (
        s.subject_type_subject_type_id = 1
        OR (
            s.subject_type_subject_type_id = 2
            AND EXISTS (
                SELECT 1 FROM students_has_subjects
                WHERE students_student_id = st.student_id AND subjects_subject_id = s.subject_id
            )
        )
    )
";

$statusFilterSql = '';
if ($status === 'submitted') {
    $statusFilterSql = " AND lf.lecture_feedback_id IS NOT NULL";
} elseif ($status === 'not_submitted') {
    $statusFilterSql = " AND lf.lecture_feedback_id IS NULL";
}

// ---- Summary cards (unfiltered by pagination, filtered by the rest) ----
$summarySql = "
    SELECT
        COUNT(*) AS total_rows,
        SUM(CASE WHEN lf.lecture_feedback_id IS NOT NULL THEN 1 ELSE 0 END) AS total_submitted,
        AVG(CASE WHEN lf.lecture_feedback_id IS NOT NULL THEN lf.rating ELSE NULL END) AS average_rating
    " . $baseJoins;
$summaryResult = Database::search($summarySql)->fetch_assoc();

$totalRows      = (int) ($summaryResult['total_rows'] ?? 0);
$totalSubmitted = (int) ($summaryResult['total_submitted'] ?? 0);
$totalPending   = $totalRows - $totalSubmitted;
$averageRating  = $summaryResult['average_rating'] !== null ? round((float) $summaryResult['average_rating'], 1) : 0;
$responseRate   = $totalRows > 0 ? round(($totalSubmitted / $totalRows) * 100) : 0;

// ---- Paginated rows ----
$countSql = "SELECT COUNT(*) AS cnt " . $baseJoins . $statusFilterSql;
$total = (int) Database::search($countSql)->fetch_assoc()['cnt'];

$rowsSql = "
    SELECT
        shi.student_id AS sId, ld.first_name, ld.last_name, st.profile_img, ti.title,
        f.faculty, p.programme, i.intake,
        s.subject, lt.lecture_type,
        tt.date AS lecture_date,
        lf.rating, lf.remark, lf.created_at AS submitted_at
    " . $baseJoins . $statusFilterSql . "
    ORDER BY (lf.lecture_feedback_id IS NULL) ASC, lf.created_at DESC, tt.date DESC
    LIMIT " . (int) $perPage . " OFFSET " . (int) $offset;

$rowsResult = Database::search($rowsSql)->fetch_all(MYSQLI_ASSOC);

$rows = [];
foreach ($rowsResult as $r) {
    $rows[] = [
        'sId' => $r['sId'],
        'first_name' => htmlspecialchars($r['first_name']),
        'last_name' => htmlspecialchars($r['last_name']),
        'title' => htmlspecialchars($r['title']),
        'profile_img' => $r['profile_img'],
        'faculty' => htmlspecialchars($r['faculty']),
        'programme' => htmlspecialchars($r['programme']),
        'intake' => htmlspecialchars($r['intake']),
        'subject' => htmlspecialchars($r['subject']),
        'lecture_type' => htmlspecialchars($r['lecture_type']),
        'lecture_date' => $r['lecture_date'],
        'submitted' => $r['rating'] !== null,
        'rating' => $r['rating'] !== null ? (int) $r['rating'] : null,
        'remark' => $r['remark'] !== null ? htmlspecialchars($r['remark']) : null,
        'submitted_at' => $r['submitted_at'],
    ];
}

echo json_encode([
    'total_submitted' => $totalSubmitted,
    'total_pending' => $totalPending,
    'response_rate' => $responseRate,
    'average_rating' => $averageRating,
    'rows' => $rows,
    'total' => $total,
    'page' => $page,
    'per_page' => $perPage,
]);