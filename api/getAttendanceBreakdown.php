<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Your session has expired. Please log in again.']);
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if ($sessionUserRole == null) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have access to this page.']);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

// Same counselor resolution used by the assigned-students list: default to
// whoever is logged in, only a Pro/AcademicPro user can pass counselor_id
// to look at a different counselor's roster.
$counselorUserId = (int) $sessionUserObject['user_id'];

$input = json_decode(file_get_contents('php://input'), true);

if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($input['counselor_id'])) {
    $counselorUserId = (int) $input['counselor_id'];
}

$facultyId   = isset($input['faculty_id']) ? (int) $input['faculty_id'] : 0;   // 0 = all
$programmeId = isset($input['programme_id']) ? (int) $input['programme_id'] : 0; // 0 = all
$intakeId    = isset($input['intake_id']) ? (int) $input['intake_id'] : 0;     // 0 = all
$subjectId   = isset($input['subject_id']) ? (int) $input['subject_id'] : 0;   // 0 = all
$startDate   = isset($input['start_date']) ? $input['start_date'] : null;
$endDate     = isset($input['end_date']) ? $input['end_date'] : null;

const AT_RISK_STREAK_THRESHOLD = 3;

function isValidDate($date)
{
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

if (!isValidDate($startDate) || !isValidDate($endDate) || $startDate > $endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a valid date range.']);
    exit;
}

$startObj = new DateTime($startDate);
$endObj   = new DateTime($endDate);
$dayDiff  = (int)$endObj->diff($startObj)->format('%a');
if ($dayDiff > 366) {
    http_response_code(400);
    echo json_encode(['error' => 'Please select a range of one year or less.']);
    exit;
}

$counselorEsc = Database::escape($counselorUserId);
$startDateEsc = Database::escape($startDate);
$endDateEsc   = Database::escape($endDate);

$timezone = new DateTimeZone("Asia/Colombo");
$now = new DateTime("now", $timezone);

function emptyResult()
{
    echo json_encode([
        'total_students'     => 0,
        'lectures_held'      => 0,
        'average_attendance' => 0,
        'status_breakdown'   => ['PRESENT' => 0, 'ABSENT' => 0, 'HALF_VERIFY' => 0, 'WATCHED' => 0],
        'trend'              => [],
        'students'           => [],
        'at_risk'            => [],
    ]);
    exit;
}

// ----------------------------------------------------------------------
// 1. Roster = this counselor's live assigned students, wherever they
//    happen to sit - potentially several faculties/programmes/intakes at
//    once. Faculty/Programme/Intake are optional filters on top of that,
//    not a required starting point.
// ----------------------------------------------------------------------
$rosterQuery = "
    SELECT
        shi.students_has_intakes_id AS shi_id,
        shi.student_id AS sId,
        s.student_id AS student_pk,
        ld.first_name,
        ld.last_name,
        title.title,
        s.profile_img,
        i.intake_id,
        i.intake,
        p.programme_id,
        p.programme,
        f.faculty_id,
        f.faculty
    FROM students_has_intakes shi
    INNER JOIN students s ON s.student_id = shi.students_student_id
    INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
    INNER JOIN title ON s.title_title_id = title.title_id
    INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
    INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
    INNER JOIN faculties f ON p.faculties_faculty_id = f.faculty_id
    WHERE  shi.status_status_id != '3'
";

if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
    $rosterQuery .= " AND shi.counselor_user_id = '" . $counselorEsc . "'";
}
if ($facultyId > 0) {
    $rosterQuery .= " AND f.faculty_id = '" . Database::escape($facultyId) . "'";
}
if ($programmeId > 0) {
    $rosterQuery .= " AND p.programme_id = '" . Database::escape($programmeId) . "'";
}
if ($intakeId > 0) {
    $rosterQuery .= " AND i.intake_id = '" . Database::escape($intakeId) . "'";
}

$studentRows = Database::search($rosterQuery)->fetch_all(MYSQLI_ASSOC);

if (empty($studentRows)) {
    emptyResult();
}

$studentMeta = []; // [student_pk] => {sId, first_name, last_name, title, profile_img, intake_id, intake, programme, faculty}
foreach ($studentRows as $row) {
    $studentMeta[$row['student_pk']] = [
        'sId'         => $row['sId'],
        'first_name'  => $row['first_name'],
        'last_name'   => $row['last_name'],
        'title'       => $row['title'],
        'profile_img' => (!empty($row['profile_img']) && file_exists('../assets/imgs/students/' . $row['profile_img'])) ? $row['profile_img'] : null,
        'intake_id'   => $row['intake_id'],
        'intake'      => $row['intake'],
        'programme'   => $row['programme'],
        'faculty'     => $row['faculty'],
    ];
}
$totalStudents = count($studentMeta);

// ----------------------------------------------------------------------
// 2. Lectures across every intake represented in the roster (or just the
//    one intake if the caller narrowed to it), optionally narrowed to one
//    subject. Carries intake_id so eligibility can be checked per-student
//    against the student's own intake, not just "in the roster somewhere".
// ----------------------------------------------------------------------
$rosterIntakeIds = array_values(array_unique(array_column($studentMeta, 'intake_id')));
$rosterIntakeIdsEsc = implode(',', array_map('intval', $rosterIntakeIds));

$lecturesQuery = "
    SELECT
        tt.time_table_id,
        tt.date,
        tt.start_time,
        tt.end_time,
        tt.lecture_type_lecture_type_id AS lecture_type_id,
        s.subject_id,
        s.subject,
        s.subject_type_subject_type_id AS subject_type_id,
        l.intakes_intake_id AS intake_id
    FROM time_table tt
    INNER JOIN lecturers lec ON tt.lecturers_lecturers_id = lec.lecturers_id
    INNER JOIN subjects s ON lec.subjects_subject_id = s.subject_id
    INNER JOIN levels l ON s.levels_level_id = l.level_id
    WHERE l.intakes_intake_id IN (" . $rosterIntakeIdsEsc . ")
      AND tt.time_table_status_time_table_status_id = 5
      AND tt.date BETWEEN '" . $startDateEsc . "' AND '" . $endDateEsc . "'
";
if ($subjectId > 0) {
    $lecturesQuery .= " AND s.subject_id = '" . Database::escape($subjectId) . "'";
}
$lecturesQuery .= " ORDER BY tt.date ASC, tt.start_time ASC";

$lectureRows = Database::search($lecturesQuery)->fetch_all(MYSQLI_ASSOC);

if (empty($lectureRows)) {
    echo json_encode([
        'total_students'     => $totalStudents,
        'lectures_held'      => 0,
        'average_attendance' => 0,
        'status_breakdown'   => ['PRESENT' => 0, 'ABSENT' => 0, 'HALF_VERIFY' => 0, 'WATCHED' => 0],
        'trend'              => [],
        'students'           => [],
        'at_risk'            => [],
    ]);
    exit;
}

$lectureIds = array_column($lectureRows, 'time_table_id');
$lectureIdsEsc = implode(',', array_map('intval', $lectureIds));

// ----------------------------------------------------------------------
// 3. Elective membership - which roster students are enrolled in which
//    type-2 (opt-in) subjects, so a core-subject lecture doesn't get held
//    against a student who never signed up for that elective.
// ----------------------------------------------------------------------
$electiveSubjectIds = array_values(array_unique(array_filter(array_map(function ($lr) {
    return $lr['subject_type_id'] == 2 ? $lr['subject_id'] : null;
}, $lectureRows))));

$electiveMembership = []; // [subject_id][student_pk] => true
if (!empty($electiveSubjectIds)) {
    $electiveIdsEsc = implode(',', array_map('intval', $electiveSubjectIds));
    $membershipQuery = "
        SELECT students_student_id AS student_pk, subjects_subject_id AS subject_id
        FROM students_has_subjects
        WHERE subjects_subject_id IN (" . $electiveIdsEsc . ")
          AND status_status_id = '1'
    ";
    $membershipRows = Database::search($membershipQuery)->fetch_all(MYSQLI_ASSOC);
    foreach ($membershipRows as $row) {
        $electiveMembership[$row['subject_id']][$row['student_pk']] = true;
    }
}

// ----------------------------------------------------------------------
// 4. Attendance records for these lectures, in one query, keyed by
//    [student_pk][time_table_id] => attendance_status_id.
//    Status IDs: 1 Half Verify, 2 Present, 3 Absent, 4 Watched.
// ----------------------------------------------------------------------
$attendanceQuery = "
    SELECT students_student_id AS student_pk, time_table_time_table_id AS tt_id, attendance_status_attendance_status_id AS status_id
    FROM attendance
    WHERE time_table_time_table_id IN (" . $lectureIdsEsc . ")
";
$attendanceRows = Database::search($attendanceQuery)->fetch_all(MYSQLI_ASSOC);

$attendanceMap = []; // [student_pk][tt_id] => status_id
foreach ($attendanceRows as $row) {
    $attendanceMap[$row['student_pk']][$row['tt_id']] = (int)$row['status_id'];
}

// ----------------------------------------------------------------------
// 5. Walk every (lecture x eligible roster student) pair. A lecture only
//    counts once its end time has passed. A student is only eligible for
//    a lecture if the lecture's intake matches the student's own intake
//    (roster spans multiple intakes, so this stops cross-intake bleed),
//    and if it's an elective, only if they're actually enrolled in it.
// ----------------------------------------------------------------------
$statusBreakdown = ['PRESENT' => 0, 'ABSENT' => 0, 'HALF_VERIFY' => 0, 'WATCHED' => 0];
$dailyTotals = []; // [date] => ['held'=>n, 'present_equivalent'=>n]
$perStudentRecords = []; // [student_pk] => ordered list of ['date','tt_id','outcome']
$perStudentTotals = []; // [student_pk] => ['held'=>n,'present'=>n,'absent'=>n,'half_verify'=>n,'watched'=>n]
$totalHeldLectures = 0;

foreach ($lectureRows as $lecture) {
    $ttId = $lecture['time_table_id'];
    $lectureEnd = new DateTime($lecture['date'] . ' ' . $lecture['end_time'], $timezone);
    if ($now < $lectureEnd) {
        continue; // lecture hasn't finished yet, don't count it either way
    }

    $totalHeldLectures++;

    $lectureIntakeId = $lecture['intake_id'];
    $lectureSubjectId = $lecture['subject_id'];
    $isElective = ($lecture['subject_type_id'] == 2);

    foreach ($studentMeta as $studentPk => $meta) {
        if ($meta['intake_id'] != $lectureIntakeId) {
            continue; // this lecture belongs to a different intake than the student's own
        }
        if ($isElective && empty($electiveMembership[$lectureSubjectId][$studentPk])) {
            continue; // not enrolled in this elective, not accountable for it
        }

        $statusId = $attendanceMap[$studentPk][$ttId] ?? null;

        if ($statusId === 2) {
            $outcome = 'PRESENT';
        } elseif ($statusId === 4) {
            $outcome = 'WATCHED';
        } elseif ($statusId === 1) {
            $outcome = 'HALF_VERIFY';
        } elseif ($statusId === 3) {
            $outcome = 'ABSENT';
        } else {
            // No record at all for a lecture that's already ended = absent.
            $outcome = 'ABSENT';
        }

        $statusBreakdown[$outcome]++;

        if (!isset($dailyTotals[$lecture['date']])) {
            $dailyTotals[$lecture['date']] = ['held' => 0, 'present_equivalent' => 0];
        }
        $dailyTotals[$lecture['date']]['held']++;
        if ($outcome === 'PRESENT' || $outcome === 'WATCHED') {
            $dailyTotals[$lecture['date']]['present_equivalent']++;
        }

        if (!isset($perStudentTotals[$studentPk])) {
            $perStudentTotals[$studentPk] = ['held' => 0, 'present' => 0, 'absent' => 0, 'half_verify' => 0, 'watched' => 0];
        }
        $perStudentTotals[$studentPk]['held']++;
        if ($outcome === 'PRESENT') $perStudentTotals[$studentPk]['present']++;
        elseif ($outcome === 'WATCHED') $perStudentTotals[$studentPk]['watched']++;
        elseif ($outcome === 'HALF_VERIFY') $perStudentTotals[$studentPk]['half_verify']++;
        else $perStudentTotals[$studentPk]['absent']++;

        $perStudentRecords[$studentPk][] = [
            'date'    => $lecture['date'],
            'time'    => $lecture['start_time'],
            'tt_id'   => $ttId,
            'outcome' => $outcome,
        ];
    }
}

ksort($dailyTotals);
$lecturesHeldTotal = 0;
foreach ($dailyTotals as $d) $lecturesHeldTotal += $d['held'];

// ----------------------------------------------------------------------
// 6. Daily trend - % of that day's held-lecture slots that were
//    Present/Watched, across the whole roster.
// ----------------------------------------------------------------------
$trend = [];
foreach ($dailyTotals as $date => $counts) {
    $pct = $counts['held'] > 0 ? round(($counts['present_equivalent'] / $counts['held']) * 100, 1) : 0;
    $trend[] = [
        'date'           => $date,
        'date_label'     => date('d M', strtotime($date)),
        'attendance_pct' => $pct,
    ];
}

// ----------------------------------------------------------------------
// 7. Per-student summary + consecutive-absence streak, now carrying
//    faculty/programme/intake since the roster spans more than one of
//    each.
// ----------------------------------------------------------------------
$students = [];
$atRisk = [];

foreach ($studentMeta as $studentPk => $meta) {
    $totals = $perStudentTotals[$studentPk] ?? ['held' => 0, 'present' => 0, 'absent' => 0, 'half_verify' => 0, 'watched' => 0];
    $records = $perStudentRecords[$studentPk] ?? [];

    $presentEquivalent = $totals['present'] + $totals['watched'];
    $attendancePct = $totals['held'] > 0 ? round(($presentEquivalent / $totals['held']) * 100, 1) : 0;

    $streak = 0;
    $lastAttendedDate = null;
    for ($i = count($records) - 1; $i >= 0; $i--) {
        if ($records[$i]['outcome'] === 'ABSENT') {
            $streak++;
        } else {
            $lastAttendedDate = $records[$i]['date'];
            break;
        }
    }

    $studentRow = [
        'sId'              => $meta['sId'],
        'first_name'       => $meta['first_name'],
        'last_name'        => $meta['last_name'],
        'title'            => $meta['title'],
        'profile_img'      => $meta['profile_img'],
        'faculty'          => $meta['faculty'],
        'programme'        => $meta['programme'],
        'intake'           => $meta['intake'],
        'lectures_held'    => $totals['held'],
        'present_days'     => $totals['present'],
        'watched_days'     => $totals['watched'],
        'half_verify_days' => $totals['half_verify'],
        'absent_days'      => $totals['absent'],
        'attendance_pct'   => $attendancePct,
        'absence_streak'   => $streak,
    ];

    $students[] = $studentRow;

    if ($streak >= AT_RISK_STREAK_THRESHOLD) {
        $atRisk[] = array_merge($studentRow, [
            'last_attended_date' => $lastAttendedDate, // null = never attended in range
        ]);
    }
}

usort($students, function ($a, $b) {
    return $a['attendance_pct'] <=> $b['attendance_pct'];
});

usort($atRisk, function ($a, $b) {
    return $b['absence_streak'] <=> $a['absence_streak'];
});

// ----------------------------------------------------------------------
// 8. Average attendance across the range - mean of the daily trend %s.
// ----------------------------------------------------------------------
$averageAttendance = 0;
if (!empty($trend)) {
    $sum = array_sum(array_column($trend, 'attendance_pct'));
    $averageAttendance = round($sum / count($trend), 1);
}

echo json_encode([
    'total_students'     => $totalStudents,
    'lectures_held'      => $totalHeldLectures,
    'average_attendance' => $averageAttendance,
    'status_breakdown'   => $statusBreakdown,
    'trend'              => $trend,
    'students'           => $students,
    'at_risk'            => $atRisk,
]);
