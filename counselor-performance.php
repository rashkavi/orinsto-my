<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/classes/GetLeadStatusStyle.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['user-id']) && !empty($_GET['user-id']))) {
            header("Location: ./users");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('leads'))) {
                header("Location: ./users");
            } else {
                require_once "./includes/connection.php";
                $id = $_GET['user-id'];
                $user = Database::search("SELECT * FROM `users` 
                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                        INNER JOIN `status` ON `users`.`status_status_id`=`status`.`status_id` 
                                                        WHERE `users`.`user_id`='" . $id . "' AND `user_roles`.`user_role_id` IN (7,8,11,12,21)");
                if ($user->num_rows == 0) {
                    header("Location: ./users");
                } else {
                    $udata = $user->fetch_assoc();
                    $STATUS_HOT = 2;
                    $STATUS_WARM = 3;
                    $STATUS_BUSY = 4;
                    $STATUS_SLEEP = 5;
                    $STATUS_ENROLLED = 6;
                    $STATUS_FUTURE = 7;
                    $STATUS_COLD = 8;

                    $id = (int)$id;

                    $userLeadSummaryRs = Database::search("
                        SELECT
                            COUNT(`lead_id`) AS total_leads,
                    
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_HOT' THEN 1 ELSE 0 END) AS hot_leads,
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_WARM' THEN 1 ELSE 0 END) AS warm_leads,
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_BUSY' THEN 1 ELSE 0 END) AS busy_leads,
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_SLEEP' THEN 1 ELSE 0 END) AS sleep_leads,
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_COLD' THEN 1 ELSE 0 END) AS cold_leads,
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_ENROLLED' THEN 1 ELSE 0 END) AS enrolled_leads,
                            SUM(CASE WHEN `lead_status_lead_status_id` = '$STATUS_FUTURE' THEN 1 ELSE 0 END) AS future_leads,
                    
                            SUM(CASE 
                                WHEN `remind_date` IS NOT NULL
                                 AND DATE(`remind_date`) < CURDATE()
                                 AND `lead_status_lead_status_id` NOT IN ('$STATUS_ENROLLED', '$STATUS_COLD')
                                THEN 1 ELSE 0 
                            END) AS non_follow_up_leads
                    
                        FROM `leads`
                        WHERE `users_user_id` = '$id'
                    ");

                    $cardData = $userLeadSummaryRs->fetch_assoc();

                    if (!$cardData) {
                        $cardData = [
                            'hot_leads' => 0,
                            'warm_leads' => 0,
                            'busy_leads' => 0,
                            'sleep_leads' => 0,
                            'cold_leads' => 0,
                            'enrolled_leads' => 0,
                            'future_leads' => 0,
                            'non_follow_up_leads' => 0,
                        ];
                    }

                    $emptyChart = [0, 0, 0, 0, 0];

                    $hotLeadsChart = $emptyChart;
                    $warmLeadsChart = $emptyChart;
                    $busyLeadsChart = $emptyChart;
                    $sleepLeadsChart = $emptyChart;
                    $coldLeadsChart = $emptyChart;
                    $enrolledLeadsChart = $emptyChart;
                    $futureLeadsChart = $emptyChart;
                    $nonFollowUpLeadsChart = $emptyChart;

                    $performanceCards = [
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
                            'title' => 'Busy Leads',
                            'value' => number_format((int)$cardData['busy_leads']),
                            'icon' => 'mdi:phone-missed-outline',
                            'change' => '',
                            'change_type' => 'negative',
                            'period' => 'Busy calls',
                            'chart' => $busyLeadsChart,
                            'theme' => 'busy'
                        ],
                        [
                            'title' => 'Sleep Leads',
                            'value' => number_format((int)$cardData['sleep_leads']),
                            'icon' => 'mdi:sleep',
                            'change' => '',
                            'change_type' => 'negative',
                            'period' => 'Sleep status',
                            'chart' => $sleepLeadsChart,
                            'theme' => 'sleep'
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
                            'title' => 'Non Follow-Up Leads',
                            'value' => number_format((int)$cardData['non_follow_up_leads']),
                            'icon' => 'mdi:calendar-alert-outline',
                            'change' => '',
                            'change_type' => 'negative',
                            'period' => 'Missed follow-ups',
                            'chart' => $nonFollowUpLeadsChart
                        ],
                    ];

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Counselor Performance - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                        <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
                        <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializePerformance(<?= $id ?>,20),initializeLeads();">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("performance");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("performance");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?= $udata['title'] . "." . $udata['user_fname'] . " " . $udata['user_lname']; ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./Performance.php">Performance</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $udata['title'] . "." . $udata['user_fname'] . " " . $udata['user_lname']; ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 24)) {
                                                        ?>
                                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" type="button" onclick="window.location = './counselor-targets?user-id=<?= $id ?>';">
                                                                    Targets
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>

                                                    </div>
                                                </div>

                                                <div class="container-fluid p-0">
                                                    <div class="row g-3">
                                                        <?php foreach ($performanceCards as $card) {
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

                                                <div class="container p-0 g-0 mt-4">
                                                    <div class="row g-4 d-flex">
                                                        <div class="col-12 col-lg-8 d-flex">
                                                            <div class="d-flex flex-column component-theme p-4 w-100">
                                                                <div class="d-flex flex-row justify-content-end">
                                                                    <div class="col-4 d-flex flex-column">
                                                                        <select id="target-condition" class="form-select custom-select" onchange="loadTargetChart(<?= $id ?>,20)">
                                                                            <option value="1">This Week</option>
                                                                            <option value="2">This Month</option>
                                                                            <option value="3">This Year</option>
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                                                    <canvas id="targetChart" class="w-100 h-auto"></canvas>
                                                                </div>

                                                                <!-- Chart Status Summary -->
                                                                <div class="row g-3 mt-3" id="allStatusSummary">
                                                                    <!-- Hot -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card hot">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:fire"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Hot</div>
                                                                                <div class="lead-performance-value" id="hotTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Warm -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card warm">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:weather-sunny"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Warm</div>
                                                                                <div class="lead-performance-value" id="warmTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Busy -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card busy">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:phone"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Busy</div>
                                                                                <div class="lead-performance-value" id="busyTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Sleep -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card sleep">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:sleep"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Sleep</div>
                                                                                <div class="lead-performance-value" id="sleepTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Future -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card future">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:clock-fast"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Future</div>
                                                                                <div class="lead-performance-value" id="futureTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Cold -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card cold">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:snowflake"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Cold</div>
                                                                                <div class="lead-performance-value" id="coldTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Enrolled -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card enrolled">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:cash-check"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Enrolled</div>
                                                                                <div class="lead-performance-value" id="enrolledTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Blacklist -->
                                                                    <div class="col-6 col-md-3 col-xl">
                                                                        <div class="lead-performance-card blacklist">
                                                                            <div class="lead-performance-icon">
                                                                                <iconify-icon icon="mdi:block-helper"></iconify-icon>
                                                                            </div>
                                                                            <div>
                                                                                <div class="lead-performance-label">Blacklist</div>
                                                                                <div class="lead-performance-value" id="blacklistTotal">0</div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-lg-4 d-flex">
                                                            <div class="d-flex flex-column p-4 component-theme shadow-none">
                                                                <div class="row mb-3 text-center align-items-center">
                                                                    <div class="col-4 text-start">
                                                                        <button class="btn btn-outline-secondary" id="prevMonth">‹ Prev</button>
                                                                    </div>
                                                                    <div class="col-4">
                                                                        <h5 id="monthName" class="fw-bold mb-0"></h5>
                                                                    </div>
                                                                    <div class="col-4 text-end">
                                                                        <button class="btn btn-outline-secondary" id="nextMonth">Next ›</button>
                                                                    </div>
                                                                </div>
                                                                <div class="calendar border rounded shadow-sm p-3 m-0">
                                                                    <div class="calendar-header d-grid text-center fw-bold mb-2"
                                                                        style="grid-template-columns: repeat(7, 1fr);">
                                                                        <div>Sun</div>
                                                                        <div>Mon</div>
                                                                        <div>Tue</div>
                                                                        <div>Wed</div>
                                                                        <div>Thu</div>
                                                                        <div>Fri</div>
                                                                        <div>Sat</div>
                                                                    </div>
                                                                    <div id="calendarDays" class="calendar-body d-grid text-center"
                                                                        style="grid-template-columns: repeat(7, 1fr); gap: 6px;"></div>
                                                                </div>
                                                                <div class="lead-summary-card border mt-3 p-3">
                                                                    <?php
                                                                    $currentMonthStart = date('Y-m-01 00:00:00');
                                                                    $currentMonthEnd = date('Y-m-t 23:59:59');

                                                                    $previousMonthStart = date('Y-m-01 00:00:00', strtotime('first day of last month'));
                                                                    $previousMonthEnd = date('Y-m-t 23:59:59', strtotime('last day of last month'));

                                                                    $query = "
                                                                        SELECT 
                                                                            SUM(CASE 
                                                                                WHEN `date_updated` BETWEEN '$currentMonthStart' AND '$currentMonthEnd' THEN 1 
                                                                                ELSE 0 
                                                                            END) AS current_month_logs,

                                                                            SUM(CASE 
                                                                                WHEN `date_updated` BETWEEN '$previousMonthStart' AND '$previousMonthEnd' THEN 1 
                                                                                ELSE 0 
                                                                            END) AS previous_month_logs,

                                                                            COUNT(DISTINCT CASE 
                                                                                WHEN `date_updated` BETWEEN '$currentMonthStart' AND '$currentMonthEnd' THEN `leads_id` 
                                                                                END) AS current_month_unique_leads,

                                                                            COUNT(DISTINCT CASE 
                                                                                WHEN `date_updated` BETWEEN '$previousMonthStart' AND '$previousMonthEnd' THEN `leads_id` 
                                                                                END) AS previous_month_unique_leads
                                                                        FROM `lead_logs`

                                                                        WHERE `users_user_id` = '$id'
                                                                        AND `lead_status_lead_status_id` IN (13,14,15,16,17)
                                                                    ";

                                                                    $result = Database::search($query);
                                                                    $data = $result->fetch_assoc();

                                                                    $currentCount = (int)($data['current_month_unique_leads'] ?? 0);
                                                                    $previousCount = (int)($data['previous_month_unique_leads'] ?? 0);

                                                                    $percentage = 0;
                                                                    $trend = 'neutral';

                                                                    if ($previousCount > 0) {
                                                                        $percentage = round((($currentCount - $previousCount) / $previousCount) * 100);
                                                                    } elseif ($currentCount > 0) {
                                                                        $percentage = 100;
                                                                    }

                                                                    if ($currentCount > $previousCount) {
                                                                        $trend = 'up';
                                                                    } elseif ($currentCount < $previousCount) {
                                                                        $trend = 'down';
                                                                    }

                                                                    $percentageText = abs($percentage) . '%';

                                                                    if ($trend == 'up') {
                                                                        $trendIcon = 'mdi:trending-up';
                                                                        $trendText = 'Up';
                                                                        $trendStatus = 'Target achievement improved';
                                                                        $trendDesc = 'Current month target achievement is higher than previous month';
                                                                    } elseif ($trend == 'down') {
                                                                        $trendIcon = 'mdi:trending-down';
                                                                        $trendText = 'Down';
                                                                        $trendStatus = 'Target achievement dropped';
                                                                        $trendDesc = 'Current month target achievement is lower than previous month';
                                                                    } else {
                                                                        $trendIcon = 'mdi:minus';
                                                                        $trendText = 'No Change';
                                                                        $trendStatus = 'Target achievement is stable';
                                                                        $trendDesc = 'Current month target achievement is the same as previous month';
                                                                    }
                                                                    ?>

                                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                                        <div>
                                                                            <h6 class="fw-bold mb-1">Lead Target Achieved Summary</h6>
                                                                            <p class="mb-0 text-muted small">
                                                                                Compare current month performance with previous month
                                                                            </p>
                                                                        </div>
                                                                        <div id="trendBadge" class="trend-badge <?= $trend ?>">
                                                                            <span class="iconify" data-icon="<?= $trendIcon ?>"></span>
                                                                            <span id="trendText"><?= $trendText ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="row g-2">
                                                                        <div class="col-6">
                                                                            <div class="summary-mini-card current-card">
                                                                                <span class="summary-label">Current Month</span>
                                                                                <h4 id="currentMonthCount" class="summary-value mb-0"><?= $currentCount ?></h4>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-6">
                                                                            <div class="summary-mini-card previous-card">
                                                                                <span class="summary-label">Previous Month</span>
                                                                                <h4 id="previousMonthCount" class="summary-value mb-0"><?= $previousCount ?></h4>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div class="mt-3 d-flex justify-content-between align-items-center">
                                                                        <div class="d-flex align-items-center gap-2">
                                                                            <span id="trendIconWrap" class="trend-icon <?= $trend ?>">
                                                                                <iconify-icon id="trendIcon" icon="<?= $trendIcon ?>"></iconify-icon>
                                                                            </span>
                                                                            <div>
                                                                                <div class="fw-semibold" id="trendStatus"><?= $trendStatus ?></div>
                                                                                <small class="text-muted" id="trendDescription"><?= $trendDesc ?></small>
                                                                            </div>
                                                                        </div>

                                                                        <div class="text-end">
                                                                            <div class="fw-bold fs-5" id="trendPercentage"><?= $percentageText ?></div>
                                                                            <small class="text-muted">Change</small>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 mt-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <?php
                                                        $query = "
                                                          SELECT 
                                                            leads.*,
                                                            users.*,
                                                        programmes.*,
                                                        lead_source.*,
                                                            institutes.*,
                                                            institute_has_branches.*,
                                                            lead_student_reg_status.*,
                                                        latest_logs.latest_log,
                                                            `leads`.`lead_id` AS 'nid', `leads`.`name` AS 'full_name', 
                                                            `lead_status`.`lead_status` AS 'new_status',`lead_status`.`lead_status_id` AS `sid`,
                                                            `users`.`user_id` AS `uid`
                                                        FROM `leads`
                                                        INNER JOIN `lead_status` 
                                                            ON leads.lead_status_lead_status_id = lead_status.lead_status_id
                                                        LEFT JOIN `users` 
                                                            ON leads.users_user_id = users.user_id
                                                        LEFT JOIN `programmes` ON `leads`.`programmes_programme_id` = `programmes`.`programme_id`
                                                        LEFT JOIN `lead_source` 
                                                            ON leads.lead_source_lead_source_id = lead_source.lead_source_id
                                                        LEFT JOIN `institutes` 
                                                            ON leads.institutes_institute_id = institutes.institute_id
                                                        LEFT JOIN `institute_has_branches` 
                                                            ON institutes.institute_id = institute_has_branches.institutes_institute_id
                                                        LEFT JOIN `lead_student_reg_status` 
                                                            ON leads.lead_student_reg_status_lead_student_reg_status_id = lead_student_reg_status.lead_student_reg_status_id
                                                        LEFT JOIN (
                                                                                                    SELECT `leads_id`, MAX(`date_updated`) AS latest_log
                                                                                                    FROM `lead_logs`
                                                                                                    GROUP BY `leads_id`
                                                                                                ) AS latest_logs ON `leads`.`lead_id` = `latest_logs`.`leads_id`
                                                        WHERE leads.lead_status_lead_status_id != '1'
                                                          AND leads.users_user_id = '" . $id . "'
                                                          GROUP BY `leads`.`lead_id`
                                                          ORDER BY `leads`.`lead_id` DESC
                                                     ";

                                                        $results = Database::search($query);
                                                        ?>
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows ?> Leads</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="darkColor px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text-color form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleCounsilerPerformancePagination(<?= $id ?>,'filter')">
                                                                    </div>
                                                                </div>

                                                                <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                    </button>
                                                                </div>
                                                                <div class="col-12 collapse" id="filterCollapse">
                                                                    <div class="card card-body border-0 p-0">
                                                                        <div class="col-12">
                                                                            <div class="row gy-3 justify-content-end">
                                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="status-selector" onchange="handleCounsilerPerformancePagination(<?= $id ?>,'filter')">
                                                                                        <option selected value="0">- Select Status -</option>
                                                                                        <?php
                                                                                        $status = Database::search("SELECT * FROM `lead_status`");
                                                                                        while ($data = $status->fetch_assoc()) {
                                                                                        ?>
                                                                                            <option value="<?php echo $data["lead_status_id"]; ?>"><?php echo $data["lead_status"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="source-selector" onchange="handleCounsilerPerformancePagination(<?= $id ?>,'filter')">
                                                                                        <option selected value="0">- Select Lead Source -</option>
                                                                                        <?php
                                                                                        $source = Database::search("SELECT * FROM `lead_source`");
                                                                                        while ($data = $source->fetch_assoc()) {
                                                                                        ?>
                                                                                            <option value="<?php echo $data["lead_source_id"]; ?>"><?php echo $data["lead_source"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="" class="pe-2 sub-text-color">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleCounsilerPerformancePagination(<?= $id ?>,'filter')">
                                                                        <option selected value="10">10</option>
                                                                        <option value="25">25</option>
                                                                        <option value="50">50</option>
                                                                        <option value="100">100</option>
                                                                        <option value="250">250</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12">
                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                <div class="overflow-x-auto p-0 m-0 g-0" id="leads-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="leads-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Lead Id</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Full Name</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Contact Number</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>

                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lead Source</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Registration Status</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php
                                                                            $pageno = 1;
                                                                            $results_count = $results->num_rows;
                                                                            $result_per_page = 10;  // Consistent variable name

                                                                            if ($results_count > $result_per_page) {
                                                                                $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                            } else {
                                                                                $number_of_pages = 1;
                                                                            }

                                                                            $page_results = ($pageno - 1) * $result_per_page;
                                                                            $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                            if ($users->num_rows > 0) {
                                                                                $status_classes = [
                                                                                    "1"  => "table-btn-yellow",
                                                                                    "2"  => "table-btn-primary",
                                                                                    "3"  => "table-btn-danger",
                                                                                    "4"  => "table-btn-success",
                                                                                    "5"  => "table-btn-pink",
                                                                                ];
                                                                                while ($data = $users->fetch_assoc()) {
                                                                                    $statusStyle = GetLeadStatusStyle::get($data['sid']);

                                                                            ?>
                                                                                    <tr class="position-relative">
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
                                                                                                    #<?= htmlspecialchars($data["lead_id"]); ?>
                                                                                                </span>

                                                                                            </div>
                                                                                        </td>

                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["full_name"]; ?></span></td>
                                                                                        <td class="py-3 align-middle">
                                                                                            <span class="text-14 main-text"><?php echo $data["contact"]; ?></span>
                                                                                        </td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["programme"]; ?></span></td>
                                                                                        <td class="py-2 align-middle">
                                                                                            <span class="text-14 main-text"><?php echo (new DateTime($data["latest_log"]))->format('Y-m-d h:i A') ?></span>
                                                                                        </td>
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
                                                                                            if (empty($data["lead_student_reg_status"])) {
                                                                                            ?><span class="text-14 main-text">N/A</span><?php
                                                                                                                                    } else {
                                                                                                                                        $sbutton_class = $status_classes[$data["lead_student_reg_status_id"]];
                                                                                                                                        ?>
                                                                                                <label class="text-center table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["lead_student_reg_status"]; ?></label>
                                                                                            <?php
                                                                                                                                    }
                                                                                            ?>
                                                                                        </td>
                                                                                        <td class="py-3 align-middle">

                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text-color cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lead-view?lead-id=<?= $data['nid']; ?>">
                                                                                                            <iconify-icon icon="carbon:view" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                            View</a></li>
                                                                                                    <?php
                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                                                                                                    ?>
                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick='window.location.href = "edit-lead?lead-id=<?= $data["nid"]; ?>"'>
                                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                                Edit</a></li>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>

                                                                                                </ul>
                                                                                            </div>


                                                                                        </td>
                                                                                    </tr>
                                                                                <?php
                                                                                }
                                                                            } else {
                                                                                ?>
                                                                                <tr class="justify-content-center align-middle">
                                                                                    <td colspan="13" class="align-middle justify-content-center">
                                                                                        <div class="d-flex flex-column align-items-center">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                                                                        </div>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tbody>
                                                                    </table>
                                                                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                                                                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                            <div class="row p-4 align-items-center">
                                                                                <!-- Previous Button (col-1) -->
                                                                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleCounsilerPerformancePagination(<?= $id ?>,'previousPage')">
                                                                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                                                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                </button>
                                                                                <!-- Page Numbers (col-10, centered) -->
                                                                                <div class="col-10 custom-pagination d-flex justify-content-center">
                                                                                    <ul class="pagination-list d-flex mb-0">
                                                                                        <?php
                                                                                        $shudderPages1 = false;
                                                                                        $shudderPages2 = false;

                                                                                        function renderPageButton($i, $isActive, $id)
                                                                                        {
                                                                                            $activeClass = $isActive ? 'active' : '';
                                                                                            $action = $isActive ? '' : "onclick=\"handleCounsilerPerformancePagination('{$id}','goToPage', '{$i}')\"";
                                                                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                        }

                                                                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                                renderPageButton($i, $pageno == $i, $id);
                                                                                            } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                                $shudderPages1 = true;
                                                                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                                                            } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                                $shudderPages2 = true;
                                                                                                echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </ul>
                                                                                </div>
                                                                                <!-- Next Button (col-1) -->
                                                                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCounsilerPerformancePagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                                                                    <span class="text-14 main-text fw-semibold">Next</span>
                                                                                    <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                                                                </button>
                                                                            </div>
                                                                        </nav>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
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
                        </div>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                        <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>