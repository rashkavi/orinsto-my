<?php

require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once './includes/Project.php';
require_once './includes/classes/GetLeadStatusStyle.php';
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
    exit();
}

$sessionUserRole = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 1))) {
    header("Location: ./dashboard");
    exit();
}

$STATUS_NEW = 1;
$STATUS_WARM = 3;
$STATUS_BUSY = 4;
$STATUS_SLEEP = 5;
$STATUS_BLACKLIST = 8;
$STATUS_HOT = 2;
$STATUS_FUTURE = 7;
$STATUS_COMPLETED = 6;

$cardData = Database::search("
    SELECT 
        COUNT(*) AS total_leads,

        SUM(CASE WHEN DATE(`date_added`) = CURDATE() THEN 1 ELSE 0 END) AS today_leads,

        SUM(CASE 
            WHEN YEAR(`date_added`) = YEAR(CURDATE()) 
            AND MONTH(`date_added`) = MONTH(CURDATE()) 
            THEN 1 ELSE 0 
        END) AS month_leads,

        SUM(CASE WHEN `lead_status_lead_status_id` IN ($STATUS_HOT) THEN 1 ELSE 0 END) AS hot_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` IN ($STATUS_COMPLETED) THEN 1 ELSE 0 END) AS completed_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` IN ($STATUS_WARM) THEN 1 ELSE 0 END) AS warm_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` = $STATUS_BUSY THEN 1 ELSE 0 END) AS busy_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` = $STATUS_SLEEP THEN 1 ELSE 0 END) AS sleep_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` = $STATUS_FUTURE THEN 1 ELSE 0 END) AS future_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` IN ($STATUS_BLACKLIST) THEN 1 ELSE 0 END) AS blacklist_leads,
        SUM(CASE WHEN `lead_status_lead_status_id` = $STATUS_NEW THEN 1 ELSE 0 END) AS untouched_leads
    FROM `leads`
")->fetch_assoc();

$totalLeads = (int)($cardData['total_leads'] ?? 0);
$completedLeads = (int)($cardData['completed_leads'] ?? 0);
$conversionRate = $totalLeads > 0 ? round(($completedLeads / $totalLeads) * 100) : 0;


$trendResult = Database::search("
    SELECT 
        YEAR(`date_added`) AS y,
        MONTH(`date_added`) AS m,
        DATE_FORMAT(`date_added`, '%b') AS month_name,
        COUNT(*) AS total_leads
    FROM `leads`
    WHERE `date_added` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(`date_added`), MONTH(`date_added`)
    ORDER BY YEAR(`date_added`) ASC, MONTH(`date_added`) ASC
");

$leadTrendMap = [];
while ($row = $trendResult->fetch_assoc()) {
    $key = $row['y'] . '-' . str_pad($row['m'], 2, '0', STR_PAD_LEFT);
    $leadTrendMap[$key] = [
        'label' => $row['month_name'],
        'count' => (int)$row['total_leads']
    ];
}

$trendResult = Database::search("
    SELECT 
        y,
        m,
        month_name,

        SUM(completed_count) AS completed_count,
        SUM(warm_count) AS warm_count

    FROM (

        /* Completed Leads */
        SELECT 
            YEAR(`date_updated`) AS y,
            MONTH(`date_updated`) AS m,
            DATE_FORMAT(`date_updated`, '%b') AS month_name,

            COUNT(DISTINCT `leads_id`) AS completed_count,
            0 AS warm_count

        FROM `lead_logs`

        WHERE `lead_status_lead_status_id` IN ($STATUS_COMPLETED)
        AND `date_updated` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)

        GROUP BY YEAR(`date_updated`), MONTH(`date_updated`)

        UNION ALL

        /* Warm Leads */
        SELECT 
            YEAR(`date_added`) AS y,
            MONTH(`date_added`) AS m,
            DATE_FORMAT(`date_added`, '%b') AS month_name,

            0 AS completed_count,
            COUNT(*) AS warm_count

        FROM `leads`

        WHERE `lead_status_lead_status_id` IN ($STATUS_WARM)
          AND `date_added` >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)

        GROUP BY YEAR(`date_added`), MONTH(`date_added`)

    ) AS trend_data

    GROUP BY y, m, month_name

    ORDER BY y ASC, m ASC
");

$completedTrendMap = [];
$warmTrendMap = [];

while ($row = $trendResult->fetch_assoc()) {
    $key = $row['y'] . '-' . str_pad($row['m'], 2, '0', STR_PAD_LEFT);
    $completedTrendMap[$key] = (int)$row['completed_count'];
    $warmTrendMap[$key] = (int)$row['warm_count'];
}


$chartLabels = [];
$leadTrendData = [];
$warmTrendData = [];
$completedTrendData = [];

for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime("first day of -$i month");
    $key = $date->format("Y-m");
    $chartLabels[] = $date->format("M");
    $leadTrendData[] = $leadTrendMap[$key]['count'] ?? 0;
    $completedTrendData[] = $completedTrendMap[$key] ?? 0;
    $warmTrendData[] = $warmTrendMap[$key] ?? 0;
}

$statusChartLabels = ['Hot', 'Warm', 'Busy', 'Sleep', 'Enrolled', 'Future',  'Cold'];
$statusChartData = [
    (int)($cardData['hot_leads'] ?? 0),
    (int)($cardData['warm_leads'] ?? 0),
    (int)($cardData['busy_leads'] ?? 0),
    (int)($cardData['sleep_leads'] ?? 0),
    (int)($cardData['completed_leads'] ?? 0),
    (int)($cardData['future_leads'] ?? 0),
    (int)($cardData['blacklist_leads'] ?? 0)
];

$sourcePerformance = Database::search("
    SELECT 
        COALESCE(ls.`lead_source`, 'Unknown') AS lead_source,

        COUNT(l.`lead_id`) AS total_leads,

        SUM(CASE 
            WHEN l.`lead_status_lead_status_id` IN ($STATUS_WARM) 
            THEN 1 ELSE 0 
        END) AS warm_leads,

        SUM(CASE 
            WHEN l.`lead_status_lead_status_id` IN ($STATUS_COMPLETED) 
            THEN 1 ELSE 0 
        END) AS enrolled_leads

    FROM `leads` l

    LEFT JOIN `lead_source` ls 
        ON ls.`lead_source_id` = l.`lead_source_lead_source_id`

    GROUP BY ls.`lead_source_id`, ls.`lead_source`

    ORDER BY total_leads DESC
");

$sourceLabels = [];
$sourceLeadData = [];
$sourceWarmData = [];
$sourceEnrolledData = [];

while ($row = $sourcePerformance->fetch_assoc()) {
    $sourceLabels[] = $row['lead_source'];
    $sourceLeadData[] = (int)$row['total_leads'];
    $sourceWarmData[] = (int)$row['warm_leads'];
    $sourceEnrolledData[] = (int)$row['enrolled_leads'];
}
$counselorPerformance = Database::search("
    SELECT 
        u.`user_id`,
        u.`user_fname`,
        u.`user_lname`,
        u.`profile_img`,
        t.`title`,
        COUNT(l.`lead_id`) AS total_leads,
           SUM(CASE WHEN l.`lead_status_lead_status_id` IN ($STATUS_HOT) THEN 1 ELSE 0 END) AS hot_leads,
        SUM(CASE WHEN l.`lead_status_lead_status_id` IN ($STATUS_WARM) THEN 1 ELSE 0 END) AS warm_leads,
        SUM(CASE WHEN l.`lead_status_lead_status_id` IN ($STATUS_COMPLETED) THEN 1 ELSE 0 END) AS completed_leads
    FROM `users` u
    INNER JOIN `title` t ON t.`title_id` = u.`title_title_id`
    LEFT JOIN `leads` l ON l.`users_user_id` = u.`user_id`
    WHERE u.`user_roles_user_role_id` IN (7,8,11,12,21)
    AND u.`status_status_id` = 1
    GROUP BY u.`user_id`
    ORDER BY completed_leads DESC, total_leads DESC
    LIMIT 10
");

$urgentLeads = Database::search("
    SELECT 
        l.`lead_id`,
        l.`name`,
        l.`contact`,
        p.`programme`,
        ls.`lead_status`,
        ls.`lead_status_id`,
        u.`user_fname`,
        u.`user_lname`,
        latest_log.latest_date,
        lead_source.*,
            institutes.*,
            institute_has_branches.*,
            lead_student_reg_status.*
    FROM `leads` l
    LEFT JOIN `programmes` p ON p.`programme_id` = l.`programmes_programme_id`
    LEFT JOIN `lead_status` ls ON ls.`lead_status_id` = l.`lead_status_lead_status_id`
    LEFT JOIN `users` u ON u.`user_id` = l.`users_user_id`
    LEFT JOIN `lead_source` 
            ON l.lead_source_lead_source_id = lead_source.lead_source_id
        LEFT JOIN `institutes` 
            ON l.institutes_institute_id = institutes.institute_id
        LEFT JOIN `institute_has_branches` 
            ON institutes.institute_id = institute_has_branches.institutes_institute_id
        LEFT JOIN `lead_student_reg_status` 
            ON l.lead_student_reg_status_lead_student_reg_status_id = lead_student_reg_status.lead_student_reg_status_id
    LEFT JOIN (
        SELECT `leads_id`, MAX(`date_updated`) AS latest_date
        FROM `lead_logs`
        WHERE `lead_status_lead_status_id` IS NOT NULL
        GROUP BY `leads_id`
    ) latest_log ON latest_log.`leads_id` = l.`lead_id`
    WHERE l.`lead_status_lead_status_id` IN ($STATUS_NEW, $STATUS_BUSY, $STATUS_SLEEP)
      AND (
            latest_log.latest_date IS NULL
            OR latest_log.latest_date <= DATE_SUB(NOW(), INTERVAL 3 DAY)
          )
    GROUP BY l.`lead_id`
    ORDER BY latest_log.latest_date ASC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Marketing Dashboard - Admin Dashboard | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

</head>

<body class="overflow-hidden" onload="initializeMarketingDashboard();">
    <div class="notifications"></div>
    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">
            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include "includes/side-menu.php";
                load_side_menu::load("marketing-dashboard");
                ?>
            </div>

            <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                <div class="col-12 p-0 m-0 g-0">
                    <?php
                    include "./includes/header.php";
                    selectItem::setSelected("marketing-dashboard");
                    ?>
                </div>

                <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                    <div class="row g-4">
                        <?php for ($i = 0; $i < 8; $i++) { ?>
                            <div class="col-12 col-md-6 col-lg-3 d-flex align-items-stretch">
                                <div class="post-card-skeleton skeleton w-100 px-5 py-5"></div>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="row g-4 mt-2 mb-4">
                        <?php for ($i = 0; $i < 2; $i++) { ?>
                            <div class="col-12 col-md-6 d-flex align-items-stretch">
                                <?php include "./includes/components/skeleton-details-card.php"; ?>
                            </div>
                        <?php } ?>
                    </div>
                    <?php include "./includes/components/skeleton-table.php"; ?>
                </div>

                <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                    <div class="row m-0 px-4 g-4">
                        <!-- Header -->
                        <div class="col-12 component-theme p-4">
                            <div class="row g-0">
                                <div class="col-12">
                                    <div class="main-topic lh-lg">Marketing Dashboard</div>
                                    <div class="text-14 sub-text lh-base mt-1">Track lead inflow, conversions, counselor performance, and urgent follow-ups from one place.</div>
                                </div>
                            </div>
                        </div>

                        <?php
                        require_once "./includes/classes/MarketingDashboardSummary.php";
                        $dashboardCards = MarketingDashboardSummary::getDashboardSummary();
                        ?>

                        <div class="container-fluid p-0 mt-4">
                            <div class="row g-3">
                                <?php foreach ($dashboardCards as $card) {
                                ?>
                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                        <div class="stats-mini-card <?= htmlspecialchars($card['theme'] ?? 'default') ?>">
                                            <div class="stats-mini-top">
                                                <div class="stats-mini-left">
                                                    <div class="stats-mini-icon">
                                                        <iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon>
                                                    </div>
                                                    <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                </div>

                                                <button type="button" class="stats-mini-menu">
                                                    <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                                </button>
                                            </div>

                                            <div class="stats-mini-body">
                                                <div class="stats-mini-content">
                                                    <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?></div>

                                                    <div class="stats-mini-meta">
                                                        <?php if ($card['change'] !== '') { ?>
                                                            <span class="stats-mini-change <?= htmlspecialchars($card['change_type']) ?>">
                                                                <?= htmlspecialchars($card['change']) ?>
                                                            </span>
                                                        <?php } ?>
                                                        <span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span>
                                                    </div>
                                                </div>
                                                <?php if (!empty($card['chart'])) { ?>
                                                    <div class="stats-mini-chart" data-theme='<?= htmlspecialchars($card['theme'] ?? 'default') ?>' data-chart='<?= json_encode($card['chart']) ?>'></div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <!-- Charts -->
                        <div class="col-12 p-0">
                            <div class="row g-4 d-flex">

                                <div class="col-12 col-xl-6 d-flex align-items-stretch">
                                    <div class="component-theme p-3 w-100">
                                        <div class="mb-3">
                                            <h4 class="sub-topic mb-1">Warm Lead VS Enrolled Leads Trend</h4>
                                            <div class="section-subtext">Monthly comparison of total leads received and successfully completed registrations.</div>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="leadTrendChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-6 d-flex align-items-stretch">
                                    <div class="component-theme p-3 w-100">
                                        <div class="mb-3">
                                            <h4 class="sub-topic mb-1">Lead Status Breakdown</h4>
                                            <div class="section-subtext">Overall distribution of leads by current status groups.</div>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="statusBreakdownChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <style>
                                    .chart-scroll-x {
                                        width: 100%;
                                        overflow-x: auto;
                                        overflow-y: hidden;
                                        padding-bottom: 8px;
                                    }

                                    .chart-wide {
                                        min-width: 1100px;
                                        height: 360px;
                                    }

                                    .chart-wide canvas {
                                        width: 100% !important;
                                        height: 100% !important;
                                    }
                                </style>
                                <div class="col-12 d-flex align-items-stretch">
                                    <div class="component-theme p-3 w-100">

                                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                                            <div>
                                                <h4 class="sub-topic mb-1">Lead Source Performance</h4>
                                                <div class="section-subtext">
                                                    Compare total, warm and enrolled leads by source.
                                                </div>
                                            </div>

                                            <div class="d-flex flex-wrap gap-2 align-items-end">
                                                <div class="col-12">
                                                    <div class="row g-2 w-100">

                                                        <div class="col-12 col-md-4">
                                                            <input type="text"
                                                                id="sourceFromDate"
                                                                class="form-control custom-input date-switch-input"
                                                                placeholder="From Date"
                                                                onfocus="this.type='date'"
                                                                onblur="if(!this.value)this.type='text'">
                                                        </div>

                                                        <div class="col-12 col-md-4">
                                                            <input type="text"
                                                                id="sourceToDate"
                                                                class="form-control custom-input date-switch-input"
                                                                placeholder="To Date"
                                                                onfocus="this.type='date'"
                                                                onblur="if(!this.value)this.type='text'">
                                                        </div>

                                                        <div class="col-12 col-md-4">
                                                            <button type="button"
                                                                class="custom-btn w-100 h-48"
                                                                onclick="filterSourcePerformanceChart()">

                                                                <iconify-icon icon="mi:filter"
                                                                    width="26"
                                                                    height="26"
                                                                    class="me-1"></iconify-icon>

                                                            
                                                            </button>
                                                        </div>

                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                        <div class="chart-scroll-x">
                                            <div class="chart-wide">
                                                <canvas id="sourcePerformanceChart"></canvas>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 p-0">
                            <div class="row component-theme p-4 m-0 g-0 align-content-center">
                                <div class="mb-3">
                                    <h4 class="sub-topic mb-1">Counselor Performance</h4>
                                    <div class="section-subtext">Top counselors ranked by completed registrations and handled leads.</div>
                                </div>
                                <div class="table-responsive-wrapper">
                                    <div class="table-responsive" id="users-table-container">
                                        <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                            <thead>
                                                <tr class="br-t-5 align-content-center">
                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                                <span class="text-14 main-text fw-medium mx-2">Counseler</span>
                                                            </div>
                                                        </div>
                                                    </th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Total Leads</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Hot Leads</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Warm Leads</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Enrolled Leads</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Conversion %</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="user-table-data">

                                                <?php if ($counselorPerformance->num_rows > 0) { ?>
                                                    <?php while ($row = $counselorPerformance->fetch_assoc()) {
                                                        $cTotal = (int)$row['warm_leads'];
                                                        $cCompleted = (int)$row['completed_leads'];
                                                        $cRate = $cTotal > 0 ? round(($cCompleted / $cTotal) * 100) : 0;
                                                    ?>
                                                        <tr>

                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                        <?php
                                                                        if (!empty($row["profile_img"]) && file_exists('./assets/imgs/users/' . $row["profile_img"])) {
                                                                        ?>
                                                                            <img src="./assets/imgs/users/<?php echo $row["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                        <?php
                                                                        } else {
                                                                            $firstNameInitial = strtoupper(substr($row["user_fname"], 0, 1));
                                                                            $lastNameInitial = strtoupper(substr($row["user_lname"], 0, 1));
                                                                        ?>
                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                        <span class="text-14 main-text mx-2"><?php echo $row["title"] . "." . $row["user_fname"] . " " . $row["user_lname"]; ?></span>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="py-3 text-center"><span class="text-14 main-text"><?= (int)$row['total_leads'] ?></span></td>
                                                            <td class="py-3 text-center"><span class="text-14 main-text"><?= (int)$row['hot_leads'] ?></span></td>
                                                            <td class="py-3 text-center"><span class="text-14 main-text"><?= (int)$row['warm_leads'] ?></span></td>
                                                            <td class="py-3 text-center"><span class="badge rounded-pill bg-success-subtle text-success px-3 py-2"><?= $cCompleted ?></span></td>
                                                            <td class="py-3 text-center" style="min-width:180px;">
                                                                <div class="d-flex flex-column gap-1">
                                                                    <div class="fw-semibold"><?= $cRate ?>%</div>
                                                                    <div class="progress" style="height: 8px;">
                                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min($cRate, 100) ?>%"></div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td class="py-3 text-center h-100">
                                                                <a href="counselor-performance?user-id=<?= $row['user_id'] ?>" class="table-btn table-btn-dark-theme active border-0 text-decoration-none">View</a>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                                        </td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Urgent Follow-up Table -->
                        <div class="col-12 component-theme p-4">
                            <div class="mb-3">
                                <h4 class="sub-topic mb-1">Urgent Follow-up Leads</h4>
                                <div class="section-subtext">Leads in new, busy, or sleep stages with no recent activity in the last 3 days.</div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="table bl-solid m-0 p-0 g-0 align-middle">
                                    <thead>
                                        <tr class="br-t-5">
                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center">
                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="text-14 main-text fw-medium mx-2">Lead ID</span>
                                                    </div>
                                                </div>
                                            </th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Full Name</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Contact</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Assigned Counselor</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Lead Source</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Last Activity</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($urgentLeads->num_rows > 0) {
                                            $status_classes = [
                                                "1"  => "table-btn-yellow",
                                                "2"  => "table-btn-primary",
                                                "3"  => "table-btn-danger",
                                                "4"  => "table-btn-success",
                                                "5"  => "table-btn-pink",
                                            ];
                                            while ($lead = $urgentLeads->fetch_assoc()) {
                                                $statusStyle = GetLeadStatusStyle::get($lead['lead_status_id']);
                                        ?>
                                                <tr>
                                                    <td class="align-middle lead-id-td">
                                                        <div class="lead-id-card position-relative d-flex align-items-center">
                                                            <span class="mini-left-ribbon"
                                                                style="background: <?= htmlspecialchars($statusStyle['ribbon_bg']) ?>;">
                                                                <?= htmlspecialchars($statusStyle['ribbon_text']) ?>
                                                            </span>
                                                            <div class="lead-status-icon-wrap <?= htmlspecialchars($statusStyle['animation_class']) ?>"
                                                                style="background: <?= htmlspecialchars($statusStyle['label_bg']) ?>; color: <?= htmlspecialchars($statusStyle['label_text']) ?>;">
                                                                <iconify-icon icon="<?= htmlspecialchars($statusStyle['icon']) ?>" width="18" height="18"></iconify-icon>
                                                            </div>

                                                            <span class="text-14 main-text lead-id-text">
                                                                #<?= htmlspecialchars($lead["lead_id"]); ?>
                                                            </span>

                                                        </div>
                                                    </td>

                                                    <td class="py-3"><span class="text-14 main-text"><?= $lead['name'] ?></span></td>
                                                    <td class="py-3"><span class="text-14 main-text"><?= $lead['contact'] ?></span></td>
                                                    <td class="py-3"><span class="text-14 main-text"><?= !empty($lead['programme']) ? $lead['programme'] : '-' ?></span></td>
                                                    <td class="py-3"><span class="text-14 main-text"><?= $lead['user_fname'] . " " . $lead['user_lname'] ?></span></td>
                                                    <td class="py-2 align-middle">
                                                        <?php
                                                        if (!empty($data['lead_source_id'])) {

                                                        ?>
                                                            <button class="table-btn lead-source w-100" data-id="<?= $data["lead_source_id"]; ?>"> <?php echo $data["lead_source"]; ?> <?= $data['institute'] != null ? " - " . $data['institute'] : "";  ?> <?= $data["institute_has_branch"] != null ? " " . $data["institute_has_branch"] : ""; ?></button>
                                                        <?php
                                                        }
                                                        ?>

                                                    </td>
                                                    <td class="py-2 align-middle">
                                                        <?php
                                                        if (empty($lead["lead_student_reg_status"])) {
                                                        ?><span class="text-14 main-text">N/A</span><?php
                                                                                                } else {
                                                                                                    $sbutton_class = $status_classes[$lead["lead_student_reg_status_id"]];
                                                                                                    ?>
                                                            <label class="text-center table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $lead["lead_student_reg_status"]; ?></label>
                                                        <?php
                                                                                                }
                                                        ?>
                                                    </td>
                                                    <td class="py-3">
                                                        <span class="text-14 main-text">
                                                            <?= !empty($lead['latest_date']) ? (new DateTime($lead['latest_date']))->format('Y-m-d h:i A') : 'No logs' ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-3 text-center">
                                                        <a href="lead-view?lead-id=<?= $lead['lead_id'] ?>" class="table-btn table-btn-dark-theme active border-0 text-decoration-none">View</a>
                                                    </td>
                                                </tr>
                                            <?php
                                            }
                                        } else { ?>
                                            <tr>
                                                <td colspan="8" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <div class="flex-grow-1"></div>
                    <footer>
                        <?php include './includes/footer.php' ?>
                    </footer>
                </div>
            </div>
        </div>
    </div>
    <script>
        const leadTrendLabels = <?= json_encode($chartLabels) ?>;
        const leadTrendData = <?= json_encode($leadTrendData) ?>;
        const completedTrendData = <?= json_encode($completedTrendData) ?>;
        const warmTrendData = <?= json_encode($warmTrendData) ?>;

        const statusLabels = <?= json_encode($statusChartLabels) ?>;
        const statusData = <?= json_encode($statusChartData) ?>;

        const sourceLabels = <?= json_encode($sourceLabels) ?>;
        const sourceLeadData = <?= json_encode($sourceLeadData) ?>;
        const sourceWarmData = <?= json_encode($sourceWarmData) ?>;
        const sourceEnrolledData = <?= json_encode($sourceEnrolledData) ?>;
    </script>
    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>


</body>

</html>