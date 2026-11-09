<?php

class AdminDashboardSummary
{
    private static function fetchRow($sql)
    {
        $result = Database::search($sql);
        return ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [];
    }

    private static function getMonthKeys($monthsBack = 4)
    {
        $keys = [];
        for ($i = $monthsBack; $i >= 0; $i--) {
            $keys[] = date('Y-m', strtotime("-{$i} month"));
        }
        return $keys;
    }

    private static function getMonthlySeries($sql, $valueKey = 'total')
    {
        $map = array_fill_keys(self::getMonthKeys(4), 0);
        $rs = Database::search($sql);
        if ($rs) {
            while ($row = $rs->fetch_assoc()) {
                if (isset($map[$row['month_key']])) {
                    $map[$row['month_key']] = (float)($row[$valueKey] ?? 0);
                }
            }
        }
        return array_values($map);
    }

    private static function buildChangeMeta($current, $previous, $period = 'vs previous period')
    {
        $current = (float)$current;
        $previous = (float)$previous;

        if ($previous == 0) {
            return [
                'change' => $current == 0 ? '0%' : 'New',
                'change_type' => $current == 0 ? 'neutral' : 'positive',
                'period' => $period,
                'good_up' => true
            ];
        }

        $percent = round((($current - $previous) / $previous) * 100, 1);
        return [
            'change' => ($percent > 0 ? '+' : '') . $percent . '%',
            'change_type' => $percent > 0 ? 'positive' : ($percent < 0 ? 'negative' : 'neutral'),
            'period' => $period,
            'good_up' => $percent >= 0
        ];
    }

    private static function escape($value)
    {
        return addslashes((string)$value);
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

        // Validate dates before using them in SQL.
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match($datePattern, $startDate) || !preg_match($datePattern, $endDate)) {
            $startDate = $now->format('Y-m-01');
            $endDate = $todayDate;
        }
        if ($startDate > $endDate) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }

        $search = self::escape($searchFilter);
        $programmeSql = $programmeFilter > 0 ? " AND i.programmes_programme_id = {$programmeFilter} " : "";
        $searchStudentSql = "";
        if ($search !== '') {
            $searchStudentSql = " AND (
                CONCAT(COALESCE(ld.first_name,''),' ',COALESCE(ld.last_name,'')) LIKE '%{$search}%'
                OR COALESCE(ld.email,'') LIKE '%{$search}%'
                OR COALESCE(ld.mobile,'') LIKE '%{$search}%'
                OR i.intake LIKE '%{$search}%'
                OR p.programme LIKE '%{$search}%'
            ) ";
        }

        /*
         * Student statistics:
         * The date range is based on the admission date in students_has_intakes.
         * Programme/search filters are applied through the student's intake.
         */
        $studentBase = "
            FROM students s
            INNER JOIN login_details ld
                ON s.login_details_login_details_id = ld.login_details_id
            INNER JOIN students_has_intakes shi
                ON s.student_id = shi.students_student_id
            INNER JOIN intakes i
                ON shi.intakes_intake_id = i.intake_id
            INNER JOIN programmes p
                ON i.programmes_programme_id = p.programme_id
            WHERE ld.status_status_id = 1
              AND shi.addmission_date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql}
              {$searchStudentSql}
        ";

        $generalStats = self::fetchRow("
            SELECT
                COUNT(DISTINCT s.student_id) AS student_count,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 1 THEN s.student_id END) AS active_students,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 2 THEN s.student_id END) AS inactive_students,
                COUNT(DISTINCT CASE WHEN shi.status_status_id = 3 THEN s.student_id END) AS discontinued_students
            {$studentBase}
        ");

        $totalStudents = (int)($generalStats['student_count'] ?? 0);
        $activeStudents = (int)($generalStats['active_students'] ?? 0);
        $inactiveStudents = (int)($generalStats['inactive_students'] ?? 0);
        $discontinuedStudents = (int)($generalStats['discontinued_students'] ?? 0);

        $activePct = $totalStudents > 0 ? round(($activeStudents / $totalStudents) * 100, 1) : 0;
        $inactivePct = $totalStudents > 0 ? round(($inactiveStudents / $totalStudents) * 100, 1) : 0;
        $discontinuePct = $totalStudents > 0 ? round(($discontinuedStudents / $totalStudents) * 100, 1) : 0;

        /*
         * Admission and payment figures use the selected date range.
         */
        $admissionStats = self::fetchRow("
            SELECT COUNT(DISTINCT shi.students_student_id) AS total
            FROM students_has_intakes shi
            INNER JOIN students s ON shi.students_student_id = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            WHERE shi.addmission_date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql}
              {$searchStudentSql}
        ");

        $paymentData = self::fetchRow("
            SELECT
                COALESCE(SUM(CASE WHEN cpp_paid.paid_total IS NOT NULL THEN cpp_paid.paid_total ELSE 0 END), 0) AS received_income,
                COALESCE(SUM(CASE WHEN cp.due_date BETWEEN '{$startDate}' AND '{$endDate}' THEN
                    GREATEST(cp.amount - COALESCE(
                        CASE
                            WHEN cp.discount_type_discount_type_id = 2 THEN cp.discount
                            WHEN cp.discount_type_discount_type_id = 3 THEN cp.amount * cp.discount / 100
                            ELSE 0
                        END, 0), 0)
                    ELSE 0 END), 0) AS expected_income,
                COALESCE(SUM(CASE WHEN cp.due_date BETWEEN '{$startDate}' AND '{$endDate}' THEN
                    GREATEST(cp.amount - COALESCE(
                        CASE
                            WHEN cp.discount_type_discount_type_id = 2 THEN cp.discount
                            WHEN cp.discount_type_discount_type_id = 3 THEN cp.amount * cp.discount / 100
                            ELSE 0
                        END, 0), 0)
                    ELSE 0 END), 0)
                - COALESCE(SUM(COALESCE(cpp_paid.paid_total, 0)), 0) AS balance_payment
            FROM course_payments cp
            INNER JOIN payment_plan_has_students_has_intakes pshi
                ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
            INNER JOIN students_has_intakes shi
                ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
            INNER JOIN students s ON shi.students_student_id = s.student_id
            INNER JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            LEFT JOIN (
                SELECT
                    course_payments_course_payments_id,
                    SUM(CASE WHEN payment_status_payment_status_id = 3
                        AND date_paid BETWEEN '{$startDate}' AND '{$endDate}'
                        THEN COALESCE(paid_amount, 0) + COALESCE(panelty_amount, 0) - COALESCE(discount_amount, 0)
                        ELSE 0 END) AS paid_total
                FROM course_payment_has_payments
                GROUP BY course_payments_course_payments_id
            ) cpp_paid ON cp.course_payments_id = cpp_paid.course_payments_course_payments_id
            WHERE (
                cp.due_date BETWEEN '{$startDate}' AND '{$endDate}'
                OR cpp_paid.paid_total IS NOT NULL
            )
            {$programmeSql}
            " . ($search !== '' ? " AND (
                CONCAT(COALESCE(ld.first_name,''),' ',COALESCE(ld.last_name,'')) LIKE '%{$search}%'
                OR COALESCE(ld.email,'') LIKE '%{$search}%'
                OR COALESCE(ld.mobile,'') LIKE '%{$search}%'
                OR i.intake LIKE '%{$search}%'
                OR p.programme LIKE '%{$search}%'
            ) " : "") . "
        ");

        // Global system counts are intentionally not affected by academic/date filters.
        $systemStats = self::fetchRow("
            SELECT
                (SELECT COUNT(*) FROM users WHERE status_status_id = 1 AND user_roles_user_role_id != 20) AS users_count,
                (SELECT COUNT(*) FROM users WHERE status_status_id = 1 AND user_roles_user_role_id = 20) AS lecturers_count,
                (SELECT COUNT(*) FROM faculties) AS faculties_count,
                (SELECT COUNT(*) FROM programmes) AS programme_count
        ");

        $admissionsMeta = self::buildChangeMeta($admissionStats['total'] ?? 0, 0);
        $expectedIncome = (float)($paymentData['expected_income'] ?? 0);
        $receivedIncome = (float)($paymentData['received_income'] ?? 0);
        $balancePayment = max(0, (float)($paymentData['balance_payment'] ?? 0));

        $studentCards = [
            [
                'title' => 'Total Students',
                'value' => $totalStudents,
                'icon' => 'mdi:school',
                'note' => 'Students admitted in selected period',
                'spark' => [],
                'href' => 'students'
            ],
            [
                'title' => 'Active Students',
                'value' => $activeStudents,
                'icon' => 'mdi:account-check',
                'share' => $activePct,
                'share_tone' => 'g',
                'note' => 'of filtered students',
                'spark' => [],
                'href' => 'students'
            ],
            [
                'title' => 'Inactive Students',
                'value' => $inactiveStudents,
                'icon' => 'mdi:account-clock',
                'share' => $inactivePct,
                'share_tone' => 'o',
                'note' => 'of filtered students',
                'spark' => [],
                'href' => 'students'
            ],
            [
                'title' => 'Discontinue Students',
                'value' => $discontinuedStudents,
                'icon' => 'mdi:account-cancel',
                'share' => $discontinuePct,
                'share_tone' => 'r',
                'note' => 'of filtered students',
                'spark' => [],
                'href' => 'students'
            ]
        ];

        $financeCards = [
            [
                'title' => 'New Admissions',
                'value' => (int)($admissionStats['total'] ?? 0),
                'icon' => 'mdi:account-plus',
                'change' => (float)str_replace(['+', '%', 'New'], '', $admissionsMeta['change']),
                'good_up' => true,
                'spark' => [],
                'href' => 'students'
            ],
            [
                'title' => 'Expected Income',
                'value' => $expectedIncome,
                'format' => 'money',
                'icon' => 'mdi:trending-up',
                'change' => 0,
                'good_up' => true,
                'spark' => [],
                'href' => 'finance'
            ],
            [
                'title' => 'Received Payment',
                'value' => $receivedIncome,
                'format' => 'money',
                'icon' => 'mdi:wallet',
                'change' => 0,
                'good_up' => true,
                'spark' => [],
                'href' => 'finance'
            ],
            [
                'title' => 'Balance Payment',
                'value' => $balancePayment,
                'format' => 'money',
                'icon' => 'mdi:coins',
                'change' => 0,
                'good_up' => false,
                'spark' => [],
                'href' => 'finance'
            ]
        ];

        $systemCards = [
            ['title' => 'Users', 'value' => (int)($systemStats['users_count'] ?? 0), 'note' => 'Active', 'icon' => 'mdi:account-group', 'href' => 'users'],
            ['title' => 'Faculties', 'value' => (int)($systemStats['faculties_count'] ?? 0), 'note' => 'Total', 'icon' => 'mdi:domain', 'href' => 'faculties'],
            ['title' => 'Programmes', 'value' => (int)($systemStats['programme_count'] ?? 0), 'note' => 'Total', 'icon' => 'mdi:book-open-page-variant', 'href' => 'programmes'],
            ['title' => 'Lecturers', 'value' => (int)($systemStats['lecturers_count'] ?? 0), 'note' => 'Active', 'icon' => 'mdi:teach', 'href' => 'lecturers']
        ];

        // Live classes: selected dates, programme and search are all applied.
        $liveClasses = [];
        $liveSql = "
            SELECT tt.time_table_id, tt.start_time, tt.end_time, tt.lecture, tt.lecture_title,
                   subj.subject, i.intake, u.user_fname, u.user_lname, u.profile_img, t.title,tt.date
            FROM time_table tt
            INNER JOIN subjects subj ON tt.subjects_subject_id = subj.subject_id
            INNER JOIN levels lv ON subj.levels_level_id = lv.level_id
            INNER JOIN intakes i ON lv.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            INNER JOIN lecturers lec ON tt.lecturers_lecturers_id = lec.lecturers_id
            INNER JOIN users u ON lec.users_user_id = u.user_id
            INNER JOIN title t ON u.title_title_id = t.title_id
            WHERE tt.date BETWEEN '{$startDate}' AND '{$endDate}'
              AND tt.lecture_type_lecture_type_id = 2
              AND tt.time_table_status_time_table_status_id = 1
              {$programmeSql}
              " . ($search !== '' ? " AND (
                    i.intake LIKE '%{$search}%'
                    OR p.programme LIKE '%{$search}%'
                    OR subj.subject LIKE '%{$search}%'
                    OR tt.lecture_title LIKE '%{$search}%'
                    OR CONCAT(u.user_fname,' ',u.user_lname) LIKE '%{$search}%'
              ) " : "") . "
            ORDER BY tt.date ASC, tt.start_time ASC
        ";
        $liveRes = Database::search($liveSql);
        if ($liveRes) {
            while ($r = $liveRes->fetch_assoc()) {
                $status = 'Upcoming';
                if ($r['start_time'] <= $nowTime && $r['end_time'] >= $nowTime && $r['date'] === $todayDate) {
                    $status = 'Ongoing';
                } elseif ($r['date'] < $todayDate || ($r['date'] === $todayDate && $nowTime > $r['end_time'])) {
                    $status = 'Ended';
                }
                $liveClasses[] = [
                    'id' => $r['time_table_id'],
                    'subject' => !empty($r['lecture_title']) ? $r['lecture_title'] : $r['subject'],
                    'intake' => $r['intake'],
                    'lecturer' => $r['title'] . '. ' . $r['user_fname'] . ' ' . $r['user_lname'],
                    'initials' => strtoupper(substr($r['user_fname'], 0, 1) . substr($r['user_lname'], 0, 1)),
                    'start' => $r['start_time'],
                    'end' => $r['end_time'],
                    'status' => $status,
                    'link' => $r['lecture']
                ];
            }
        }

        // Management counts are filtered where their database relationships permit it.
        $inquiries = self::fetchRow(
            "SELECT COUNT(*) AS total FROM students_has_help_desk
             WHERE help_desk_status_help_desk_status_id = 1"
        );

        $apps = self::fetchRow("
            SELECT COUNT(DISTINCT cp.course_payments_id) AS total
            FROM course_payments cp
            INNER JOIN payment_plan_has_students_has_intakes pshi
                ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
            INNER JOIN students_has_intakes shi
                ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            WHERE cp.payment_status_payment_status_id = 2
              AND shi.addmission_date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql}
        ");

        $overdue = self::fetchRow("
            SELECT COUNT(DISTINCT cp.course_payments_id) AS total
            FROM course_payments cp
            INNER JOIN payment_plan_has_students_has_intakes pshi
                ON cp.payment_plan_has_students_has_intakes_id = pshi.payment_plan_has_students_has_intakes_id
            INNER JOIN students_has_intakes shi
                ON pshi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            WHERE cp.payment_status_payment_status_id = 1
              AND cp.due_date < CURDATE()
              AND shi.addmission_date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql}
        ");

        $certs = self::fetchRow("
            SELECT COUNT(DISTINCT shi.students_student_id) AS total
            FROM students_has_intakes shi
            INNER JOIN intakes i ON shi.intakes_intake_id = i.intake_id
            WHERE shi.status_status_id = 4
              AND shi.addmission_date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql}
        ");

        $actions = [
            ['label' => 'New inquiries to call back', 'count' => (int)($inquiries['total'] ?? 0), 'tone' => 'warn', 'icon' => 'mdi:phone-in-talk-outline', 'href' => 'inquiries'],
            ['label' => 'Applications to review', 'count' => (int)($apps['total'] ?? 0), 'tone' => 'info', 'icon' => 'mdi:file-document-edit-outline', 'href' => 'enrollments'],
            ['label' => 'Overdue fee reminders', 'count' => (int)($overdue['total'] ?? 0), 'tone' => 'bad', 'icon' => 'mdi:alert-circle-outline', 'href' => 'awaiting-payments'],
            ['label' => 'Students below 75% attendance', 'count' => 0, 'tone' => 'warn', 'icon' => 'mdi:account-alert-outline', 'href' => 'attendance'],
            ['label' => 'Certificates ready to issue', 'count' => (int)($certs['total'] ?? 0), 'tone' => 'info', 'icon' => 'mdi:certificate-outline', 'href' => '']
        ];

        // Batches: filter by programme, admission date and search term.
        $batches = [];
        $batchesSql = "
            SELECT i.intake_id, i.intake, p.programme,
                   COUNT(DISTINCT shi.students_student_id) AS total_students,
                   COUNT(DISTINCT CASE WHEN shi.status_status_id = 1 THEN shi.students_student_id END) AS active_students
            FROM intakes i
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            LEFT JOIN students_has_intakes shi
                ON i.intake_id = shi.intakes_intake_id
                AND shi.addmission_date BETWEEN '{$startDate}' AND '{$endDate}'
            LEFT JOIN students s ON shi.students_student_id = s.student_id
            LEFT JOIN login_details ld ON s.login_details_login_details_id = ld.login_details_id
            WHERE 1=1 {$programmeSql}
              " . ($search !== '' ? " AND (i.intake LIKE '%{$search}%' OR p.programme LIKE '%{$search}%'
                    OR CONCAT(COALESCE(ld.first_name,''),' ',COALESCE(ld.last_name,'')) LIKE '%{$search}%'
                    OR COALESCE(ld.email,'') LIKE '%{$search}%') " : "") . "
            GROUP BY i.intake_id, i.intake, p.programme
            HAVING COUNT(DISTINCT shi.students_student_id) > 0
            ORDER BY i.intake_id DESC
            LIMIT 5
        ";
        $bRes = Database::search($batchesSql);
        if ($bRes) {
            $progressValues = [85, 60, 40, 20, 10];
            $idx = 0;
            while ($b = $bRes->fetch_assoc()) {
                $progress = $progressValues[$idx % count($progressValues)];
                $state = $progress > 80 ? 'Ending soon' : ($progress < 25 ? 'New intake' : 'Running');
                $batches[] = [
                    'intake' => $b['intake'],
                    'programme' => $b['programme'],
                    'progress' => $progress,
                    'active' => (int)$b['active_students'],
                    'students' => (int)$b['total_students'],
                    'state' => $state
                ];
                $idx++;
            }
        }

        // Events and deadlines are calendar items, not student/lecture statistics.
        $events = [];
        $deadlines = [];
        $checkTable = Database::search("SHOW TABLES LIKE 'academic_events'");
        if ($checkTable && $checkTable->num_rows > 0) {
            $resE = Database::search("
                SELECT * FROM academic_events
                WHERE status = 1 AND event_type IN ('event','workshop','site_visit')
                  AND start_date BETWEEN '{$startDate}' AND '{$endDate}'
                ORDER BY start_date ASC, start_time ASC LIMIT 5
            ");
            if ($resE) {
                while ($r = $resE->fetch_assoc()) {
                    $timeStr = 'All Day';
                    if (!empty($r['start_time']) && !empty($r['end_time'])) {
                        $timeStr = date('h:i A', strtotime($r['start_time'])) . ' – ' . date('h:i A', strtotime($r['end_time']));
                    }
                    $events[] = [
                        'date' => $r['start_date'],
                        'title' => $r['title'],
                        'time' => $timeStr,
                        'highlight' => (bool)$r['is_highlighted']
                    ];
                }
            }

            $resD = Database::search("
                SELECT * FROM academic_events
                WHERE status = 1 AND event_type IN ('deadline','orientation')
                  AND start_date BETWEEN '{$startDate}' AND '{$endDate}'
                ORDER BY start_date ASC, start_time ASC LIMIT 5
            ");
            if ($resD) {
                while ($r = $resD->fetch_assoc()) {
                    $deadlines[] = [
                        'date' => $r['start_date'],
                        'title' => $r['title'],
                        'highlight' => (bool)$r['is_highlighted']
                    ];
                }
            }
        }

        // Lecture schedule: selected date range, programme and search.
        $lectures = [];
        $lecSql = "
            SELECT tt.time_table_id, tt.date, tt.start_time, tt.end_time, tt.lecture_title,
                   lt.lecture_type, lt.lecture_type_id, i.intake, subj.subject,
                   p.programme, p.programme_id, u.user_id, u.user_fname, u.user_lname,
                   u.profile_img, t.title
            FROM time_table tt
            INNER JOIN subjects subj ON tt.subjects_subject_id = subj.subject_id
            INNER JOIN levels lv ON subj.levels_level_id = lv.level_id
            INNER JOIN intakes i ON lv.intakes_intake_id = i.intake_id
            INNER JOIN programmes p ON i.programmes_programme_id = p.programme_id
            INNER JOIN lecture_type lt ON tt.lecture_type_lecture_type_id = lt.lecture_type_id
            INNER JOIN lecturers lec ON tt.lecturers_lecturers_id = lec.lecturers_id
            INNER JOIN users u ON lec.users_user_id = u.user_id
            INNER JOIN title t ON u.title_title_id = t.title_id
            WHERE lt.lecture_type_id != 3
              AND tt.date BETWEEN '{$startDate}' AND '{$endDate}'
              {$programmeSql}
              " . ($search !== '' ? " AND (
                    i.intake LIKE '%{$search}%'
                    OR p.programme LIKE '%{$search}%'
                    OR subj.subject LIKE '%{$search}%'
                    OR tt.lecture_title LIKE '%{$search}%'
                    OR CONCAT(u.user_fname,' ',u.user_lname) LIKE '%{$search}%'
              ) " : "") . "
            ORDER BY tt.date DESC, tt.start_time ASC
            LIMIT 25
        ";
        $lecRes = Database::search($lecSql);
        if ($lecRes) {
            while ($row = $lecRes->fetch_assoc()) {
                $status = 'Upcoming';
                if ($row['date'] === $todayDate) {
                    if ($nowTime >= $row['start_time'] && $nowTime <= $row['end_time']) {
                        $status = 'Ongoing';
                    } elseif ($nowTime > $row['end_time']) {
                        $status = 'Completed';
                    }
                } elseif ($row['date'] < $todayDate) {
                    $status = 'Completed';
                }

                $profilePath = './assets/imgs/users/' . ($row['profile_img'] ?? '');
                $lectures[] = [
                    'id' => $row['time_table_id'],
                    'date' => $row['date'],
                    'start' => $row['start_time'],
                    'end' => $row['end_time'],
                    'lecturer' => $row['title'] . '. ' . $row['user_fname'] . ' ' . $row['user_lname'],
                    'profile' => !empty($row['profile_img']) && file_exists($profilePath) ? $profilePath : '',
                    'initials' => strtoupper(substr($row['user_fname'] ?? 'L', 0, 1) . substr($row['user_lname'] ?? '', 0, 1)),
                    'type' => (int)$row['lecture_type_id'] === 1 ? 'Physical' : 'Online',
                    'intake' => $row['intake'],
                    'subject' => !empty($row['lecture_title']) ? $row['lecture_title'] : $row['subject'],
                    'status' => $status
                ];
            }
        }

        $programmes = [];
        $progRes = Database::search("SELECT programme_id AS id, programme AS name FROM programmes ORDER BY programme ASC");
        if ($progRes) {
            while ($p = $progRes->fetch_assoc()) {
                $programmes[] = $p;
            }
        }

        return [
            'student_cards' => $studentCards,
            'finance_cards' => $financeCards,
            'system_cards' => $systemCards,
            'live' => $liveClasses,
            'actions' => $actions,
            'batches' => $batches,
            'events' => $events,
            'deadlines' => $deadlines,
            'lectures' => $lectures,
            'programmes' => $programmes,
            'range' => ['from' => $startDate, 'to' => $endDate]
        ];
    }

    public static function getDashboardSummary()
    {
        $data = self::getComprehensiveDashboardData();
        return array_merge($data['student_cards'], $data['finance_cards'], $data['system_cards']);
    }
}
