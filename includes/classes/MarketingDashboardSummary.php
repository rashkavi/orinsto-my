<?php

class MarketingDashboardSummary
{
    private static function fetchRow($sql)
    {
        $result = Database::search($sql);
        return $result ? $result->fetch_assoc() : [];
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
        $STATUS_NEW = 1;
        $STATUS_WARM = 3;
        $STATUS_BUSY = 4; // No Answer
        $STATUS_SLEEP = 5;
        $STATUS_BLACKLIST = 8;
        $STATUS_HOT = 2;
        $STATUS_FUTURE = 7;
        $STATUS_COMPLETED = 6;
        $STATUS_COLD = 8; // change if your Cold status IDs are different

        $cardData = self::fetchRow("
    SELECT 
        COUNT(*) AS total_leads,

        SUM(CASE WHEN DATE(date_added) = CURDATE() THEN 1 ELSE 0 END) AS today_leads,

        SUM(CASE 
            WHEN date_added >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
             AND date_added < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 7 DAY)
            THEN 1 ELSE 0 
        END) AS weekly_leads,

        SUM(CASE 
            WHEN YEAR(date_added) = YEAR(CURDATE())
             AND MONTH(date_added) = MONTH(CURDATE())
            THEN 1 ELSE 0 
        END) AS monthly_leads,

        SUM(CASE WHEN lead_status_lead_status_id IN ($STATUS_HOT) THEN 1 ELSE 0 END) AS hot_leads,
        SUM(CASE WHEN lead_status_lead_status_id IN ($STATUS_WARM) THEN 1 ELSE 0 END) AS warm_leads,
        SUM(CASE WHEN lead_status_lead_status_id IN ($STATUS_COLD) THEN 1 ELSE 0 END) AS cold_leads,
        SUM(CASE WHEN lead_status_lead_status_id = $STATUS_FUTURE THEN 1 ELSE 0 END) AS future_leads,
        SUM(CASE WHEN lead_status_lead_status_id = $STATUS_BUSY THEN 1 ELSE 0 END) AS no_answer_leads,
        SUM(CASE WHEN lead_status_lead_status_id = $STATUS_SLEEP THEN 1 ELSE 0 END) AS sleep_calls,
        SUM(CASE WHEN lead_status_lead_status_id IN ($STATUS_COMPLETED) THEN 1 ELSE 0 END) AS enrolled_leads,

        SUM(CASE 
            WHEN remind_date IS NOT NULL
             AND DATE(remind_date) < CURDATE()
             AND lead_status_lead_status_id NOT IN ($STATUS_COMPLETED)
             AND lead_status_lead_status_id NOT IN ($STATUS_BLACKLIST)
            THEN 1 ELSE 0 
        END) AS non_plan_followed
    FROM leads
");

        $todayLeadsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $weeklyLeadsChart = $todayLeadsChart;
        $monthlyLeadsChart = $todayLeadsChart;
        $totalLeadsChart = $todayLeadsChart;

        $hotLeadsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id IN ($STATUS_HOT)
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $warmLeadsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id IN ($STATUS_WARM)
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $coldLeadsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id IN ($STATUS_COLD)
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $futureLeadsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id = $STATUS_FUTURE
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $noAnswerChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id = $STATUS_BUSY
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $sleepCallsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id = $STATUS_SLEEP
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $nonPlanFollowedChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(remind_date, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE remind_date IS NOT NULL
      AND DATE(remind_date) < CURDATE()
      AND lead_status_lead_status_id NOT IN ($STATUS_COMPLETED)
      AND lead_status_lead_status_id NOT IN ($STATUS_BLACKLIST)
      AND remind_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(remind_date, '%Y-%m')
");

        $enrolledLeadsChart = self::getMonthlySeries("
    SELECT DATE_FORMAT(date_added, '%Y-%m') AS month_key, COUNT(*) AS total
    FROM leads
    WHERE lead_status_lead_status_id IN ($STATUS_COMPLETED)
      AND date_added >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), '%Y-%m-01')
    GROUP BY DATE_FORMAT(date_added, '%Y-%m')
");

        $dashboardCards = [
            [
                'title' => 'Today Leads',
                'value' => number_format((int)$cardData['today_leads']),
                'icon' => 'mdi:calendar-today-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Today',
                'chart' => $todayLeadsChart
            ],
            [
                'title' => 'Weekly Leads',
                'value' => number_format((int)$cardData['weekly_leads']),
                'icon' => 'mdi:calendar-week-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'This week',
                'chart' => $weeklyLeadsChart
            ],
            [
                'title' => 'Monthly Leads',
                'value' => number_format((int)$cardData['monthly_leads']),
                'icon' => 'mdi:calendar-month-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'This month',
                'chart' => $monthlyLeadsChart
            ],
            [
                'title' => 'Total Leads',
                'value' => number_format((int)$cardData['total_leads']),
                'icon' => 'mdi:account-multiple-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'All leads',
                'chart' => $totalLeadsChart
            ],
            [
                'title' => 'Hot Leads',
                'value' => number_format((int)$cardData['hot_leads']),
                'icon' => 'mdi:fire',
                'change' => '',
                'change_type' => 'positive',
                'period' => 'High priority',
                'chart' => $hotLeadsChart,
                 'theme' => 'hot'
            ],
            [
                'title' => 'Warm Leads',
                'value' => number_format((int)$cardData['warm_leads']),
                'icon' => 'mdi:weather-sunny',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Follow-up needed',
                'chart' => $warmLeadsChart,
                 'theme' => 'warm'
            ],
            [
                'title' => 'Cold Leads',
                'value' => number_format((int)$cardData['cold_leads']),
                'icon' => 'mdi:snowflake',
                'change' => '',
                'change_type' => 'negative',
                'period' => 'Low priority',
                'chart' => $coldLeadsChart,
                 'theme' => 'cold'
            ],
            [
                'title' => 'Future Leads',
                'value' => number_format((int)$cardData['future_leads']),
                'icon' => 'mdi:calendar-clock-outline',
                'change' => '',
                'change_type' => 'neutral',
                'period' => 'Future follow-up',
                'chart' => $futureLeadsChart,
                 'theme' => 'future'
            ],
            [
                'title' => 'Busy Calls',
                'value' => number_format((int)$cardData['no_answer_leads']),
                'icon' => 'mdi:phone-missed-outline',
                'change' => '',
                'change_type' => 'negative',
                'period' => 'Busy calls',
                'chart' => $noAnswerChart,
                 'theme' => 'busy'
            ],
            [
                'title' => 'Sleep Calls',
                'value' => number_format((int)$cardData['sleep_calls']),
                'icon' => 'mdi:sleep',
                'change' => '',
                'change_type' => 'negative',
                'period' => 'Sleep status',
                'chart' => $sleepCallsChart,
                 'theme' => 'sleep'
            ],
            [
                'title' => 'Enrolled Leads',
                'value' => number_format((int)$cardData['enrolled_leads']),
                'icon' => 'mdi:account-check-outline',
                'change' => '',
                'change_type' => 'positive',
                'period' => 'Converted leads',
                'chart' => $enrolledLeadsChart,
                 'theme' => 'enrolled'
            ],
            [
                'title' => 'Non Follow-Up Leads',
                'value' => number_format((int)$cardData['non_plan_followed']),
                'icon' => 'mdi:calendar-alert-outline',
                'change' => '',
                'change_type' => 'negative',
                'period' => 'Missed follow-ups',
                'chart' => $nonPlanFollowedChart
            ],
        ];

        return $dashboardCards;
    }
}
