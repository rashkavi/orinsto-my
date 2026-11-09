<?php

/**
 * Super Admin dashboard data.
 *
 * Every figure on the Super Admin dashboard is produced here so the view only
 * renders. Filters: start_date, end_date, programme_id and search.
 * Period figures use the selected date range and are compared against the
 * previous period of the same length. "Right now" figures (live classes,
 * batches, action centre) describe the current state of the campus.
 */
class AdminDashboardSummary
{
    /** Attendance statuses that count as attended: Half Verify, Present, Watched. */
    const ATTENDED_STATUSES = '1,2,4';

    /** Students below this attendance rate are flagged in the action centre. */
    const ATTENDANCE_THRESHOLD = 75;

    private static function fetchRow($sql)
    {
        $result = Database::search($sql);
        return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [];
    }

    private static function fetchAll($sql)
    {
        $rows = [];
        $result = Database::search($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private static function escape($value)
    {
        return Database::escape((string)$value);
    }

    /** Net payable amount of a course payment after its discount. */
    private static function netAmountSql($alias = 'cp')
    {
        return "GREATEST({$alias}.amount - CASE
                    WHEN {$alias}.discount_type_discount_type_id = 2 THEN {$alias}.discount
                    WHEN {$alias}.discount_type_discount_type_id = 3 THEN {$alias}.amount * {$alias}.discount / 100
                    ELSE 0 END, 0)";
    }

    /** Money actually received for a payment row (approved only). */
    private static function paidAmountSql($alias = 'cph')
    {
        return "(COALESCE({$alias}.paid_amount, 0) + COALESCE({$alias}.panelty_amount, 0) - COALESCE({$alias}.discount_amount, 0))";
    }

    /** Percentage change, or null when there is nothing to compare against. */
    private static function percentChange($current, $previous)
    {
        $current = (float)$current;
        $previous = (float)$previous;
        if ($previous == 0) {
            return $current == 0 ? 0.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /** Keys (Y-m) and labels (M y) for the $count months ending with $endDate's month. */
    private static function monthWindow($endDate, $count = 6)
    {
        $end = new DateTime(substr($endDate, 0, 7) . '-01');
        $keys = [];
        $labels = [];
        for ($i = $count - 1; $i >= 0; $i--) {
            $m = (clone $end)->modify("-{$i} month");
            $keys[] = $m->format('Y-m');
            $labels[] = $m->format('M y');
        }
        return [$keys, $labels];
    }

    private static function seriesFromRows($keys, $rows, $valueKey = 'total')
    {
        $map = array_fill_keys($keys, 0);
        foreach ($rows as $row) {
            if (array_key_exists($row['month_key'], $map)) {
                $map[$row['month_key']] = round((float)$row[$valueKey], 2);
            }
        }
        return array_values($map);
    }

    public static function getComprehensiveDashboardData($filters = [])
    {
        $timezone = new DateTimeZone('Asia/Colombo');
        $now = new DateTime('now', $timezone);
        $todayDate = $now->format('Y-m-d');
        $nowTime = $now->format('H:i:s');

        $startDate = !empty($filters['start_date']) ? $filters['start_date'] : $now->format('Y-m-01');
        $endDate = !empty($filters['end_date']) ? $filters['end_date'] : $todayDate;
        $programmeFilter = !empty($filters['programme_id']) ? (int)$filters['programme_id'] : 0;
        $searchFilter = trim($filters['search'] ?? '');

        // Validate dates before they reach SQL.
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($datePattern, $startDate) || !preg_match($datePattern, $endDate)
            || !strtotime($startDate) || !strtotime($endDate)) {
            $startDate = $now->format('Y-m-01');
            $endDate = $todayDate;
        }
        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        // Previous period of the same length, used for "vs previous period".
        $periodDays = (int)(new DateTime($startDate))->diff(new DateTime($endDate))->days + 1;
        $prevEnd = (new DateTime($startDate))->modify('-1 day')->format('Y-m-d');
        $prevStart = (new DateTime($prevEnd))->modify('-' . ($periodDays - 1) . ' day')->format('Y-m-d');

        $rangeStart = "{$startDate} 00:00:00";
        $rangeEnd = "{$endDate} 23:59:59";
        $prevRangeStart = "{$prevStart} 00:00:00";
        $prevRangeEnd = "{$prevEnd} 23:59:59";

        $search = self::escape($searchFilter);
        $programmeSql = $programmeFilter > 0 ? " AND i.programmes_programme_id = {$programmeFilter} " : "";

        // Search on a student's name / contact, intake or programme.
        $studentSearchSql = $search === '' ? '' : " AND (
                CONCAT(COALESCE(ld.first_name,''),' ',COALESCE(ld.last_name,'')) LIKE '%{$search}%'
                OR COALESCE(ld.email,'') LIKE '%{$search}%'
                OR COALESCE(s.full_name,'') LIKE '%{$search}%'
                OR COALESCE(s.mobile,'') LIKE '%{$search}%'
                OR COALESCE(ld.whatsapp,'') LIKE '%{$search}%'
                OR COALESCE(shi.student_id,'') LIKE '%{$search}%'
                OR i.intake LIKE '%{$search}%'
                OR p.programme LIKE '%{$search}%'
            ) ";

        // Search on a lecture's intake, programme, subject, title or lecturer.
        $lectureSearchSql = $search === '' ? '' : " AND (
                i.intake LIKE '%{$search}%'
                OR p.programme LIKE '%{$search}%'
                OR subj.subject LIKE '%{$search}%'
                OR COALESCE(tt.lecture_title,'') LIKE '%{$search}%'
                OR CONCAT(u.user_fname,' ',u.user_lname) LIKE '%{$search}%'
            ) ";

        $studentJoins = "
            FROM students_has_intakes shi
            INNER JOIN students s ON shi.students_student_id = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
        ";

        /* ───────────── Students (admitted in the selected period) ───────────── */
        $studentStats = self::fetchRow("
            SELECT
                COUNT(DISTINCT shi.students_has_intakes_id) AS total,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 1 THEN shi.students_has_intakes_id END) AS active,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 2 THEN shi.students_has_intakes_id END) AS inactive,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 3 THEN shi.students_has_intakes_id END) AS discontinued
            {$studentJoins}
            WHERE shi.addmission_date BETWEEN '{$rangeStart}' AND '{$rangeEnd}'
              {$programmeSql} {$studentSearchSql}
        ");
        $prevAdmissions = self::fetchRow("
            SELECT COUNT(DISTINCT shi.students_has_intakes_id) AS total
            {$studentJoins}
            WHERE shi.addmission_date BETWEEN '{$prevRangeStart}' AND '{$prevRangeEnd}'
              {$programmeSql} {$studentSearchSql}
        ");

        $totalStudents = (int)($studentStats['total'] ?? 0);
        $activeStudents = (int)($studentStats['active'] ?? 0);
        $inactiveStudents = (int)($studentStats['inactive'] ?? 0);
        $discontinuedStudents = (int)($studentStats['discontinued'] ?? 0);
        $share = function ($n) use ($totalStudents) {
            return $totalStudents > 0 ? round(($n / $totalStudents) * 100, 1) : 0;
        };

        // Whole-campus enrolment right now (not tied to the date range).
        $campusStats = self::fetchRow("
            SELECT
                COUNT(DISTINCT shi.students_has_intakes_id) AS total,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 1 THEN shi.students_has_intakes_id END) AS active
            {$studentJoins}
            WHERE 1 = 1 {$programmeSql} {$studentSearchSql}
        ");

        /* ───────────── Finance ───────────── */
        $net = self::netAmountSql('cp');
        $paid = self::paidAmountSql('cph');
        $paymentJoins = "
            FROM course_payments cp
            INNER JOIN payment_plan_has_students_has_intakes pshi
                ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
            INNER JOIN students_has_intakes shi
                ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
            INNER JOIN students s ON shi.students_student_id = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
        ";

        $expectedSql = function ($from, $to) use ($net, $paymentJoins, $programmeSql, $studentSearchSql) {
            return "SELECT COALESCE(SUM({$net}), 0) AS total
                {$paymentJoins}
                WHERE cp.due_date BETWEEN '{$from}' AND '{$to}'
                  {$programmeSql} {$studentSearchSql}";
        };
        $receivedSql = function ($from, $to) use ($paid, $paymentJoins, $programmeSql, $studentSearchSql) {
            return "SELECT COALESCE(SUM({$paid}), 0) AS total
                {$paymentJoins}
                INNER JOIN course_payment_has_payments cph
                    ON cph.course_payments_course_payments_id = cp.course_payments_id
                WHERE cph.payment_status_payment_status_id = 3
                  AND cph.date_paid BETWEEN '{$from}' AND '{$to}'
                  {$programmeSql} {$studentSearchSql}";
        };

        $expectedIncome = (float)(self::fetchRow($expectedSql($startDate, $endDate))['total'] ?? 0);
        $receivedIncome = (float)(self::fetchRow($receivedSql($startDate, $endDate))['total'] ?? 0);
        $prevExpected = (float)(self::fetchRow($expectedSql($prevStart, $prevEnd))['total'] ?? 0);
        $prevReceived = (float)(self::fetchRow($receivedSql($prevStart, $prevEnd))['total'] ?? 0);

        // Collected against instalments that fell due in the period.
        $collectedOnDue = (float)(self::fetchRow("
            SELECT COALESCE(SUM({$paid}), 0) AS total
            {$paymentJoins}
            INNER JOIN course_payment_has_payments cph
                ON cph.course_payments_course_payments_id = cp.course_payments_id
            WHERE cph.payment_status_payment_status_id = 3
              AND cp.due_date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql} {$studentSearchSql}
        ")['total'] ?? 0);
        $balancePayment = max(0, $expectedIncome - $collectedOnDue);
        $collectionRate = $expectedIncome > 0 ? min(100, round(($collectedOnDue / $expectedIncome) * 100, 1)) : 0;

        // Total outstanding today across all instalments already due.
        $outstanding = self::fetchRow("
            SELECT COALESCE(SUM(GREATEST({$net} - COALESCE(pd.paid, 0), 0)), 0) AS total,
                   COUNT(DISTINCT CASE WHEN {$net} - COALESCE(pd.paid, 0) > 0 THEN shi.students_has_intakes_id END) AS students
            {$paymentJoins}
            LEFT JOIN (
                SELECT cph.course_payments_course_payments_id AS cp_id, SUM({$paid}) AS paid
                FROM course_payment_has_payments cph
                WHERE cph.payment_status_payment_status_id = 3
                GROUP BY cph.course_payments_course_payments_id
            ) pd ON pd.cp_id = cp.course_payments_id
            WHERE cp.due_date < '{$todayDate}'
              AND shi.status_status_id = 1
              {$programmeSql} {$studentSearchSql}
        ");

        /* ───────────── Attendance ───────────── */
        $attendanceJoins = "
            FROM attendance a
            INNER JOIN time_table tt ON a.time_table_time_table_id = tt.time_table_id
            INNER JOIN subjects subj ON tt.subjects_subject_id = subj.subject_id
            INNER JOIN levels lv ON subj.levels_level_id = lv.level_id
            INNER JOIN intakes i ON lv.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
        ";
        $attended = self::ATTENDED_STATUSES;
        $attendanceRateSql = function ($from, $to) use ($attendanceJoins, $attended, $programmeSql) {
            return "SELECT COUNT(*) AS marked,
                       SUM(CASE WHEN a.attendance_status_attendance_status_id IN ({$attended}) THEN 1 ELSE 0 END) AS attended
                {$attendanceJoins}
                WHERE tt.date BETWEEN '{$from}' AND '{$to}' {$programmeSql}";
        };
        $att = self::fetchRow($attendanceRateSql($startDate, $endDate));
        $prevAtt = self::fetchRow($attendanceRateSql($prevStart, $prevEnd));
        $attendanceRate = (int)($att['marked'] ?? 0) > 0 ? round(($att['attended'] / $att['marked']) * 100, 1) : null;
        $prevAttendanceRate = (int)($prevAtt['marked'] ?? 0) > 0 ? round(($prevAtt['attended'] / $prevAtt['marked']) * 100, 1) : null;

        $threshold = self::ATTENDANCE_THRESHOLD;
        $lowAttendance = self::fetchRow("
            SELECT COUNT(*) AS total FROM (
                SELECT a.students_student_id
                {$attendanceJoins}
                INNER JOIN students_has_intakes shi
                    ON shi.students_student_id = a.students_student_id
                    AND shi.intakes_intake_id = i.intake_id
                    AND shi.status_status_id = 1
                WHERE tt.date <= '{$todayDate}' {$programmeSql}
                GROUP BY a.students_student_id, i.intake_id
                HAVING SUM(CASE WHEN a.attendance_status_attendance_status_id IN ({$attended}) THEN 1 ELSE 0 END) * 100 < COUNT(*) * {$threshold}
            ) x
        ");

        /* ───────────── Lectures in the period ───────────── */
        $lectureJoins = "
            FROM time_table tt
            INNER JOIN subjects subj ON tt.subjects_subject_id = subj.subject_id
            INNER JOIN levels lv ON subj.levels_level_id = lv.level_id
            INNER JOIN intakes i ON lv.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            INNER JOIN lecture_type lt ON tt.lecture_type_lecture_type_id = lt.lecture_type_id
            INNER JOIN lecturers lec ON tt.lecturers_lecturers_id = lec.lecturers_id
            INNER JOIN users u ON lec.users_user_id = u.user_id
            LEFT JOIN title t ON u.title_title_id = t.title_id
        ";
        $lectureStats = self::fetchRow("
            SELECT COUNT(*) AS total,
                   SUM(CASE WHEN tt.lecture_type_lecture_type_id = 1 THEN 1 ELSE 0 END) AS physical,
                   SUM(CASE WHEN tt.lecture_type_lecture_type_id = 2 THEN 1 ELSE 0 END) AS online,
                   SUM(CASE WHEN tt.time_table_status_time_table_status_id IN (2, 3) THEN 1 ELSE 0 END) AS cancelled
            {$lectureJoins}
            WHERE tt.lecture_type_lecture_type_id != 3
              AND tt.date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql} {$lectureSearchSql}
        ");

        /* ───────────── System (whole campus, not filtered) ───────────── */
        $systemStats = self::fetchRow("
            SELECT
                (SELECT COUNT(*) FROM users WHERE status_status_id = 1 AND user_roles_user_role_id != 20) AS users_count,
                (SELECT COUNT(*) FROM users WHERE status_status_id = 1 AND user_roles_user_role_id = 20) AS lecturers_count,
                (SELECT COUNT(*) FROM faculties) AS faculties_count,
                (SELECT COUNT(*) FROM programmes) AS programme_count,
                (SELECT COUNT(*) FROM intakes) AS intake_count
        ");

        /* ───────────── Monthly trend (6 months ending with the selected end month) ───────────── */
        [$monthKeys, $monthLabels] = self::monthWindow($endDate, 6);
        $trendFrom = $monthKeys[0] . '-01';
        $trendTo = (new DateTime(end($monthKeys) . '-01'))->modify('last day of this month')->format('Y-m-d');

        $admissionRows = self::fetchAll("
            SELECT DATE_FORMAT(shi.addmission_date, '%Y-%m') AS month_key,
                   COUNT(DISTINCT shi.students_has_intakes_id) AS total
            {$studentJoins}
            WHERE shi.addmission_date BETWEEN '{$trendFrom} 00:00:00' AND '{$trendTo} 23:59:59'
              {$programmeSql} {$studentSearchSql}
            GROUP BY month_key
        ");
        $expectedRows = self::fetchAll("
            SELECT DATE_FORMAT(cp.due_date, '%Y-%m') AS month_key, SUM({$net}) AS total
            {$paymentJoins}
            WHERE cp.due_date BETWEEN '{$trendFrom}' AND '{$trendTo}'
              {$programmeSql} {$studentSearchSql}
            GROUP BY month_key
        ");
        $receivedRows = self::fetchAll("
            SELECT DATE_FORMAT(cph.date_paid, '%Y-%m') AS month_key, SUM({$paid}) AS total
            {$paymentJoins}
            INNER JOIN course_payment_has_payments cph
                ON cph.course_payments_course_payments_id = cp.course_payments_id
            WHERE cph.payment_status_payment_status_id = 3
              AND cph.date_paid BETWEEN '{$trendFrom}' AND '{$trendTo}'
              {$programmeSql} {$studentSearchSql}
            GROUP BY month_key
        ");

        $trend = [
            'labels' => $monthLabels,
            'admissions' => array_map('intval', self::seriesFromRows($monthKeys, $admissionRows)),
            'expected' => self::seriesFromRows($monthKeys, $expectedRows),
            'received' => self::seriesFromRows($monthKeys, $receivedRows),
        ];

        /* ───────────── KPI cards ───────────── */
        $admissionsChange = self::percentChange($totalStudents, $prevAdmissions['total'] ?? 0);

        $studentCards = [
            [
                'title' => 'Total Students',
                'value' => $totalStudents,
                'icon' => 'mdi:school-outline',
                'note' => 'admitted in period',
                'href' => 'students',
            ],
            [
                'title' => 'Active Students',
                'value' => $activeStudents,
                'icon' => 'mdi:account-check-outline',
                'share' => $share($activeStudents),
                'tone' => 'good',
                'href' => 'students',
            ],
            [
                'title' => 'Inactive Students',
                'value' => $inactiveStudents,
                'icon' => 'mdi:account-clock-outline',
                'share' => $share($inactiveStudents),
                'tone' => 'warn',
                'href' => 'students',
            ],
            [
                'title' => 'Discontinue Students',
                'value' => $discontinuedStudents,
                'icon' => 'mdi:account-cancel-outline',
                'share' => $share($discontinuedStudents),
                'tone' => 'bad',
                'href' => 'students',
            ],
        ];

        $financeCards = [
            [
                'title' => 'New Admissions',
                'value' => $totalStudents,
                'icon' => 'mdi:account-plus-outline',
                'change' => $admissionsChange,
                'good_up' => true,
                'spark' => $trend['admissions'],
                'href' => 'students',
            ],
            [
                'title' => 'Expected Income',
                'value' => $expectedIncome,
                'format' => 'money',
                'icon' => 'mdi:cash-clock',
                'change' => self::percentChange($expectedIncome, $prevExpected),
                'good_up' => true,
                'spark' => $trend['expected'],
                'href' => 'students-payments',
            ],
            [
                'title' => 'Received Payment',
                'value' => $receivedIncome,
                'format' => 'money',
                'icon' => 'mdi:wallet-outline',
                'change' => self::percentChange($receivedIncome, $prevReceived),
                'good_up' => true,
                'spark' => $trend['received'],
                'href' => 'payments',
            ],
            [
                'title' => 'Balance Payment',
                'value' => $balancePayment,
                'format' => 'money',
                'icon' => 'mdi:cash-remove',
                'rate' => $collectionRate,
                'href' => 'awaiting-payments',
            ],
        ];

        $systemCards = [
            ['title' => 'Users', 'value' => (int)($systemStats['users_count'] ?? 0), 'note' => 'Active', 'icon' => 'mdi:account-group-outline', 'href' => 'users'],
            ['title' => 'Faculties', 'value' => (int)($systemStats['faculties_count'] ?? 0), 'note' => 'Total', 'icon' => 'mdi:domain', 'href' => 'faculties'],
            ['title' => 'Programmes', 'value' => (int)($systemStats['programme_count'] ?? 0), 'note' => 'Total', 'icon' => 'mdi:book-open-page-variant-outline', 'href' => 'programes'],
            ['title' => 'Lecturers', 'value' => (int)($systemStats['lecturers_count'] ?? 0), 'note' => 'Active', 'icon' => 'mdi:human-male-board', 'href' => 'lecturers'],
        ];

        $insights = [
            'campus_total' => (int)($campusStats['total'] ?? 0),
            'campus_active' => (int)($campusStats['active'] ?? 0),
            'collection_rate' => $collectionRate,
            'collected_on_due' => $collectedOnDue,
            'outstanding' => (float)($outstanding['total'] ?? 0),
            'outstanding_students' => (int)($outstanding['students'] ?? 0),
            'attendance_rate' => $attendanceRate,
            'attendance_change' => ($attendanceRate !== null && $prevAttendanceRate !== null)
                ? round($attendanceRate - $prevAttendanceRate, 1) : null,
            'attendance_marked' => (int)($att['marked'] ?? 0),
            'lectures_total' => (int)($lectureStats['total'] ?? 0),
            'lectures_physical' => (int)($lectureStats['physical'] ?? 0),
            'lectures_online' => (int)($lectureStats['online'] ?? 0),
            'lectures_cancelled' => (int)($lectureStats['cancelled'] ?? 0),
            'intakes' => (int)($systemStats['intake_count'] ?? 0),
        ];

        /* ───────────── Live classes: today only ───────────── */
        $liveClasses = [];
        $liveRows = self::fetchAll("
            SELECT tt.time_table_id, tt.date, tt.start_time, tt.end_time, tt.lecture, tt.lecture_title,
                   tt.lecture_type_lecture_type_id AS type_id, tt.time_table_status_time_table_status_id AS status_id,
                   subj.subject, i.intake, p.programme, u.user_fname, u.user_lname, t.title
            {$lectureJoins}
            WHERE tt.date = '{$todayDate}'
              AND tt.lecture_type_lecture_type_id IN (1, 2)
              AND tt.time_table_status_time_table_status_id IN (1, 5)
              {$programmeSql} {$lectureSearchSql}
            ORDER BY tt.start_time ASC
        ");
        foreach ($liveRows as $r) {
            $status = 'Upcoming';
            if ((int)$r['status_id'] === 5 || $nowTime > $r['end_time']) {
                $status = 'Ended';
            } elseif ($nowTime >= $r['start_time']) {
                $status = 'Live';
            }
            $liveClasses[] = [
                'id' => (int)$r['time_table_id'],
                'title' => !empty($r['lecture_title']) ? $r['lecture_title'] : $r['subject'],
                'subject' => $r['subject'],
                'intake' => $r['intake'],
                'programme' => $r['programme'],
                'lecturer' => trim(($r['title'] ? $r['title'] . '. ' : '') . $r['user_fname'] . ' ' . $r['user_lname']),
                'start' => $r['start_time'],
                'end' => $r['end_time'],
                'type' => (int)$r['type_id'] === 2 ? 'Online' : 'Physical',
                'status' => $status,
                'link' => ((int)$r['type_id'] === 2 && filter_var($r['lecture'], FILTER_VALIDATE_URL)) ? $r['lecture'] : '',
            ];
        }

        /* ───────────── Management action centre (current state) ───────────── */
        $inquiries = self::fetchRow("
            SELECT COUNT(*) AS total FROM students_has_help_desk
            WHERE help_desk_status_help_desk_status_id IN (1, 3)
        ");
        $approvals = self::fetchRow("
            SELECT COUNT(DISTINCT cph.course_payment_has_payment_id) AS total
            {$paymentJoins}
            INNER JOIN course_payment_has_payments cph
                ON cph.course_payments_course_payments_id = cp.course_payments_id
            WHERE cph.payment_status_payment_status_id = 2 {$programmeSql}
        ");
        $overdue = self::fetchRow("
            SELECT COUNT(DISTINCT cp.course_payments_id) AS total
            {$paymentJoins}
            WHERE cp.payment_status_payment_status_id IN (1, 2)
              AND cp.due_date < '{$todayDate}'
              AND shi.status_status_id = 1
              {$programmeSql}
        ");
        $certificates = self::fetchRow("
            SELECT COUNT(DISTINCT shi.students_has_intakes_id) AS total
            {$studentJoins}
            WHERE shi.status_status_id = 1
              AND i.end_date IS NOT NULL AND i.end_date < '{$todayDate}'
              {$programmeSql}
        ");

        $actions = [
            ['label' => 'New inquiries to call back', 'hint' => 'Open & escalated help-desk tickets', 'count' => (int)($inquiries['total'] ?? 0), 'tone' => 'warn', 'icon' => 'mdi:phone-in-talk-outline', 'href' => 'help-desk'],
            ['label' => 'Applications to review', 'hint' => 'Payment slips awaiting approval', 'count' => (int)($approvals['total'] ?? 0), 'tone' => 'info', 'icon' => 'mdi:file-document-edit-outline', 'href' => 'awaiting-payments'],
            ['label' => 'Overdue fee reminders', 'hint' => 'Instalments past their due date', 'count' => (int)($overdue['total'] ?? 0), 'tone' => 'bad', 'icon' => 'mdi:alert-circle-outline', 'href' => 'awaiting-payments'],
            ['label' => 'Students below ' . $threshold . '% attendance', 'hint' => 'Active students, all lectures to date', 'count' => (int)($lowAttendance['total'] ?? 0), 'tone' => 'warn', 'icon' => 'mdi:account-alert-outline', 'href' => 'students-attendance-breakdown'],
            ['label' => 'Certificates ready to issue', 'hint' => 'Active students of completed intakes', 'count' => (int)($certificates['total'] ?? 0), 'tone' => 'info', 'icon' => 'mdi:certificate-outline', 'href' => 'intakes'],
        ];

        /* ───────────── Batches (intakes) – current progress ───────────── */
        $batches = [];
        $batchRows = self::fetchAll("
            SELECT i.intake_id, i.intake, i.start_date, i.end_date, p.programme,
                   COUNT(DISTINCT shi.students_has_intakes_id) AS students,
                   COUNT(DISTINCT CASE WHEN shi.status_status_id = 1 THEN shi.students_has_intakes_id END) AS active
            FROM intakes i
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            LEFT JOIN students_has_intakes shi ON shi.intakes_intake_id = i.intake_id
            WHERE 1 = 1 {$programmeSql}
              " . ($search !== '' ? " AND (i.intake LIKE '%{$search}%' OR p.programme LIKE '%{$search}%') " : '') . "
            GROUP BY i.intake_id, i.intake, i.start_date, i.end_date, p.programme
            ORDER BY (i.end_date IS NOT NULL AND i.end_date < '{$todayDate}') ASC, i.start_date DESC, i.intake_id DESC
            LIMIT 6
        ");
        $today = new DateTime($todayDate);
        foreach ($batchRows as $b) {
            $progress = null;
            $state = 'Running';
            $daysLeft = null;
            if (!empty($b['start_date']) && !empty($b['end_date'])) {
                $start = new DateTime($b['start_date']);
                $end = new DateTime($b['end_date']);
                $span = max(1, (int)$start->diff($end)->days);
                if ($today < $start) {
                    $progress = 0;
                    $state = 'New intake';
                } elseif ($today > $end) {
                    $progress = 100;
                    $state = 'Completed';
                } else {
                    $progress = (int)round(($start->diff($today)->days / $span) * 100);
                    $daysLeft = (int)$today->diff($end)->days;
                    $state = $progress >= 85 ? 'Ending soon' : ($progress < 10 ? 'New intake' : 'Running');
                }
            }
            $batches[] = [
                'id' => (int)$b['intake_id'],
                'intake' => $b['intake'],
                'programme' => $b['programme'],
                'start' => $b['start_date'],
                'end' => $b['end_date'],
                'progress' => $progress,
                'days_left' => $daysLeft,
                'students' => (int)$b['students'],
                'active' => (int)$b['active'],
                'state' => $state,
            ];
        }

        /* ───────────── Students by programme (current) ───────────── */
        $programmeMix = self::fetchAll("
            SELECT p.programme_id AS id, p.programme AS name,
                   COUNT(DISTINCT shi.students_has_intakes_id) AS students,
                   COUNT(DISTINCT CASE WHEN shi.status_status_id = 1 THEN shi.students_has_intakes_id END) AS active
            FROM programmes p
            LEFT JOIN intakes i ON i.programmes_programme_id = p.programme_id
            LEFT JOIN students_has_intakes shi ON shi.intakes_intake_id = i.intake_id
            WHERE 1 = 1 " . ($programmeFilter > 0 ? " AND p.programme_id = {$programmeFilter} " : '') . "
            GROUP BY p.programme_id, p.programme
            ORDER BY students DESC, p.programme ASC
        ");

        /* ───────────── Events & deadlines ───────────── */
        $events = [];
        $deadlines = [];
        $checkTable = Database::search("SHOW TABLES LIKE 'academic_events'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Show what is coming up from the start of the range, so the widget is never stale.
            $eventsFrom = max($startDate, $todayDate) > $endDate ? $startDate : min($startDate, $todayDate);
            $eventProgSql = $programmeFilter > 0
                ? " AND (target_programme_id IS NULL OR target_programme_id = {$programmeFilter}) " : '';
            $eventRows = self::fetchAll("
                SELECT * FROM academic_events
                WHERE status = 1
                  AND COALESCE(end_date, start_date) >= '{$eventsFrom}'
                  {$eventProgSql}
                ORDER BY start_date ASC, start_time ASC
                LIMIT 30
            ");
            foreach ($eventRows as $r) {
                $item = [
                    'date' => $r['start_date'],
                    'title' => $r['title'],
                    'type' => $r['event_type'],
                    'location' => $r['location'] ?? '',
                    'time' => (!empty($r['start_time']) && !empty($r['end_time']))
                        ? date('h:i A', strtotime($r['start_time'])) . ' – ' . date('h:i A', strtotime($r['end_time']))
                        : 'All day',
                    'highlight' => (bool)$r['is_highlighted'],
                    'days_away' => (int)$today->diff(new DateTime($r['start_date']))->format('%r%a'),
                ];
                if (in_array($r['event_type'], ['deadline', 'exam'], true)) {
                    if (count($deadlines) < 6) $deadlines[] = $item;
                } elseif (count($events) < 6) {
                    $events[] = $item;
                }
            }
        }

        /* ───────────── Lecture schedule (selected range) ───────────── */
        $lectures = [];
        $lectureRows = self::fetchAll("
            SELECT tt.time_table_id, tt.date, tt.start_time, tt.end_time, tt.lecture_title,
                   tt.time_table_status_time_table_status_id AS status_id,
                   lt.lecture_type_id, i.intake, subj.subject, p.programme,
                   u.user_fname, u.user_lname, u.profile_img, t.title
            {$lectureJoins}
            WHERE lt.lecture_type_id != 3
              AND tt.date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql} {$lectureSearchSql}
            ORDER BY tt.date DESC, tt.start_time ASC
            LIMIT 25
        ");
        foreach ($lectureRows as $row) {
            $statusId = (int)$row['status_id'];
            if ($statusId === 2) {
                $status = 'Cancelled';
            } elseif ($statusId === 3) {
                $status = 'Postponed';
            } elseif ($statusId === 5 || $row['date'] < $todayDate
                || ($row['date'] === $todayDate && $nowTime > $row['end_time'])) {
                $status = 'Completed';
            } elseif ($row['date'] === $todayDate && $nowTime >= $row['start_time']) {
                $status = 'Ongoing';
            } else {
                $status = 'Upcoming';
            }

            $profilePath = './assets/imgs/users/' . ($row['profile_img'] ?? '');
            $lectures[] = [
                'id' => (int)$row['time_table_id'],
                'date' => $row['date'],
                'start' => $row['start_time'],
                'end' => $row['end_time'],
                'lecturer' => trim(($row['title'] ? $row['title'] . '. ' : '') . $row['user_fname'] . ' ' . $row['user_lname']),
                'profile' => !empty($row['profile_img']) && file_exists($profilePath) ? $profilePath : '',
                'initials' => strtoupper(substr($row['user_fname'] ?? 'L', 0, 1) . substr($row['user_lname'] ?? '', 0, 1)),
                'type' => (int)$row['lecture_type_id'] === 1 ? 'Physical' : 'Online',
                'intake' => $row['intake'],
                'programme' => $row['programme'],
                'subject' => !empty($row['lecture_title']) ? $row['lecture_title'] : $row['subject'],
                'status' => $status,
            ];
        }

        $programmes = self::fetchAll("SELECT programme_id AS id, programme AS name FROM programmes ORDER BY programme ASC");

        return [
            'student_cards' => $studentCards,
            'finance_cards' => $financeCards,
            'system_cards' => $systemCards,
            'insights' => $insights,
            'trend' => $trend,
            'programme_mix' => $programmeMix,
            'live' => $liveClasses,
            'actions' => $actions,
            'batches' => $batches,
            'events' => $events,
            'deadlines' => $deadlines,
            'lectures' => $lectures,
            'programmes' => $programmes,
            'range' => [
                'from' => $startDate,
                'to' => $endDate,
                'prev_from' => $prevStart,
                'prev_to' => $prevEnd,
                'days' => $periodDays,
            ],
        ];
    }

    public static function getDashboardSummary()
    {
        $data = self::getComprehensiveDashboardData();
        return array_merge($data['student_cards'], $data['finance_cards'], $data['system_cards']);
    }
}
