<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

ob_start();
header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$counselorUserId = (int) $sessionUserObject['user_id'];

// If a Pro/admin user is viewing this page for a specific counselor,
// allow an optional ?counselor_id= override; otherwise scope to self.
if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($_GET['counselor_id'])) {
    $counselorUserId = (int) $_GET['counselor_id'];
}

// Active status id — adjust if your `status` table uses a different id for Active.
define('ACTIVE_STATUS_ID', 1);

try {
    $assignedDateExpr = counselorAssignedDateExpr();
    $assignedDateJoin = counselorAssignedDateJoinSql();

    // ---- Stat cards ----
    // "today_assigned" / "month_assigned" are based on the EFFECTIVE assigned
    // date (see CounselorAssignmentHelper.php) — a student reassigned to this
    // counselor today counts as "today assigned" even if their intake was
    // created weeks ago.
    $statsQuery = "
        SELECT
            COUNT(*) AS total_assigned,
            SUM(CASE WHEN DATE({$assignedDateExpr}) = CURDATE() THEN 1 ELSE 0 END) AS today_assigned,
            SUM(CASE WHEN YEAR({$assignedDateExpr}) = YEAR(CURDATE()) AND MONTH({$assignedDateExpr}) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS month_assigned,
            SUM(CASE WHEN `students_has_intakes`.`status_status_id` = " . ACTIVE_STATUS_ID . " THEN 1 ELSE 0 END) AS active_assigned
        FROM `students_has_intakes`
        {$assignedDateJoin}
        WHERE `students_has_intakes`.`counselor_user_id` = '" . Database::escape($counselorUserId) . "'
    ";
    $statsResult = Database::search($statsQuery);
    $stats = $statsResult->fetch_assoc();

    // Comparison periods for the little +/- change badges
    $prevMonthQuery = "
        SELECT COUNT(*) AS cnt
        FROM `students_has_intakes`
        {$assignedDateJoin}
        WHERE `students_has_intakes`.`counselor_user_id` = '" . Database::escape($counselorUserId) . "'
          AND YEAR({$assignedDateExpr}) = YEAR(CURDATE() - INTERVAL 1 MONTH)
          AND MONTH({$assignedDateExpr}) = MONTH(CURDATE() - INTERVAL 1 MONTH)
    ";
    $prevMonth = Database::search($prevMonthQuery)->fetch_assoc()['cnt'];

    $prevDayQuery = "
        SELECT COUNT(*) AS cnt
        FROM `students_has_intakes`
        {$assignedDateJoin}
        WHERE `students_has_intakes`.`counselor_user_id` = '" . Database::escape($counselorUserId) . "'
          AND DATE({$assignedDateExpr}) = CURDATE() - INTERVAL 1 DAY
    ";
    $prevDay = Database::search($prevDayQuery)->fetch_assoc()['cnt'];

    function pctChange($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? ["value" => "+100%", "type" => "positive"] : ["value" => "0%", "type" => "neutral"];
        }
        $diff = round((($current - $previous) / $previous) * 100);
        if ($diff == 0) {
            return ["value" => "0%", "type" => "neutral"];
        }
        $type = $diff > 0 ? "positive" : "negative";
        $sign = $diff > 0 ? "+" : "";
        return ["value" => $sign . $diff . "%", "type" => $type];
    }

    $monthChange = pctChange((int)$stats['month_assigned'], (int)$prevMonth);
    $todayChange = pctChange((int)$stats['today_assigned'], (int)$prevDay);

    // ---- Trend chart: last 6 months, monthly counts (effective assigned_date) ----
    $trendQuery = "
        SELECT
            DATE_FORMAT({$assignedDateExpr}, '%Y-%m') AS ym,
            COUNT(*) AS cnt
        FROM `students_has_intakes`
        {$assignedDateJoin}
        WHERE `students_has_intakes`.`counselor_user_id` = '" . Database::escape($counselorUserId) . "'
          AND {$assignedDateExpr} >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
        GROUP BY ym
        ORDER BY ym ASC
    ";
    $trendResult = Database::search($trendQuery);
    $trendByMonth = [];
    while ($row = $trendResult->fetch_assoc()) {
        $trendByMonth[$row['ym']] = (int) $row['cnt'];
    }

    // Fill in any months with zero assignments so the chart has a full 6-month axis
    $labels = [];
    $values = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime("-$i months"));
        $labels[] = date('M Y', strtotime("-$i months"));
        $values[] = $trendByMonth[$ym] ?? 0;
    }

    $cards = [
        [
            "title" => "Total Assigned",
            "icon" => "mdi:account-group-outline",
            "value" => (int) $stats['total_assigned'],
            "change" => "",
            "change_type" => "",
            "period" => "all time",
            "href" => "counselor-dashboard?filter=all",
        ],
        [
            "title" => "This Month Assigned",
            "icon" => "mdi:calendar-month-outline",
            "value" => (int) $stats['month_assigned'],
            "change" => $monthChange['value'],
            "change_type" => $monthChange['type'],
            "period" => "vs last month",
            "href" => "counselor-dashboard?filter=month",
            "chart" => $values,
        ],
        [
            "title" => "Today Assigned",
            "icon" => "mdi:calendar-today-outline",
            "value" => (int) $stats['today_assigned'],
            "change" => $todayChange['value'],
            "change_type" => $todayChange['type'],
            "period" => "vs yesterday",
            "href" => "counselor-dashboard?filter=today",
        ],
        [
            "title" => "Active Assigned",
            "icon" => "mdi:account-check-outline",
            "value" => (int) $stats['active_assigned'],
            "change" => "",
            "change_type" => "",
            "period" => "currently active",
            "href" => "counselor-dashboard?filter=all&status=1",
        ],
    ];

    ob_end_clean();
    echo json_encode([
        "success" => true,
        "cards" => $cards,
        "chart" => [
            "labels" => $labels,
            "values" => $values,
        ],
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to load dashboard data"]);
}
function counselorAssignedDateJoinSql($alias = 'students_has_intakes')
{
    return "
        LEFT JOIN (
            SELECT
                `students_has_intakes_students_has_intakes_id`,
                MAX(`date_updated`) AS `last_counselor_change`
            FROM `students_has_intakes_logs`
            WHERE `log` LIKE 'Counselor changed%'
            GROUP BY `students_has_intakes_students_has_intakes_id`
        ) `counselor_change_log`
            ON `counselor_change_log`.`students_has_intakes_students_has_intakes_id` = `{$alias}`.`students_has_intakes_id`
    ";
}

function counselorAssignedDateExpr($alias = 'students_has_intakes')
{
    return "COALESCE(`counselor_change_log`.`last_counselor_change`, `{$alias}`.`addmission_date`)";
}

?>