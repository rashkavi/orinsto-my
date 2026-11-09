<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
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

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 24))) {
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

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Counselor Tragets - Admin Dashboard | <?= Project::getBaseName() ?></title>

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

                    <body class="overflow-hidden" onload="initializeCounsellorTargets();">
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
                                                                <span class="main-topic lh-lg">Counselor Targets</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./Performance.php">Performance</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./counselor-performance?user-id=<?= $id; ?>"><?= $udata['title'] . "." . $udata['user_fname'] . " " . $udata['user_lname']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="#">Counselor Targets</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="modal" data-bs-target="#addTarget">
                                                                Add Target
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <?php
                                                        $query = "
                                                     SELECT 
                                                         lt.`lead_target_id`,
                                                         lt.`target`,
                                                         lt.`start_date`,
                                                         lt.`end_date`,
                                                         lt.`date_added`,
                                                         lt.`users_user_id`,
                                                         lt.`counselor_user_id`,
                                                         lt.`lead_target_period_lead_target_period_id`,
                                                         lt.`lead_target_type_lead_target_type_id`,
                                                         lt.`intakes_intake_id`,
                                                 
                                                         u.`user_fname`,
                                                         u.`user_lname`,
                                                         ltp.`lead_target_period`,
                                                         ltp.`lead_target_period_id`,
                                                         ltt.`lead_target_type`,

                                                         i.`intake`,
                                                 
                                                         COALESCE(a.`achieved_count`, 0) AS `achieved_count`,
                                                         GREATEST(lt.`target` - COALESCE(a.`achieved_count`, 0), 0) AS `pending_count`,
                                                         CASE 
                                                             WHEN lt.`target` > 0 
                                                             THEN ROUND((COALESCE(a.`achieved_count`, 0) / lt.`target`) * 100, 0)
                                                             ELSE 0
                                                         END AS `achieved_percentage`
                                                 
                                                     FROM `lead_targets` lt
                                                     INNER JOIN `users` u 
                                                         ON u.`user_id` = lt.`counselor_user_id`
                                                     INNER JOIN `lead_target_period` ltp
                                                         ON ltp.`lead_target_period_id` = lt.`lead_target_period_lead_target_period_id`
                                                     INNER JOIN `lead_target_type` ltt
                                                         ON ltt.`lead_target_type_id` = lt.`lead_target_type_lead_target_type_id`
                                                     LEFT JOIN `intakes` i
                                                         ON i.`intake_id` = lt.`intakes_intake_id`
                                                 
                                                     LEFT JOIN (
                                                         SELECT 
                                                             lt2.`lead_target_id`,
                                                             COUNT(DISTINCT CASE
                                                                 WHEN lt2.`lead_target_type_lead_target_type_id` = 1
                                                                      AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                                 THEN ll.`leads_id`
                                                 
                                                                 WHEN lt2.`lead_target_type_lead_target_type_id` = 2
                                                                      AND ll.`lead_status_lead_status_id` IN (7,11,12,13,14,15,16,17)
                                                                      AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                                 THEN ll.`leads_id`
                                                 
                                                                 WHEN lt2.`lead_target_type_lead_target_type_id` = 3
                                                                      AND ll.`lead_status_lead_status_id` = 11
                                                                      AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                                 THEN ll.`leads_id`
                                                             END) AS `achieved_count`
                                                         FROM `lead_targets` lt2
                                                         LEFT JOIN `lead_logs` ll
                                                             ON ll.`users_user_id` = lt2.`counselor_user_id`
                                                         GROUP BY lt2.`lead_target_id`
                                                     ) a ON a.`lead_target_id` = lt.`lead_target_id`
                                                 WHERE lt.`counselor_user_id` = '$id'
                                                     ORDER BY lt.`lead_target_id` DESC
                                                 ";

                                                        function getPreviousTargetPercentage($currentTargetId, $counselorId, $targetTypeId, $periodTypeId)
                                                        {
                                                            $previousQuery = "
                                                                    SELECT 
                                                                        lt.`target`,
                                                                        COALESCE(a.`achieved_count`, 0) AS `achieved_count`
                                                                    FROM `lead_targets` lt
                                                                    LEFT JOIN (
                                                                        SELECT 
                                                                            lt2.`lead_target_id`,
                                                                            COUNT(DISTINCT CASE
                                                                                WHEN lt2.`lead_target_type_lead_target_type_id` = 1
                                                                                    AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                                                THEN ll.`leads_id`

                                                                                WHEN lt2.`lead_target_type_lead_target_type_id` = 2
                                                                                    AND ll.`lead_status_lead_status_id` IN (7,11,12,13,14,15,16,17)
                                                                                    AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                                                THEN ll.`leads_id`

                                                                                WHEN lt2.`lead_target_type_lead_target_type_id` = 3
                                                                                    AND ll.`lead_status_lead_status_id` = 11
                                                                                    AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                                                THEN ll.`leads_id`
                                                                            END) AS `achieved_count`
                                                                        FROM `lead_targets` lt2
                                                                        LEFT JOIN `lead_logs` ll
                                                                            ON ll.`users_user_id` = lt2.`counselor_user_id`
                                                                        GROUP BY lt2.`lead_target_id`
                                                                    ) a ON a.`lead_target_id` = lt.`lead_target_id`
                                                                    WHERE lt.`counselor_user_id` = '$counselorId'
                                                                    AND lt.`lead_target_type_lead_target_type_id` = '$targetTypeId'
                                                                    AND lt.`lead_target_period_lead_target_period_id` = '$periodTypeId'
                                                                    AND lt.`lead_target_id` < '$currentTargetId'
                                                                    ORDER BY lt.`lead_target_id` DESC
                                                                    LIMIT 1
                                                                ";

                                                            $result = Database::search($previousQuery);

                                                            if ($result->num_rows > 0) {
                                                                $data = $result->fetch_assoc();
                                                                $target = (int)$data['target'];
                                                                $achieved = (int)$data['achieved_count'];

                                                                if ($target > 0) {
                                                                    return round(($achieved / $target) * 100);
                                                                }
                                                            }

                                                            return 0;
                                                        }

                                                        $results = Database::search($query);
                                                        ?>
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                        <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows ?> Targets</span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="darkColor px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text-color form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleLeadsTargetPagination(<?= $id ?>,'filter');">
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
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="type-selector" onchange="handleLeadsTargetPagination(<?= $id ?>,'filter')">
                                                                                        <option selected value="0">- Select Target Type -</option>
                                                                                        <?php
                                                                                        $target_type = Database::search("SELECT * FROM `lead_target_type`");
                                                                                        while ($data = $target_type->fetch_assoc()) {
                                                                                        ?>
                                                                                            <option value="<?php echo $data["lead_target_type_id"]; ?>"><?php echo $data["lead_target_type"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="period-selector" onchange="handleLeadsTargetPagination(<?= $id ?>,'filter')">
                                                                                        <option selected value="0">- Select Target Period -</option>
                                                                                        <?php
                                                                                        $lead_target_period = Database::search("SELECT * FROM `lead_target_period`");
                                                                                        while ($data = $lead_target_period->fetch_assoc()) {
                                                                                        ?>
                                                                                            <option value="<?php echo $data["lead_target_period_id"]; ?>"><?php echo $data["lead_target_period"]; ?></option>
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
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector">
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

                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Target Type</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Intake</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Target</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Expected End</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Achieved</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Pending</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Achieved %</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Previous %</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Trend</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
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
                                                                            $targets = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");


                                                                            if ($targets->num_rows > 0) {
                                                                                $status_classes = [
                                                                                    "1"     => "table-btn-orange",
                                                                                    "2"  => "table-btn-pink",
                                                                                    "3"  => "table-btn-yellow",
                                                                                ];

                                                                                function getProgressBarColor($percentage)
                                                                                {
                                                                                    if ($percentage < 50) {
                                                                                        return "bg-dark-red";
                                                                                    } elseif ($percentage < 75) {
                                                                                        return "bg-dark-orange";
                                                                                    } else {
                                                                                        return "bg-dark-green";
                                                                                    }
                                                                                }
                                                                                while ($data = $targets->fetch_assoc()) {
                                                                                    $previousPercentage = getPreviousTargetPercentage(
                                                                                        $data["lead_target_id"],
                                                                                        $data["counselor_user_id"],
                                                                                        $data["lead_target_type_lead_target_type_id"],
                                                                                        $data["lead_target_period_lead_target_period_id"]
                                                                                    );

                                                                                    $percentage = (int)$data["achieved_percentage"];
                                                                                    $achieved = (int)$data["achieved_count"];
                                                                                    $pending = (int)$data["pending_count"];

                                                                                    if ($percentage > $previousPercentage) {
                                                                                        $trendIcon = "mdi:trending-up";
                                                                                        $trendClass = "text-success";
                                                                                        $trendBg = "bg-success-subtle";
                                                                                        $trendText = "Up";
                                                                                    } elseif ($percentage < $previousPercentage) {
                                                                                        $trendIcon = "mdi:trending-down";
                                                                                        $trendClass = "text-danger";
                                                                                        $trendBg = "bg-danger-subtle";
                                                                                        $trendText = "Down";
                                                                                    } else {
                                                                                        $trendIcon = "mdi:minus";
                                                                                        $trendClass = "text-secondary";
                                                                                        $trendBg = "bg-secondary-subtle";
                                                                                        $trendText = "Same";
                                                                                    }


                                                                            ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3">
                                                                                            <span class="text-14 main-text"><?= $data["lead_target_type"] ?></span>
                                                                                        </td>
                                                                                        <td class="py-3">
                                                                                            <span class="text-14 main-text"><?= !empty($data["intake"]) ? $data["intake"] : "-" ?></span>
                                                                                        </td>
                                                                                        <td class="py-3 text-center">
                                                                                            <span class="fw-bold"><?= $data["target"] ?></span>
                                                                                        </td>
                                                                                        <td class="py-3">
                                                                                            <span class="text-14 main-text">
                                                                                                <?= $data["end_date"] ?>
                                                                                            </span>
                                                                                        </td>
                                                                                        <td class="py-3 text-center">
                                                                                            <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2"><?= $achieved ?></span>
                                                                                        </td>
                                                                                        <td class="py-3 text-center">
                                                                                            <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2"><?= $pending ?></span>
                                                                                        </td>
                                                                                        <td class="py-3 text-center" style="min-width: 180px;">
                                                                                            <div class="d-flex flex-column gap-1">
                                                                                                <div class="fw-semibold"><?= $percentage ?>%</div>
                                                                                                <div class="progress" style="height: 8px;">
                                                                                                    <div class="progress-bar <?= getProgressBarColor($percentage) ?> br-0" role="progressbar" style="width: <?= min($percentage, 100) ?>%"></div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="py-3 text-center">
                                                                                            <span class="text-14 main-text"><?= $previousPercentage ?>%</span>
                                                                                        </td>
                                                                                        <td class="py-3 text-center">
                                                                                            <div class="d-inline-flex align-items-center justify-content-center gap-2 px-3 py-2 w-100 <?= $trendBg ?>">
                                                                                                <iconify-icon icon="<?= $trendIcon ?>" class="<?= $trendClass ?>" width="18" height="18"></iconify-icon>
                                                                                                <span class="text-14 <?= $trendClass ?> fw-semibold"><?= $trendText ?></span>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon
                                                                                                    icon="charm:menu-kebab"
                                                                                                    class="cursor-pointer"
                                                                                                    data-bs-toggle="dropdown"
                                                                                                    data-bs-boundary="viewport"></iconify-icon>

                                                                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 br-5">
                                                                                                    <li>
                                                                                                        <a class="dropdown-item d-flex align-items-center"
                                                                                                            href="counselor-target-summary?target-id=<?= $data['lead_target_id']; ?>">
                                                                                                            <iconify-icon icon="carbon:view" width="20" class="me-2"></iconify-icon>
                                                                                                            View
                                                                                                        </a>
                                                                                                    </li>
                                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                    <li>
                                                                                                        <a class="dropdown-item d-flex align-items-center cursor-pointer"
                                                                                                            onclick='openEditTargetModal(
                                                                                                                <?= json_encode($data["lead_target_id"]) ?>,
                                                                                                                <?= json_encode($data["lead_target_type_lead_target_type_id"]) ?>,
                                                                                                                <?= json_encode($data["lead_target_period_lead_target_period_id"]) ?>,
                                                                                                                <?= json_encode($data["intakes_intake_id"] ?? 0) ?>,
                                                                                                                <?= json_encode($data["start_date"]) ?>,
                                                                                                                <?= json_encode($data["end_date"]) ?>,
                                                                                                                <?= json_encode($data["target"]) ?>
                                                                                                            )'>
                                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" class="me-2"></iconify-icon>
                                                                                                            Edit
                                                                                                        </a>
                                                                                                    </li>
                                                                                                </ul>
                                                                                            </div>
                                                                                        </td>
                                                                                    </tr>
                                                                                <?php
                                                                                }
                                                                            } else {
                                                                                ?>
                                                                                <tr>
                                                                                    <td colspan="11" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                                                                    </td>
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
                                                                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleLeadsTargetPagination(<?= $id ?>,'previousPage')">
                                                                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                                                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                </button>
                                                                                <!-- Page Numbers (col-10, centered) -->
                                                                                <div class="col-10 custom-pagination d-flex justify-content-center">
                                                                                    <ul class="pagination-list d-flex mb-0">
                                                                                        <?php
                                                                                        $shudderPages1 = false;
                                                                                        $shudderPages2 = false;

                                                                                        function renderPageButton($i, $isActive = false, $id)
                                                                                        {
                                                                                            $activeClass = $isActive ? 'active' : '';
                                                                                            $action = $isActive ? '' : "onclick=\"handleLeadsTargetPagination('{$id}','goToPage', '{$i}')\"";
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
                                                                                <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLeadsTargetPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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

                        <div class="modal fade br-0" id="addTarget" aria-hidden="true" aria-labelledby="addTargetLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="addTargetLabel">Add Target</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>

                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">

                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="target-type">Target Type</label>
                                                    <select id="target-type" class="form-select custom-select mt-2">
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        $lead_target_type = Database::search("SELECT * FROM `lead_target_type`");
                                                        while ($data = $lead_target_type->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $data["lead_target_type_id"]; ?>">
                                                                <?= $data["lead_target_type"]; ?>
                                                            </option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="target-type-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="target-period">Target Period</label>
                                                    <select id="target-period" class="form-select custom-select mt-2">
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        $lead_target_period = Database::search("SELECT * FROM `lead_target_period`");
                                                        while ($data = $lead_target_period->fetch_assoc()) {
                                                        ?>
                                                            <option value="<?= $data["lead_target_period_id"]; ?>">
                                                                <?= $data["lead_target_period"]; ?>
                                                            </option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="target-period-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-md-6 d-flex flex-column d-none" id="intake-container">
                                                    <label class="text-14 main-text required" for="intake-id">Intake</label>
                                                    <select id="intake-id" class="form-select custom-select mt-2">
                                                        <option value="0">- Select -</option>
                                                        <?php
                                                        $intake = Database::search("SELECT * FROM `intakes` WHERE `start_date` <= CURDATE()");
                                                        while ($data = $intake->fetch_assoc()) {
                                                        ?>
                                                            <option
                                                                value="<?= $data["intake_id"]; ?>"
                                                                data-start="<?= $data["start_date"]; ?>">
                                                                <?= $data["intake"]; ?>
                                                            </option>
                                                        <?php
                                                        }
                                                        ?>
                                                    </select>
                                                    <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="start-date">Start Date</label>
                                                    <input type="date" class="form-control custom-input mt-2" id="start-date">
                                                    <span id="start-date-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="end-date">End Date</label>
                                                    <input type="date" class="form-control custom-input mt-2" id="end-date">
                                                    <span id="end-date-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="target-value">Target</label>
                                                    <input type="number" class="form-control custom-input mt-2" id="target-value" min="0">
                                                    <span id="target-value-feedback" class="text-dark-red text-12"></span>
                                                </div>

                                                <div class="col-12">
                                                    <div class="row justify-content-end">
                                                        <div class="col-12 col-md-4">
                                                            <button class="w-100 custom-btn m-0" onclick="addLeadTarget(this,<?= $id ?>)">Add Target</button>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade br-0" id="editTarget" aria-hidden="true" aria-labelledby="editTargetLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editTargetLabel">Edit Target</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body p-0 mt-3">
                                        <input type="hidden" id="edit-target-id">

                                        <div class="row gy-3 gx-3">
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text required">Target Type</label>
                                                <select id="edit-target-type" class="form-select custom-select mt-2" disabled>
                                                    <option value="0">- Select -</option>
                                                    <?php
                                                    $lead_target_type = Database::search("SELECT * FROM `lead_target_type`");
                                                    while ($data = $lead_target_type->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?= $data["lead_target_type_id"]; ?>">
                                                            <?= $data["lead_target_type"]; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <span id="edit-target-type-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text required">Target Period</label>
                                                <select id="edit-target-period" class="form-select custom-select mt-2" onchange="toggleEditIntakeContainer()" disabled>
                                                    <option value="0">- Select -</option>
                                                    <?php
                                                    $lead_target_period = Database::search("SELECT * FROM `lead_target_period`");
                                                    while ($data = $lead_target_period->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?= $data["lead_target_period_id"]; ?>">
                                                            <?= $data["lead_target_period"]; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <span id="edit-target-period-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12 col-md-6 d-flex flex-column d-none" id="edit-intake-container">
                                                <label class="text-14 main-text required">Intake</label>
                                                <select id="edit-intake-id" class="form-select custom-select mt-2">
                                                    <option value="0">- Select -</option>
                                                    <?php
                                                    $intake = Database::search("SELECT * FROM `intakes` WHERE `start_date` <= CURDATE()");
                                                    while ($data = $intake->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?= $data["intake_id"]; ?>">
                                                            <?= $data["intake"]; ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                                <span id="edit-intake-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text required">Start Date</label>
                                                <input type="date" class="form-control custom-input mt-2" id="edit-start-date">
                                                <span id="edit-start-date-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text required">End Date</label>
                                                <input type="date" class="form-control custom-input mt-2" id="edit-end-date">
                                                <span id="edit-end-date-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text required">Target</label>
                                                <input type="number" class="form-control custom-input mt-2" id="edit-target-value" min="0">
                                                <span id="edit-target-value-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <div class="col-12">
                                                <div class="row justify-content-end">
                                                    <div class="col-12 col-md-4">
                                                        <button class="w-100 custom-btn m-0" onclick="editLeadTarget(this, <?= $id ?>)">
                                                            Update Target
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

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