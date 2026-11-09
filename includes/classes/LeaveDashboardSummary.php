<?php

class LeaveDashboardSummary
{
    private static function fetchRow($sql)
    {
        return Database::search($sql)->fetch_assoc();
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
        $seriesMap = array_fill_keys(self::getMonthKeys(4), 0);

        $rs = Database::search($sql);

        while ($row = $rs->fetch_assoc()) {
            if (isset($seriesMap[$row['month_key']])) {
                $seriesMap[$row['month_key']] = (float)$row[$valueKey];
            }
        }

        return array_values($seriesMap);
    }

    private static function buildChangeMeta($current, $previous, $period = 'vs last month')
    {
        $current = (float)$current;
        $previous = (float)$previous;

        if ($previous == 0 && $current == 0) {
            return [
                'change' => '0%',
                'change_type' => 'neutral',
                'period' => $period
            ];
        }

        if ($previous == 0 && $current > 0) {
            return [
                'change' => 'New',
                'change_type' => 'positive',
                'period' => $period
            ];
        }

        $percent = (($current - $previous) / $previous) * 100;
        $rounded = round($percent, 1);

        return [
            'change' => ($rounded > 0 ? '+' : '') . $rounded . '%',
            'change_type' => $rounded > 0 ? 'positive' : ($rounded < 0 ? 'negative' : 'neutral'),
            'period' => $period
        ];
    }

    public static function getDashboardSummary()
    {
        $STATUS_PENDING = 1;
        $STATUS_APPROVED = 2;
        $STATUS_REJECTED = 3;
        $STATUS_CANCELLED = 4;

        $cardData = self::fetchRow("
            SELECT

            (
                SELECT COUNT(*)
                FROM `leave_requests`
            ) AS total_leave_requests,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_PENDING}
            ) AS pending_leave_requests,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
            ) AS approved_leave_requests,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_REJECTED}
            ) AS rejected_leave_requests,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE DATE(`requested_at`) = CURDATE()
            ) AS today_leave_requests,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE DATE(`requested_at`) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            ) AS yesterday_leave_requests,

            (
                SELECT COUNT(DISTINCT `users_user_id`)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
                AND CURDATE() BETWEEN `from_date` AND `to_date`
            ) AS today_leave_users,

            (
                SELECT COUNT(DISTINCT `users_user_id`)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
                AND DATE_SUB(CURDATE(), INTERVAL 1 DAY) BETWEEN `from_date` AND `to_date`
            ) AS yesterday_leave_users,

            (
                SELECT COALESCE(SUM(`total_days`), 0)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
                AND MONTH(`from_date`) = MONTH(CURDATE())
                AND YEAR(`from_date`) = YEAR(CURDATE())
            ) AS monthly_used_leave_days,

            (
                SELECT COALESCE(SUM(`total_days`), 0)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
                AND MONTH(`from_date`) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                AND YEAR(`from_date`) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            ) AS previous_month_used_leave_days,

            (
                SELECT COALESCE(SUM(`total_days`), 0)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_PENDING}
            ) AS pending_leave_days,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_PENDING}
                AND `requested_at` <= DATE_SUB(NOW(), INTERVAL 2 DAY)
            ) AS delayed_approval_requests,

            (
                SELECT COUNT(*)
                FROM `leave_requests`
                WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
                AND `from_date` > CURDATE()
                AND `from_date` <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ) AS upcoming_week_leave_requests,

            (
                SELECT COUNT(*)
                FROM `leave_types`
                WHERE `status_status_id` = 1
            ) AS active_leave_types,

            (
                SELECT COUNT(*)
                FROM `users`
                WHERE `status_status_id` = 1
            ) AS active_users,

            (
                SELECT COUNT(DISTINCT `users_user_id`)
                FROM `leave_requests`
                WHERE YEAR(`from_date`) = YEAR(CURDATE())
            ) AS yearly_leave_requested_users
        ");

        $todayLeaveMeta = self::buildChangeMeta(
            $cardData['today_leave_users'],
            $cardData['yesterday_leave_users'],
            'vs yesterday'
        );

        $todayRequestMeta = self::buildChangeMeta(
            $cardData['today_leave_requests'],
            $cardData['yesterday_leave_requests'],
            'vs yesterday'
        );

        $usedDaysMeta = self::buildChangeMeta(
            $cardData['monthly_used_leave_days'],
            $cardData['previous_month_used_leave_days'],
            'vs last month'
        );

        $requestChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(`requested_at`, '%Y-%m') AS month_key,
                COUNT(*) AS total
            FROM `leave_requests`
            WHERE `requested_at` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(`requested_at`, '%Y-%m')
        ");

        $approvedChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(`from_date`, '%Y-%m') AS month_key,
                COUNT(*) AS total
            FROM `leave_requests`
            WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
            AND `from_date` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(`from_date`, '%Y-%m')
        ");

        $usedDaysChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(`from_date`, '%Y-%m') AS month_key,
                COALESCE(SUM(`total_days`), 0) AS total
            FROM `leave_requests`
            WHERE `leave_request_status_leave_request_status_id` = {$STATUS_APPROVED}
            AND `from_date` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(`from_date`, '%Y-%m')
        ");

        $pendingChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(`requested_at`, '%Y-%m') AS month_key,
                COUNT(*) AS total
            FROM `leave_requests`
            WHERE `leave_request_status_leave_request_status_id` = {$STATUS_PENDING}
            AND `requested_at` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(`requested_at`, '%Y-%m')
        ");

        $rejectedChart = self::getMonthlySeries("
            SELECT
                DATE_FORMAT(`reviewed_at`, '%Y-%m') AS month_key,
                COUNT(*) AS total
            FROM `leave_requests`
            WHERE `leave_request_status_leave_request_status_id` = {$STATUS_REJECTED}
            AND `reviewed_at` IS NOT NULL
            AND `reviewed_at` >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
            GROUP BY DATE_FORMAT(`reviewed_at`, '%Y-%m')
        ");

        $activeUserCount = (int)$cardData['active_users'];
        $yearlyRequestedUsers = (int)$cardData['yearly_leave_requested_users'];
        $leaveUsageCoverage = $activeUserCount > 0 ? round(($yearlyRequestedUsers / $activeUserCount) * 100, 1) : 0;

        $approvalRate = ((int)$cardData['total_leave_requests'] > 0)
            ? round(((int)$cardData['approved_leave_requests'] / (int)$cardData['total_leave_requests']) * 100, 1)
            : 0;

        $dashboardCards = [
            [
                'title' => 'Today Leave Users',
                'value' => number_format((int)$cardData['today_leave_users']),
                'icon' => 'mdi:account-arrow-right-outline',
                'change' => $todayLeaveMeta['change'],
                'change_type' => $todayLeaveMeta['change_type'],
                'period' => $todayLeaveMeta['period'],
                'chart' => []
            ],
            [
                'title' => 'Today Leave Requests',
                'value' => number_format((int)$cardData['today_leave_requests']),
                'icon' => 'mdi:calendar-today-outline',
                'change' => $todayRequestMeta['change'],
                'change_type' => $todayRequestMeta['change_type'],
                'period' => $todayRequestMeta['period'],
                'chart' => $requestChart
            ],
            [
                'title' => 'Pending Approvals',
                'value' => number_format((int)$cardData['pending_leave_requests']),
                'icon' => 'mdi:timer-sand',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Awaiting manager / HR review',
                'chart' => $pendingChart
            ],
            [
                'title' => 'Delayed Approvals',
                'value' => number_format((int)$cardData['delayed_approval_requests']),
                'icon' => 'mdi:clock-alert-outline',
                'change' => '',
                'change_type' => 'negative',
                'period' => 'Pending more than 2 days',
                'chart' => []
            ],
            [
                'title' => 'Monthly Used Leave Days',
                'value' => number_format((float)$cardData['monthly_used_leave_days'], 1),
                'icon' => 'mdi:calendar-check-outline',
                'change' => $usedDaysMeta['change'],
                'change_type' => $usedDaysMeta['change_type'],
                'period' => $usedDaysMeta['period'],
                'chart' => $usedDaysChart
            ],
            [
                'title' => 'Pending Leave Days',
                'value' => number_format((float)$cardData['pending_leave_days'], 1),
                'icon' => 'mdi:calendar-clock-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Not yet approved',
                'chart' => $pendingChart
            ],
            [
                'title' => 'Upcoming Week Leaves',
                'value' => number_format((int)$cardData['upcoming_week_leave_requests']),
                'icon' => 'mdi:calendar-arrow-right',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Next 7 days',
                'chart' => []
            ],
            [
                'title' => 'Approval Rate',
                'value' => $approvalRate . '%',
                'icon' => 'mdi:check-decagram-outline',
                'change' => '',
                'change_type' => 'positive',
                'period' => 'Approved vs total requests',
                'chart' => $approvedChart
            ],
            [
                'title' => 'Rejected Requests',
                'value' => number_format((int)$cardData['rejected_leave_requests']),
                'icon' => 'mdi:close-circle-outline',
                'change' => '',
                'change_type' => 'negative',
                'period' => 'Total rejected requests',
                'chart' => $rejectedChart
            ],
            [
                'title' => 'Leave Usage Coverage',
                'value' => $leaveUsageCoverage . '%',
                'icon' => 'mdi:account-group-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Users who requested leave this year',
                'chart' => []
            ],
            [
                'title' => 'Active Leave Types',
                'value' => number_format((int)$cardData['active_leave_types']),
                'icon' => 'mdi:format-list-bulleted-type',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Configured policies',
                'chart' => []
            ],
            [
                'title' => 'System Performance',
                'value' => number_format((int)$cardData['total_leave_requests']),
                'icon' => 'mdi:chart-timeline-variant',
                'change' => '',
                'change_type' => 'positive',
                'period' => 'Total processed leave records',
                'chart' => $requestChart
            ]
        ];

        return $dashboardCards;
    }
}
