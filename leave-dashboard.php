<?php

require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once './includes/Project.php';
require_once './includes/classes/LeaveDashboardSummary.php';
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
    exit();
}
$sessionUserRole = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 25))) {
    header("Location: ./dashboard");
    exit();
}

$STATUS_PENDING = 1;
$STATUS_APPROVED = 2;
$STATUS_REJECTED = 3;
$STATUS_CANCELLED = 4;

$currentYear = date("Y");

$cardData = Database::search("
    SELECT 
        COUNT(*) AS total_requests,

        SUM(CASE WHEN lr.leave_request_status_leave_request_status_id = $STATUS_PENDING THEN 1 ELSE 0 END) AS pending_requests,
        SUM(CASE WHEN lr.leave_request_status_leave_request_status_id = $STATUS_APPROVED THEN 1 ELSE 0 END) AS approved_requests,
        SUM(CASE WHEN lr.leave_request_status_leave_request_status_id = $STATUS_REJECTED THEN 1 ELSE 0 END) AS rejected_requests,

        SUM(CASE WHEN DATE(lr.requested_at) = CURDATE() THEN 1 ELSE 0 END) AS today_requests,

        SUM(CASE 
            WHEN YEAR(lr.requested_at) = YEAR(CURDATE()) 
            AND MONTH(lr.requested_at) = MONTH(CURDATE()) 
            THEN 1 ELSE 0 
        END) AS month_requests,

        SUM(CASE 
            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_APPROVED
            AND CURDATE() BETWEEN lr.from_date AND lr.to_date
            THEN 1 ELSE 0 
        END) AS on_leave_today,

        SUM(CASE 
            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_APPROVED
            THEN lr.total_days ELSE 0 
        END) AS approved_days,

        SUM(CASE 
            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_PENDING
            THEN lr.total_days ELSE 0 
        END) AS pending_days

    FROM leave_requests AS lr
")->fetch_assoc();

$totalRequests = (int)($cardData['total_requests'] ?? 0);
$pendingRequests = (int)($cardData['pending_requests'] ?? 0);
$approvedRequests = (int)($cardData['approved_requests'] ?? 0);
$rejectedRequests = (int)($cardData['rejected_requests'] ?? 0);
$todayRequests = (int)($cardData['today_requests'] ?? 0);
$monthRequests = (int)($cardData['month_requests'] ?? 0);
$onLeaveToday = (int)($cardData['on_leave_today'] ?? 0);
$approvedDays = (float)($cardData['approved_days'] ?? 0);
$pendingDays = (float)($cardData['pending_days'] ?? 0);

$approvalRate = $totalRequests > 0 ? round(($approvedRequests / $totalRequests) * 100) : 0;

/* Monthly Leave Request Trend */
$trendResult = Database::search("
    SELECT 
        YEAR(lr.requested_at) AS y,
        MONTH(lr.requested_at) AS m,
        DATE_FORMAT(lr.requested_at, '%b') AS month_name,
        COUNT(*) AS total_requests,
        SUM(CASE WHEN lr.leave_request_status_leave_request_status_id = $STATUS_APPROVED THEN 1 ELSE 0 END) AS approved_requests
    FROM leave_requests AS lr
    WHERE lr.requested_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY YEAR(lr.requested_at), MONTH(lr.requested_at)
    ORDER BY YEAR(lr.requested_at), MONTH(lr.requested_at)
");

$leaveTrendMap = [];

while ($row = $trendResult->fetch_assoc()) {
    $key = $row['y'] . '-' . str_pad($row['m'], 2, '0', STR_PAD_LEFT);

    $leaveTrendMap[$key] = [
        'label' => $row['month_name'],
        'total' => (int)$row['total_requests'],
        'approved' => (int)$row['approved_requests']
    ];
}

$chartLabels = [];
$requestTrendData = [];
$approvedTrendData = [];

for ($i = 5; $i >= 0; $i--) {
    $date = new DateTime("first day of -$i month");
    $key = $date->format("Y-m");

    $chartLabels[] = $date->format("M");
    $requestTrendData[] = $leaveTrendMap[$key]['total'] ?? 0;
    $approvedTrendData[] = $leaveTrendMap[$key]['approved'] ?? 0;
}

/* Leave Type Breakdown */
$typeBreakdown = Database::search("
    SELECT 
        lt.leave_type,
        COUNT(lr.leave_request_id) AS request_count
    FROM leave_types AS lt
    LEFT JOIN leave_requests AS lr 
        ON lr.leave_types_leave_type_id = lt.leave_type_id
        AND YEAR(lr.requested_at) = '$currentYear'
    WHERE lt.status_status_id = 1
    GROUP BY lt.leave_type_id
    ORDER BY request_count DESC
");

$typeLabels = [];
$typeData = [];

while ($row = $typeBreakdown->fetch_assoc()) {
    $typeLabels[] = $row['leave_type'];
    $typeData[] = (int)$row['request_count'];
}

/* Status Breakdown */
$statusLabels = ['Pending', 'Approved', 'Rejected', 'Cancelled'];
$statusData = [
    $pendingRequests,
    $approvedRequests,
    $rejectedRequests,
    (int)Database::search("
        SELECT COUNT(*) AS count 
        FROM leave_requests 
        WHERE leave_request_status_leave_request_status_id = $STATUS_CANCELLED
    ")->fetch_assoc()['count']
];

/* Users Leave Summary - calculated without user_leave_balances */
$userLeaveSummary = Database::search("
    SELECT 
        u.user_id,
        u.user_fname,
        u.user_lname,
        u.profile_img,
        t.title,

        COUNT(lr.leave_request_id) AS total_requests,

        SUM(CASE 
            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_PENDING 
            THEN lr.total_days ELSE 0 
        END) AS pending_days,

        SUM(CASE 
            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_APPROVED 
            THEN lr.total_days ELSE 0 
        END) AS approved_days,

        SUM(CASE 
            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_REJECTED 
            THEN 1 ELSE 0 
        END) AS rejected_requests

    FROM users AS u

    INNER JOIN title AS t 
        ON t.title_id = u.title_title_id

    INNER JOIN leave_requests AS lr 
        ON lr.users_user_id = u.user_id
        AND YEAR(lr.from_date) = '$currentYear'

    WHERE u.status_status_id = 1
    AND u.user_roles_user_role_id != 1
    
    GROUP BY u.user_id
    ORDER BY approved_days DESC, total_requests DESC
    LIMIT 10
");

/* Recent Pending Requests */
$pendingLeaveRequests = Database::search("
    SELECT 
        lr.leave_request_id,
        lr.from_date,
        lr.to_date,
        lr.total_days,
        lr.reason,
        lr.requested_at,

        lt.leave_type,
        ldt.leave_day_type,

        u.user_id,
        u.user_fname,
        u.user_lname,
        u.profile_img,

        t.title

    FROM leave_requests AS lr

    INNER JOIN leave_types AS lt 
        ON lt.leave_type_id = lr.leave_types_leave_type_id

    INNER JOIN leave_day_type AS ldt 
        ON ldt.leave_day_type_id = lr.leave_day_type_leave_day_type_id

    INNER JOIN users AS u 
        ON u.user_id = lr.users_user_id

    INNER JOIN title AS t 
        ON t.title_id = u.title_title_id

    WHERE lr.leave_request_status_leave_request_status_id = $STATUS_PENDING

    ORDER BY lr.requested_at DESC
    LIMIT 10
");

/* Leave Type Usage Summary */
$users = Database::search("
    SELECT 
        u.user_id,
        u.user_fname,
        u.user_lname,
        u.profile_img,
        t.title
        FROM users AS u
        INNER JOIN title AS t 
            ON t.title_id = u.title_title_id
        WHERE u.status_status_id = 1
        AND u.user_roles_user_role_id != 1
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">

    <title>Leave Dashboard - Admin Dashboard | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
</head>

<body class="overflow-hidden" onload="initializeLeaveDashboard();">
    <div class="notifications"></div>

    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
        <div id="appContainer" class="row p-0">

            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                <?php
                include "includes/side-menu.php";
                load_side_menu::load("leave-dashboard");
                ?>
            </div>

            <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                <div class="col-12 p-0 m-0 g-0">
                    <?php
                    include "./includes/header.php";
                    selectItem::setSelected("leave-dashboard");
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

                        <div class="col-12 component-theme p-4">
                            <div class="main-topic lh-lg">Leave Dashboard</div>
                            <div class="text-14 sub-text lh-base mt-1">
                                Track staff leave requests, approvals, pending leaves, leave usage, and upcoming absences from one place.
                            </div>
                        </div>

                        <div class="container-fluid p-0 mt-4">
                            <div class="row g-3">
                                <?php
                                $dashboardCards = LeaveDashboardSummary::getDashboardSummary();
                                foreach ($dashboardCards as $card) { ?>
                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                        <div class="stats-mini-card">
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
                                                    <div class="stats-mini-chart" data-chart='<?= json_encode($card['chart']) ?>'></div>
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>

                        <div class="col-12 p-0">
                            <div class="row g-4 d-flex">

                                <div class="col-12 col-xl-6 d-flex align-items-stretch">
                                    <div class="component-theme p-3 w-100">
                                        <div class="mb-3">
                                            <h4 class="sub-topic mb-1">Leave Requests vs Approved Trend</h4>
                                            <div class="section-subtext">Monthly comparison of submitted leave requests and approved requests.</div>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="leaveTrendChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-xl-6 d-flex align-items-stretch">
                                    <div class="component-theme p-3 w-100">
                                        <div class="mb-3">
                                            <h4 class="sub-topic mb-1">Leave Status Breakdown</h4>
                                            <div class="section-subtext">Current leave request distribution by approval status.</div>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="leaveStatusChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 d-flex align-items-stretch">
                                    <div class="component-theme p-3 w-100">
                                        <div class="mb-3">
                                            <h4 class="sub-topic mb-1">Leave Type Usage</h4>
                                            <div class="section-subtext">Leave request count by leave type for the current year.</div>
                                        </div>
                                        <div class="chart-container">
                                            <canvas id="leaveTypeChart"></canvas>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <div class="col-12 p-0">
                            <div class="row component-theme p-4 m-0 g-0 align-content-center">
                                <div class="mb-3">
                                    <h4 class="sub-topic mb-1">Top Leave Usage by Users</h4>
                                    <div class="section-subtext">Calculated using approved and pending leave requests. No balance table is required.</div>
                                </div>

                                <div class="table-responsive-wrapper">
                                    <div class="table-responsive">
                                        <table class="table bl-solid m-0 p-0 g-0">
                                            <thead>
                                                <tr class="br-t-5 align-content-center">
                                                    <th class="px-4 py-3 bg-table-header main-text fw-medium w-25">Employee</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Requests</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Approved Days</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Pending Days</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Rejected</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                <?php if ($userLeaveSummary->num_rows > 0) { ?>
                                                    <?php while ($row = $userLeaveSummary->fetch_assoc()) { ?>
                                                        <tr>
                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                        <?php if (!empty($row["profile_img"]) && file_exists('./assets/imgs/users/' . $row["profile_img"])) { ?>
                                                                            <img src="./assets/imgs/users/<?= $row["profile_img"] ?>" class="hwr-40" alt="User image">
                                                                        <?php } else {
                                                                            $firstNameInitial = strtoupper(substr($row["user_fname"], 0, 1));
                                                                            $lastNameInitial = strtoupper(substr($row["user_lname"], 0, 1));
                                                                        ?>
                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                <?= $firstNameInitial . $lastNameInitial ?>
                                                                            </div>
                                                                        <?php } ?>

                                                                        <span class="text-14 main-text mx-2">
                                                                            <?= $row["title"] . "." . $row["user_fname"] . " " . $row["user_lname"] ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td class="py-3 text-center">
                                                                <span class="text-14 main-text"><?= (int)$row['total_requests'] ?></span>
                                                            </td>

                                                            <td class="py-3 text-center">
                                                                <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2">
                                                                    <?= (float)$row['approved_days'] ?>
                                                                </span>
                                                            </td>

                                                            <td class="py-3 text-center">
                                                                <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">
                                                                    <?= (float)$row['pending_days'] ?>
                                                                </span>
                                                            </td>

                                                            <td class="py-3 text-center">
                                                                <span class="text-14 main-text"><?= (int)$row['rejected_requests'] ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php } ?>
                                                <?php } else { ?>
                                                    <tr>
                                                        <td colspan="5" class="text-center py-5">
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



                        <div class="col-12 component-theme p-4">
                            <div class="mb-3">
                                <h4 class="sub-topic mb-1">Pending Leave Requests</h4>
                                <div class="section-subtext">Newest leave requests waiting for approval.</div>
                            </div>

                            <div class="table-responsive">
                                <table class="table bl-solid m-0 p-0 g-0 align-middle">
                                    <thead>
                                        <tr class="br-t-5">
                                            <th class="px-4 py-3 bg-table-header main-text fw-medium w-25">Employee</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Leave Type</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Day Type</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Date Range</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Days</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium">Requested At</th>
                                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        <?php if ($pendingLeaveRequests->num_rows > 0) { ?>
                                            <?php while ($leave = $pendingLeaveRequests->fetch_assoc()) { ?>
                                                <tr>
                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($leave["profile_img"]) && file_exists('./assets/imgs/users/' . $leave["profile_img"])) { ?>
                                                                <img src="./assets/imgs/users/<?= $leave["profile_img"] ?>" class="hwr-40" alt="User image">
                                                            <?php } else {
                                                                $firstNameInitial = strtoupper(substr($leave["user_fname"], 0, 1));
                                                                $lastNameInitial = strtoupper(substr($leave["user_lname"], 0, 1));
                                                            ?>
                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                    <?= $firstNameInitial . $lastNameInitial ?>
                                                                </div>
                                                            <?php } ?>

                                                            <span class="text-14 main-text mx-2">
                                                                <?= $leave["title"] . "." . $leave["user_fname"] . " " . $leave["user_lname"] ?>
                                                            </span>
                                                        </div>
                                                    </td>

                                                    <td class="py-3">
                                                        <span class="text-14 main-text"><?= $leave['leave_type'] ?></span>
                                                    </td>

                                                    <td class="py-3">
                                                        <span class="text-14 main-text"><?= $leave['leave_day_type'] ?></span>
                                                    </td>

                                                    <td class="py-3">
                                                        <span class="text-14 main-text">
                                                            <?= $leave['from_date'] ?> to <?= $leave['to_date'] ?>
                                                        </span>
                                                    </td>

                                                    <td class="py-3 text-center">
                                                        <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2">
                                                            <?= $leave['total_days'] ?>
                                                        </span>
                                                    </td>

                                                    <td class="py-3">
                                                        <span class="text-14 main-text">
                                                            <?= (new DateTime($leave['requested_at']))->format('Y-m-d h:i A') ?>
                                                        </span>
                                                    </td>

                                                    <td class="py-3 text-center">
                                                        <a href="leave-request-view?leave-id=<?= $leave['leave_request_id'] ?>" class="table-btn table-btn-dark-theme active border-0 text-decoration-none">
                                                            View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr>
                                                <td colspan="7" class="text-center py-5">
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
                        <div class="col-12 component-theme p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">

                                <!-- LEFT SIDE -->
                                <div class="col-6">
                                    <h4 class="sub-topic mb-1">Leave Type Balance Overview</h4>
                                    <div class="section-subtext">
                                        This is calculated from leave type allocation and approved/pending leave days.
                                    </div>
                                </div>

                                <!-- RIGHT SIDE (SEARCH) -->
                                <div class="col-6 position-relative">
                                    <input
                                        type="text"
                                        id="userSearch"
                                        class="form-control custom-input"
                                        placeholder="Search user..."
                                        onkeyup="searchUser(this.value)">

                                    <div id="userSuggestions"
                                        class="list-group position-absolute w-100"
                                        style="z-index: 999;"></div>
                                </div>
                            </div>

                            <div class="table-responsive" id="leave-usage-table">
                                <div class="col-12 px-4 py-3 component-theme">
                                    <div class="row align-items-center justify-content-center">
                                        <img src="./assets/imgs/common/select-user.png" class="no-data-icon" alt="No data" />
                                        <div class="text-center mt-3">
                                            <h6 class="main-text fw-medium">Select a user to view leave analytics</h6>
                                            <p class="text-muted text-14 mb-0">
                                                Search and choose a user from the top-right search box to see their leave balance, usage, and pending requests.
                                            </p>
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

    <script>
        const leaveTrendLabels = <?= json_encode($chartLabels) ?>;
        const leaveRequestTrendData = <?= json_encode($requestTrendData) ?>;
        const leaveApprovedTrendData = <?= json_encode($approvedTrendData) ?>;

        const leaveStatusLabels = <?= json_encode($statusLabels) ?>;
        const leaveStatusData = <?= json_encode($statusData) ?>;

        const leaveTypeLabels = <?= json_encode($typeLabels) ?>;
        const leaveTypeData = <?= json_encode($typeData) ?>;
    </script>

    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

    <script id="v1r2js">
        const users = [
            <?php while ($u = $users->fetch_assoc()) { ?> {
                    id: "<?= $u['user_id'] ?>",
                    name: "<?= addslashes($u['title'] . '.' . $u['user_fname'] . ' ' . $u['user_lname']) ?>",
                    img: "assets/imgs/users/<?= $u['profile_img'] ?>"
                },
            <?php } ?>
        ];
    </script>


</body>

</html>