<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $id = isset($decoded['id']) ? $decoded['id'] : '';
                    $condition = isset($decoded['condition']) ? $decoded['condition'] : 1;
                    if (empty($id)) {
                        $status->error = "Invalid user Id";
                    } else if (!is_numeric($id)) {
                        $status->error = "Invalid user Id";
                    } else {
                        $isValidUser = Database::search("SELECT COUNT(*) as 'count' FROM `users` WHERE `user_id`='" . $id . "'");
                        $row = $isValidUser->fetch_assoc();
                        if ($row['count'] == 0) {
                            $status->error = "Invalid user Id";
                        } else {
                            if (empty($condition) || $condition < 0 || $condition > 3) {
                                $status->error = 'Invalid condition';
                            } else {

                                $startDateTime = '';
                                $endDateTime = '';

                                switch ($condition) {
                                    case 1:
                                        $startOfWeek = new DateTime('monday this week');
                                        $endOfWeek = (clone $startOfWeek)->modify('+6 days');

                                        $startDateTime = $startOfWeek->format('Y-m-d 00:00:00');
                                        $endDateTime = $endOfWeek->format('Y-m-d 23:59:59');
                                        break;
                                    case 2:

                                        $startOfMonth = new DateTime('first day of this month');
                                        $endOfMonth = new DateTime('last day of this month');

                                        $startDateTime = $startOfMonth->format('Y-m-d 00:00:00');
                                        $endDateTime = $endOfMonth->format('Y-m-d 23:59:59');

                                        break;
                                    case 3:
                                        $currentYear = date('Y');
                                        $startOfYear = new DateTime("$currentYear-01-01 00:00:00");
                                        $endOfYear = new DateTime("$currentYear-12-31 23:59:59");

                                        $startDateTime = $startOfYear->format('Y-m-d 00:00:00');
                                        $endDateTime = $endOfYear->format('Y-m-d 23:59:59');
                                }

                                $leadCountQuery = "
                                SELECT 
                                    DATE(`x`.`date_updated`) AS `day`,
                                    MONTH(`x`.`date_updated`) AS `month`,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` IN (2, 5) THEN 1 
                                        ELSE 0 
                                    END) AS warm_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` = 3 THEN 1 
                                        ELSE 0 
                                    END) AS busy_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` = 4 THEN 1 
                                        ELSE 0 
                                    END) AS sleep_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` IN (7, 11, 12, 13, 14, 15, 16, 17) THEN 1 
                                        ELSE 0 
                                    END) AS hot_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` = 9 THEN 1 
                                        ELSE 0 
                                    END) AS cold_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` = 10 THEN 1 
                                        ELSE 0 
                                    END) AS future_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` = 11 THEN 1 
                                        ELSE 0 
                                    END) AS enrolled_count,
                            
                                    SUM(CASE 
                                        WHEN `x`.`lead_status_lead_status_id` IN (6, 8) THEN 1 
                                        ELSE 0 
                                    END) AS blacklist_count
                            
                                FROM (
                                    SELECT 
                                        `ll1`.`leads_id`,
                                        `ll1`.`lead_status_lead_status_id`,
                                        `ll1`.`date_updated`
                                    FROM `lead_logs` AS `ll1`
                                    INNER JOIN (
                                        SELECT 
                                            `leads_id`,
                                            MAX(`lead_log_id`) AS `max_log_id`
                                        FROM `lead_logs`
                                        WHERE 
                                            `users_user_id` = '" . $id . "'
                                            AND `date_updated` BETWEEN '" . $startDateTime . "' AND '" . $endDateTime . "'
                                        GROUP BY `leads_id`
                                    ) AS `latest`
                                        ON `latest`.`max_log_id` = `ll1`.`lead_log_id`
                                ) AS `x`
                                GROUP BY DATE(`x`.`date_updated`)
                                ORDER BY DATE(`x`.`date_updated`) ASC
                            ";

                                switch ($condition) {
                                    case 1: // Weekly Process
                                        $label = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

                                        $warmData = array_fill(0, 7, 0);
                                        $busyData = array_fill(0, 7, 0);
                                        $sleepData = array_fill(0, 7, 0);
                                        $hotData = array_fill(0, 7, 0);
                                        $coldData = array_fill(0, 7, 0);
                                        $futureData = array_fill(0, 7, 0);
                                        $enrolledData = array_fill(0, 7, 0);
                                        $blacklistData = array_fill(0, 7, 0);

                                        $result = Database::search($leadCountQuery);

                                        $engagementData = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $engagementData[$row['day']] = [
                                                'warm' => (int)$row['warm_count'],
                                                'busy' => (int)$row['busy_count'],
                                                'sleep' => (int)$row['sleep_count'],
                                                'hot' => (int)$row['hot_count'],
                                                'cold' => (int)$row['cold_count'],
                                                'future' => (int)$row['future_count'],
                                                'enrolled' => (int)$row['enrolled_count'],
                                                'blacklist' => (int)$row['blacklist_count']
                                            ];
                                        }

                                        $currentDay = new DateTime('monday this week');
                                        for ($i = 0; $i < 7; $i++) {
                                            $dayKey = $currentDay->format('Y-m-d');

                                            $warmData[$i] = $engagementData[$dayKey]['warm'] ?? 0;
                                            $busyData[$i] = $engagementData[$dayKey]['busy'] ?? 0;
                                            $sleepData[$i] = $engagementData[$dayKey]['sleep'] ?? 0;
                                            $hotData[$i] = $engagementData[$dayKey]['hot'] ?? 0;
                                            $coldData[$i] = $engagementData[$dayKey]['cold'] ?? 0;
                                            $futureData[$i] = $engagementData[$dayKey]['future'] ?? 0;
                                            $enrolledData[$i] = $engagementData[$dayKey]['enrolled'] ?? 0;
                                            $blacklistData[$i] = $engagementData[$dayKey]['blacklist'] ?? 0;

                                            $currentDay->modify('+1 day');
                                        }

                                        $status->labels = $label;
                                        $status->warm = $warmData;
                                        $status->busy = $busyData;
                                        $status->sleep = $sleepData;
                                        $status->hot = $hotData;
                                        $status->cold = $coldData;
                                        $status->future = $futureData;
                                        $status->enrolled = $enrolledData;
                                        $status->blacklist = $blacklistData;
                                        break;

                                    case 2: // Monthly Process
                                        $label = [];

                                        $warmData = [];
                                        $busyData = [];
                                        $sleepData = [];
                                        $hotData = [];
                                        $coldData = [];
                                        $futureData = [];
                                        $enrolledData = [];
                                        $blacklistData = [];

                                        $result = Database::search($leadCountQuery);

                                        $engagementData = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $engagementData[$row['day']] = [
                                                'warm' => (int)$row['warm_count'],
                                                'busy' => (int)$row['busy_count'],
                                                'sleep' => (int)$row['sleep_count'],
                                                'hot' => (int)$row['hot_count'],
                                                'cold' => (int)$row['cold_count'],
                                                'future' => (int)$row['future_count'],
                                                'enrolled' => (int)$row['enrolled_count'],
                                                'blacklist' => (int)$row['blacklist_count']
                                            ];
                                        }

                                        $currentDay = clone $startOfMonth;
                                        while ($currentDay <= $endOfMonth) {
                                            $currentDate = $currentDay->format('Y-m-d');

                                            $label[] = $currentDay->format('d');
                                            $warmData[] = $engagementData[$currentDate]['warm'] ?? 0;
                                            $busyData[] = $engagementData[$currentDate]['busy'] ?? 0;
                                            $sleepData[] = $engagementData[$currentDate]['sleep'] ?? 0;
                                            $hotData[] = $engagementData[$currentDate]['hot'] ?? 0;
                                            $coldData[] = $engagementData[$currentDate]['cold'] ?? 0;
                                            $futureData[] = $engagementData[$currentDate]['future'] ?? 0;
                                            $enrolledData[] = $engagementData[$currentDate]['enrolled'] ?? 0;
                                            $blacklistData[] = $engagementData[$currentDate]['blacklist'] ?? 0;

                                            $currentDay->modify('+1 day');
                                        }

                                        $status->labels = $label;
                                        $status->warm = $warmData;
                                        $status->busy = $busyData;
                                        $status->sleep = $sleepData;
                                        $status->hot = $hotData;
                                        $status->cold = $coldData;
                                        $status->future = $futureData;
                                        $status->enrolled = $enrolledData;
                                        $status->blacklist = $blacklistData;
                                        break;

                                    case 3: // Yearly Process
                                        $label = [];

                                        $warmData = array_fill(0, 12, 0);
                                        $busyData = array_fill(0, 12, 0);
                                        $sleepData = array_fill(0, 12, 0);
                                        $hotData = array_fill(0, 12, 0);
                                        $coldData = array_fill(0, 12, 0);
                                        $futureData = array_fill(0, 12, 0);
                                        $enrolledData = array_fill(0, 12, 0);
                                        $blacklistData = array_fill(0, 12, 0);

                                        $result = Database::search($leadCountQuery);

                                        $engagementData = [];
                                        while ($row = $result->fetch_assoc()) {
                                            $month = (int)$row['month'];

                                            if (!isset($engagementData[$month])) {
                                                $engagementData[$month] = [
                                                    'warm' => 0,
                                                    'busy' => 0,
                                                    'sleep' => 0,
                                                    'hot' => 0,
                                                    'cold' => 0,
                                                    'future' => 0,
                                                    'enrolled' => 0,
                                                    'blacklist' => 0
                                                ];
                                            }

                                            $engagementData[$month]['warm'] += (int)$row['warm_count'];
                                            $engagementData[$month]['busy'] += (int)$row['busy_count'];
                                            $engagementData[$month]['sleep'] += (int)$row['sleep_count'];
                                            $engagementData[$month]['hot'] += (int)$row['hot_count'];
                                            $engagementData[$month]['cold'] += (int)$row['cold_count'];
                                            $engagementData[$month]['future'] += (int)$row['future_count'];
                                            $engagementData[$month]['enrolled'] += (int)$row['enrolled_count'];
                                            $engagementData[$month]['blacklist'] += (int)$row['blacklist_count'];
                                        }

                                        for ($month = 1; $month <= 12; $month++) {
                                            $label[] = date('F', mktime(0, 0, 0, $month, 1));

                                            $warmData[$month - 1] = $engagementData[$month]['warm'] ?? 0;
                                            $busyData[$month - 1] = $engagementData[$month]['busy'] ?? 0;
                                            $sleepData[$month - 1] = $engagementData[$month]['sleep'] ?? 0;
                                            $hotData[$month - 1] = $engagementData[$month]['hot'] ?? 0;
                                            $coldData[$month - 1] = $engagementData[$month]['cold'] ?? 0;
                                            $futureData[$month - 1] = $engagementData[$month]['future'] ?? 0;
                                            $enrolledData[$month - 1] = $engagementData[$month]['enrolled'] ?? 0;
                                            $blacklistData[$month - 1] = $engagementData[$month]['blacklist'] ?? 0;
                                        }

                                        $status->labels = $label;
                                        $status->warm = $warmData;
                                        $status->busy = $busyData;
                                        $status->sleep = $sleepData;
                                        $status->hot = $hotData;
                                        $status->cold = $coldData;
                                        $status->future = $futureData;
                                        $status->enrolled = $enrolledData;
                                        $status->blacklist = $blacklistData;
                                        break;
                                }
                            }
                        }
                    }
                }
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo (json_encode($status));
