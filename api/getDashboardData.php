<?php

/**
 * Admin dashboard data API.
 * Put this file in the SAME folder as getLectureScheduleData.php (it uses the same ../includes paths).
 *
 * GET params: start_date (Y-m-d), end_date (Y-m-d), programme (id), search (text)
 */
header('Content-Type: application/json');

$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

/* ------------------------------------------------------------------ helpers */
function dash_valid_date($v, $fallback)
{
    if (!is_string($v) || $v === '') return $fallback;
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : $fallback;
}

function dash_safe(callable $fn, $default)
{
    try {
        $r = $fn();
        return $r === null ? $default : $r;
    } catch (Throwable $e) {
        error_log('[getDashboardData] ' . $e->getMessage());
        return $default;
    }
}

function dash_row($sql)
{
    $rs = Database::search($sql);
    return $rs ? ($rs->fetch_assoc() ?: []) : [];
}

function dash_rows($sql)
{
    $rs = Database::search($sql);
    $out = [];
    if ($rs) {
        while ($r = $rs->fetch_assoc()) $out[] = $r;
    }
    return $out;
}

function dash_month_keys($endDate, $n = 6)
{
    $d = new DateTime(substr($endDate, 0, 7) . '-01');
    $d->modify('-' . ($n - 1) . ' month');
    $keys = [];
    for ($i = 0; $i < $n; $i++) {
        $keys[] = $d->format('Y-m');
        $d->modify('+1 month');
    }
    return $keys;
}

/** $sql must return month_key + total. Use {FIRST} and {AFTER} placeholders for the window. */
function dash_series($sql, $keys)
{
    $first = $keys[0] . '-01';
    $after = (new DateTime(end($keys) . '-01'))->modify('+1 month')->format('Y-m-d');
    $sql = str_replace(['{FIRST}', '{AFTER}'], [$first, $after], $sql);
    $map = array_fill_keys($keys, 0);
    foreach (dash_rows($sql) as $r) {
        if (isset($map[$r['month_key']])) $map[$r['month_key']] = (float)$r['total'];
    }
    return array_values($map);
}

function dash_pct_change($cur, $prev)
{
    $cur = (float)$cur;
    $prev = (float)$prev;
    if ($prev == 0) return $cur == 0 ? 0 : null; // null = "New"
    return round((($cur - $prev) / $prev) * 100, 1);
}

/* ------------------------------------------------------------------ auth */
if (!SessionUser::hasValidSession()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication Failed!']);
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
if (SessionUser::ifUserIsLecturer($sessionUserRole) && !SessionUser::ifUserIsPro($sessionUserRole)) {
    http_response_code(403);
    echo json_encode(['error' => 'Not allowed']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

/* ------------------------------------------------------------------ inputs */
$tz   = new DateTimeZone("Asia/Colombo");
$now  = new DateTime("now", $tz);
$today = $now->format('Y-m-d');
$nowTime = $now->format('H:i:s');

$from = dash_valid_date($_GET['start_date'] ?? null, $now->format('Y-m-01'));
$to   = dash_valid_date($_GET['end_date'] ?? null, $today);
if ($from > $to) {
    $t = $from;
    $from = $to;
    $to = $t;
}

$programmeId = isset($_GET['programme']) ? (int)$_GET['programme'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchEsc = addslashes(mb_substr($search, 0, 80));

$days = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
$prevTo   = date('Y-m-d', strtotime($from . ' -1 day'));
$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($days - 1) . ' day'));

$keys = dash_month_keys($to, 6);

/* ------------------------------------------------------------------ student cards */
$g = dash_safe(function () {
    $base = "FROM `students`
             INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
             INNER JOIN `students_has_intakes` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
             WHERE `login_details`.`status_status_id` = 1";
    return dash_row("SELECT
        (SELECT COUNT(DISTINCT `students`.`student_id`) $base AND `students_has_intakes`.`status_status_id` != 3) AS total,
        (SELECT COUNT(DISTINCT `students`.`student_id`) $base AND `students_has_intakes`.`status_status_id` = 1) AS active,
        (SELECT COUNT(DISTINCT `students`.`student_id`) $base AND `students_has_intakes`.`status_status_id` = 2) AS inactive,
        (SELECT COUNT(DISTINCT `students`.`student_id`) $base AND `students_has_intakes`.`status_status_id` = 3) AS discontinued");
}, []);

$total = (int)($g['total'] ?? 0);
$active = (int)($g['active'] ?? 0);
$inactive = (int)($g['inactive'] ?? 0);
$disc = (int)($g['discontinued'] ?? 0);
$share = function ($n) use ($total) {
    return $total > 0 ? round($n / $total * 100, 1) : 0;
};

$statusSeries = function ($cond) use ($keys) {
    return dash_safe(function () use ($cond, $keys) {
        return dash_series("SELECT DATE_FORMAT(shi.`addmission_date`, '%Y-%m') AS month_key, COUNT(DISTINCT shi.`students_student_id`) AS total
            FROM `students_has_intakes` shi
            INNER JOIN `students` s ON s.`student_id` = shi.`students_student_id`
            INNER JOIN `login_details` ld ON s.`login_details_login_details_id` = ld.`login_details_id`
            WHERE ld.`status_status_id` = 1 AND shi.`status_status_id` $cond
              AND shi.`addmission_date` >= '{FIRST}' AND shi.`addmission_date` < '{AFTER}'
            GROUP BY month_key", $keys);
    }, array_fill(0, 6, 0));
};

$studentCards = [
    [
        'key' => 'total',
        'title' => 'Total Students',
        'value' => $total,
        'format' => 'int',
        'color' => '#2B5FD9',
        'icon' => 'mdi:school',
        'note' => 'Total Active Continuing Students',
        'spark' => $statusSeries('!= 3'),
        'href' => 'students'
    ],
    [
        'key' => 'active',
        'title' => 'Active Students',
        'value' => $active,
        'format' => 'int',
        'color' => '#2FA35A',
        'icon' => 'mdi:account-group',
        'share' => $share($active),
        'share_tone' => 'g',
        'note' => 'of all students',
        'spark' => $statusSeries('= 1'),
        'href' => 'students'
    ],
    [
        'key' => 'inactive',
        'title' => 'Inactive Students',
        'value' => $inactive,
        'format' => 'int',
        'color' => '#F08A3C',
        'icon' => 'mdi:account-clock',
        'share' => $share($inactive),
        'share_tone' => 'o',
        'note' => 'of all students',
        'spark' => $statusSeries('= 2'),
        'href' => 'students'
    ],
    [
        'key' => 'disc',
        'title' => 'Discontinue Students',
        'value' => $disc,
        'format' => 'int',
        'color' => '#E5484D',
        'icon' => 'mdi:account-cancel',
        'share' => $share($disc),
        'share_tone' => 'r',
        'note' => 'of all students',
        'spark' => $statusSeries('= 3'),
        'href' => 'students'
    ],
];

/* ------------------------------------------------------------------ finance cards */
$adm = dash_safe(function () use ($from, $to, $prevFrom, $prevTo) {
    return dash_row("SELECT
        SUM(DATE(`date_added`) BETWEEN '$from' AND '$to') AS cur,
        SUM(DATE(`date_added`) BETWEEN '$prevFrom' AND '$prevTo') AS prev
        FROM `students`");
}, []);
$admSeries = dash_safe(function () use ($keys) {
    return dash_series("SELECT DATE_FORMAT(`date_added`, '%Y-%m') AS month_key, COUNT(*) AS total FROM `students`
        WHERE `date_added` >= '{FIRST}' AND `date_added` < '{AFTER}' GROUP BY month_key", $keys);
}, array_fill(0, 6, 0));

// Per course-payment gross / paid (same formula as AdminDashboardSummary)
$PAY_CTE = "WITH pay AS (
    SELECT cp.`course_payments_id`, cp.`due_date`,
        (cp.`amount` + COALESCE(MAX(pp.`total_penalty`), 0)
         - COALESCE(CASE WHEN cp.`discount_type_discount_type_id` = 2 THEN cp.`discount`
                         WHEN cp.`discount_type_discount_type_id` = 3 THEN (cp.`amount` * cp.`discount` / 100)
                         ELSE 0 END, 0)) AS gross,
        COALESCE(SUM(CASE WHEN cpp.`payment_status_payment_status_id` = 3
                          THEN COALESCE(cpp.`paid_amount`, 0) + COALESCE(cpp.`panelty_amount`, 0) - COALESCE(cpp.`discount_amount`, 0)
                          ELSE 0 END), 0) AS total_paid
    FROM `course_payments` cp
    LEFT JOIN `course_payment_has_payments` cpp ON cp.`course_payments_id` = cpp.`course_payments_course_payments_id`
    LEFT JOIN (SELECT `course_payments_course_payments_id`, SUM(`amount`) AS total_penalty
               FROM `panelty_payments` GROUP BY `course_payments_course_payments_id`) pp
           ON pp.`course_payments_course_payments_id` = cp.`course_payments_id`
    GROUP BY cp.`course_payments_id`
)";

$fin = dash_safe(function () use ($PAY_CTE, $from, $to, $prevFrom, $prevTo) {
    return dash_row("$PAY_CTE
        SELECT
          COALESCE(SUM(CASE WHEN `due_date` BETWEEN '$from' AND '$to' THEN gross ELSE 0 END), 0) AS exp_cur,
          COALESCE(SUM(CASE WHEN `due_date` BETWEEN '$prevFrom' AND '$prevTo' THEN gross ELSE 0 END), 0) AS exp_prev,
          COALESCE(SUM(CASE WHEN `due_date` BETWEEN '$from' AND '$to' THEN GREATEST(gross - total_paid, 0) ELSE 0 END), 0) AS bal_cur,
          COALESCE(SUM(CASE WHEN `due_date` BETWEEN '$prevFrom' AND '$prevTo' THEN GREATEST(gross - total_paid, 0) ELSE 0 END), 0) AS bal_prev,
          COALESCE(SUM(CASE WHEN `due_date` < CURDATE() AND gross - total_paid > 0.009 THEN 1 ELSE 0 END), 0) AS overdue_count
        FROM pay");
}, []);

$rec = dash_safe(function () use ($from, $to, $prevFrom, $prevTo) {
    return dash_row("SELECT
        COALESCE(SUM(CASE WHEN DATE(`date_paid`) BETWEEN '$from' AND '$to' THEN `paid_amount` ELSE 0 END), 0) AS cur,
        COALESCE(SUM(CASE WHEN DATE(`date_paid`) BETWEEN '$prevFrom' AND '$prevTo' THEN `paid_amount` ELSE 0 END), 0) AS prev
        FROM `course_payment_has_payments`
        WHERE `payment_status_payment_status_id` = 3 AND `date_paid` IS NOT NULL");
}, []);

$incomeSeries = dash_safe(function () use ($keys) {
    return dash_series("SELECT DATE_FORMAT(`date_paid`, '%Y-%m') AS month_key, SUM(`paid_amount`) AS total
        FROM `course_payment_has_payments`
        WHERE `payment_status_payment_status_id` = 3 AND `date_paid` IS NOT NULL
          AND `date_paid` >= '{FIRST}' AND `date_paid` < '{AFTER}' GROUP BY month_key", $keys);
}, array_fill(0, 6, 0));

$dueSeries = function ($balance) use ($PAY_CTE, $keys) {
    return dash_safe(function () use ($PAY_CTE, $keys, $balance) {
        $expr = $balance ? 'SUM(GREATEST(gross - total_paid, 0))' : 'SUM(gross)';
        return dash_series("$PAY_CTE SELECT DATE_FORMAT(`due_date`, '%Y-%m') AS month_key, $expr AS total
            FROM pay WHERE `due_date` >= '{FIRST}' AND `due_date` < '{AFTER}' GROUP BY month_key", $keys);
    }, array_fill(0, 6, 0));
};

$financeCards = [
    [
        'key' => 'adm',
        'title' => 'Monthly New Admissions',
        'value' => (int)($adm['cur'] ?? 0),
        'format' => 'int',
        'color' => '#7B5CE0',
        'icon' => 'mdi:account-plus',
        'purple' => true,
        'change' => dash_pct_change($adm['cur'] ?? 0, $adm['prev'] ?? 0),
        'good_up' => true,
        'spark' => $admSeries,
        'href' => 'students'
    ],
    [
        'key' => 'exp',
        'title' => 'Expected Monthly Income',
        'value' => (float)($fin['exp_cur'] ?? 0),
        'format' => 'money',
        'color' => '#2B5FD9',
        'icon' => 'mdi:trending-up',
        'change' => dash_pct_change($fin['exp_cur'] ?? 0, $fin['exp_prev'] ?? 0),
        'good_up' => true,
        'spark' => $dueSeries(false),
        'href' => 'finance'
    ],
    [
        'key' => 'rec',
        'title' => 'Received Payment',
        'value' => (float)($rec['cur'] ?? 0),
        'format' => 'money',
        'color' => '#2FA35A',
        'icon' => 'mdi:wallet',
        'change' => dash_pct_change($rec['cur'] ?? 0, $rec['prev'] ?? 0),
        'good_up' => true,
        'spark' => $incomeSeries,
        'href' => 'finance'
    ],
    [
        'key' => 'bal',
        'title' => 'Balance Payment',
        'value' => (float)($fin['bal_cur'] ?? 0),
        'format' => 'money',
        'color' => '#E5484D',
        'icon' => 'mdi:database',
        'change' => dash_pct_change($fin['bal_cur'] ?? 0, $fin['bal_prev'] ?? 0),
        'good_up' => false,
        'spark' => $dueSeries(true),
        'href' => 'finance'
    ],
];

/* ------------------------------------------------------------------ system cards */
$sys = dash_safe(function () {
    return dash_row("SELECT
        (SELECT COUNT(*) FROM `users` WHERE `status_status_id` = 1 AND `user_roles_user_role_id` != 20) AS users_count,
        (SELECT COUNT(*) FROM `users` WHERE `status_status_id` = 1 AND `user_roles_user_role_id` = 20) AS lecturers_count,
        (SELECT COUNT(*) FROM `faculties`) AS faculties_count,
        (SELECT COUNT(*) FROM `programmes`) AS programme_count");
}, []);
$systemCards = [
    ['title' => 'Users', 'value' => (int)($sys['users_count'] ?? 0), 'note' => 'Active', 'icon' => 'mdi:account', 'href' => 'users'],
    ['title' => 'Faculties', 'value' => (int)($sys['faculties_count'] ?? 0), 'note' => 'Total', 'icon' => 'mdi:account-group', 'href' => 'faculties'],
    ['title' => 'Programmes', 'value' => (int)($sys['programme_count'] ?? 0), 'note' => 'Total', 'icon' => 'mdi:book-open-variant', 'href' => 'programmes'],
    ['title' => 'Lecturers', 'value' => (int)($sys['lecturers_count'] ?? 0), 'note' => 'Active', 'icon' => 'mdi:presentation', 'href' => 'lecturers'],
];

/* ------------------------------------------------------------------ action centre (only items backed by real data) */
$inq = dash_safe(function () {
    $r = dash_row("SELECT COUNT(*) AS total FROM `leads` l
        LEFT JOIN `lead_status` ls ON l.`lead_status_lead_status_id` = ls.`lead_status_id`
        WHERE LOWER(ls.`lead_status`) IN ('hot', 'warm', 'new') OR l.`date_added` >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)");
    return (int)($r['total'] ?? 0);
}, 0);
$pending = dash_safe(function () {
    $r = dash_row("SELECT COUNT(*) AS total FROM `course_payment_has_payments` WHERE `payment_status_payment_status_id` = 2");
    return (int)($r['total'] ?? 0);
}, 0);
$rejected = dash_safe(function () {
    $r = dash_row("SELECT COUNT(*) AS total FROM `course_payment_has_payments` WHERE `payment_status_payment_status_id` = 4");
    return (int)($r['total'] ?? 0);
}, 0);

$actions = [
    ['label' => 'New inquiries to call back', 'count' => $inq, 'tone' => 'warn', 'icon' => 'mdi:phone-in-talk-outline', 'href' => 'inquiries'],
    ['label' => 'Payments awaiting approval', 'count' => $pending, 'tone' => 'info', 'icon' => 'mdi:file-document-check-outline', 'href' => 'finance'],
    ['label' => 'Overdue fee reminders', 'count' => (int)($fin['overdue_count'] ?? 0), 'tone' => 'bad', 'icon' => 'mdi:alert-circle-outline', 'href' => 'finance'],
    ['label' => 'Rejected payments', 'count' => $rejected, 'tone' => 'bad', 'icon' => 'mdi:close-circle-outline', 'href' => 'finance'],
];

/* ------------------------------------------------------------------ lectures */
$lectureSelect = "SELECT
        `tt`.`time_table_id`, `tt`.`date`, `tt`.`start_time`, `tt`.`end_time`, `tt`.`lecture_title`, `tt`.`lecture`,
        `tts`.`time_table_status_id`, `tts`.`time_table_status`,
        `lt`.`lecture_type_id`, `lt`.`lecture_type`,
        `i`.`intake`, `subj`.`subject`, `p`.`programme_id`, `p`.`programme`,
        `u`.`user_fname`, `u`.`user_lname`, `u`.`profile_img`, `t`.`title`
    FROM `time_table` AS `tt`
    INNER JOIN `subjects` AS `subj` ON `tt`.`subjects_subject_id` = `subj`.`subject_id`
    INNER JOIN `levels` AS `lv` ON `subj`.`levels_level_id` = `lv`.`level_id`
    INNER JOIN `intakes` AS `i` ON `lv`.`intakes_intake_id` = `i`.`intake_id`
    INNER JOIN `programmes` AS `p` ON `i`.`programmes_programme_id` = `p`.`programme_id`
    INNER JOIN `time_table_status` AS `tts` ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`
    INNER JOIN `lecture_type` AS `lt` ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`
    INNER JOIN `lecturers` AS `lec` ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
    INNER JOIN `users` AS `u` ON `lec`.`users_user_id` = `u`.`user_id`
    INNER JOIN `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
    WHERE `lt`.`lecture_type_id` != 3";

$mapLecture = function ($row) use ($today, $nowTime) {
    // Same status rules as getLectureScheduleData.php
    $st = $row['time_table_status'];
    if ((int)$row['time_table_status_id'] === 1) {
        if ($row['date'] === $today && $row['start_time'] < $nowTime && $row['end_time'] > $nowTime) $st = 'Ongoing';
        elseif (($row['date'] === $today && $row['start_time'] > $nowTime) || $row['date'] > $today) $st = 'Upcoming';
        else $st = 'Pending Confirmation';
    }
    $profile = '';
    if (!empty($row['profile_img']) && file_exists('../assets/imgs/users/' . $row['profile_img'])) {
        $profile = './assets/imgs/users/' . $row['profile_img'];
    }
    return [
        'id'        => (int)$row['time_table_id'],
        'lecturer'  => $row['title'] . '. ' . $row['user_fname'] . ' ' . $row['user_lname'],
        'initials'  => strtoupper(substr($row['user_fname'], 0, 1) . substr($row['user_lname'], 0, 1)),
        'profile'   => $profile,
        'type'      => (int)$row['lecture_type_id'] === 1 ? 'Physical' : 'Online',
        'intake'    => $row['intake'],
        'programme' => $row['programme'],
        'subject'   => !empty($row['lecture_title']) ? $row['lecture_title'] : $row['subject'],
        'date'      => $row['date'],
        'start'     => substr($row['start_time'], 0, 5),
        'end'       => substr($row['end_time'], 0, 5),
        'status'    => $st,
        'link'      => $row['lecture'] ?? '',
    ];
};

$lectures = dash_safe(function () use ($lectureSelect, $from, $to, $programmeId, $searchEsc, $mapLecture) {
    $sql = $lectureSelect . " AND `tt`.`date` BETWEEN '$from' AND '$to'";
    if ($programmeId > 0) $sql .= " AND `p`.`programme_id` = $programmeId";
    if ($searchEsc !== '') {
        $sql .= " AND (`i`.`intake` LIKE '%$searchEsc%' OR `subj`.`subject` LIKE '%$searchEsc%'
                   OR `tt`.`lecture_title` LIKE '%$searchEsc%'
                   OR CONCAT(`u`.`user_fname`, ' ', `u`.`user_lname`) LIKE '%$searchEsc%')";
    }
    $sql .= " ORDER BY `tt`.`date` DESC, `tt`.`start_time` ASC LIMIT 300";
    return array_map($mapLecture, dash_rows($sql));
}, []);

$live = dash_safe(function () use ($lectureSelect, $today, $mapLecture) {
    $sql = $lectureSelect . " AND `tt`.`date` = '$today' AND `lt`.`lecture_type_id` = 2 ORDER BY `tt`.`start_time` ASC LIMIT 20";
    $list = array_map($mapLecture, dash_rows($sql));
    $order = ['Ongoing' => 0, 'Upcoming' => 1];
    usort($list, function ($a, $b) use ($order) {
        $x = $order[$a['status']] ?? 2;
        $y = $order[$b['status']] ?? 2;
        return $x === $y ? strcmp($a['start'], $b['start']) : $x - $y;
    });
    return $list;
}, []);

/* ------------------------------------------------------------------ batches (derived from timetable) */
$batches = dash_safe(function () use ($today) {
    $rows = dash_rows("SELECT `i`.`intake_id`, `i`.`intake`, `p`.`programme`,
            MIN(`tt`.`date`) AS first_d, MAX(`tt`.`date`) AS last_d,
            COUNT(DISTINCT `tt`.`time_table_id`) AS lec_total,
            COUNT(DISTINCT CASE WHEN `tt`.`date` < '$today' THEN `tt`.`time_table_id` END) AS lec_done
        FROM `time_table` AS `tt`
        INNER JOIN `subjects` AS `subj` ON `tt`.`subjects_subject_id` = `subj`.`subject_id`
        INNER JOIN `levels` AS `lv` ON `subj`.`levels_level_id` = `lv`.`level_id`
        INNER JOIN `intakes` AS `i` ON `lv`.`intakes_intake_id` = `i`.`intake_id`
        INNER JOIN `programmes` AS `p` ON `i`.`programmes_programme_id` = `p`.`programme_id`
        WHERE `tt`.`lecture_type_lecture_type_id` != 3
        GROUP BY `i`.`intake_id`, `i`.`intake`, `p`.`programme`
        HAVING MAX(`tt`.`date`) >= DATE_SUB('$today', INTERVAL 30 DAY)
        ORDER BY first_d DESC LIMIT 6");

    // student counts per intake (optional – skipped silently if the column differs)
    $counts = dash_safe(function () {
        $map = [];
        foreach (
            dash_rows("SELECT `intakes_intake_id` AS iid,
                COUNT(DISTINCT CASE WHEN `status_status_id` = 1 THEN `students_student_id` END) AS active_c,
                COUNT(DISTINCT `students_student_id`) AS total_c
            FROM `students_has_intakes` GROUP BY `intakes_intake_id`") as $r
        ) {
            $map[$r['iid']] = $r;
        }
        return $map;
    }, []);

    $out = [];
    foreach ($rows as $r) {
        $total = max(1, (int)$r['lec_total']);
        if ($r['last_d'] < $today) $state = 'Completed';
        elseif ($r['first_d'] > $today) $state = 'New intake';
        elseif ($r['last_d'] <= date('Y-m-d', strtotime($today . ' +45 day'))) $state = 'Ending soon';
        else $state = 'Running';
        $c = $counts[$r['intake_id']] ?? null;
        $out[] = [
            'intake' => $r['intake'],
            'programme' => $r['programme'],
            'start' => $r['first_d'],
            'end' => $r['last_d'],
            'progress' => (int)round((int)$r['lec_done'] / $total * 100),
            'state' => $state,
            'active' => $c ? (int)$c['active_c'] : null,
            'students' => $c ? (int)$c['total_c'] : null,
        ];
    }
    return $out;
}, []);

/* ------------------------------------------------------------------ academic calendar */
$events = [];
$deadlines = [];
dash_safe(function () use (&$events, &$deadlines, $today) {
    $chk = Database::search("SHOW TABLES LIKE 'academic_events'");
    if (!$chk || $chk->num_rows === 0) return;
    $fetch = function ($types) use ($today) {
        $list = [];
        foreach (
            dash_rows("SELECT `title`, `event_type`, `start_date`, `start_time`, `end_time`, `is_highlighted`
            FROM `academic_events`
            WHERE `status` = 1 AND `event_type` IN ($types) AND `start_date` >= '$today'
            ORDER BY `start_date` ASC, `start_time` ASC LIMIT 5") as $r
        ) {
            $time = 'All Day';
            if (!empty($r['start_time'])) {
                $time = date('h:i A', strtotime($r['start_time'])) . (!empty($r['end_time']) ? ' – ' . date('h:i A', strtotime($r['end_time'])) : '');
            }
            $list[] = ['date' => $r['start_date'], 'title' => $r['title'], 'time' => $time, 'highlight' => (int)$r['is_highlighted'] === 1];
        }
        return $list;
    };
    $events = $fetch("'event', 'workshop', 'site_visit'");
    $deadlines = $fetch("'deadline', 'orientation'");
}, null);

/* ------------------------------------------------------------------ programmes for filter */
$programmes = dash_safe(function () {
    return array_map(
        function ($r) {
            return ['id' => (int)$r['programme_id'], 'name' => $r['programme']];
        },
        dash_rows("SELECT `programme_id`, `programme` FROM `programmes` ORDER BY `programme` ASC")
    );
}, []);

/* ------------------------------------------------------------------ output */
echo json_encode([
    'done'          => true,
    'generated_at'  => $now->format('H:i'),
    'today'         => $today,
    'range'         => ['from' => $from, 'to' => $to, 'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'days' => $days],
    'student_cards' => $studentCards,
    'finance_cards' => $financeCards,
    'system_cards'  => $systemCards,
    'actions'       => $actions,
    'live'          => $live,
    'batches'       => $batches,
    'events'        => $events,
    'deadlines'     => $deadlines,
    'lectures'      => $lectures,
    'programmes'    => $programmes,
], JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
