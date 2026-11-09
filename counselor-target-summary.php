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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['target-id']) && !empty($_GET['target-id']))) {
            header("Location: ./users");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            require_once "./includes/connection.php";
            $id = $_GET['target-id'];
            $target = Database::search("SELECT * FROM `lead_targets`
            INNER JOIN `users` ON `lead_targets`.`counselor_user_id`=`users`.`user_id`
            INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id` 
            WHERE `lead_target_id`='" . $id . "'");

            if ($target->num_rows == 0) {
                header("Location: ./users");
            } else {
                $targetData = $target->fetch_assoc();

                $targetTypeId = (int)$targetData["lead_target_type_lead_target_type_id"];
                $counselorUserId = (int)$targetData["counselor_user_id"];
                $startDate = !empty($targetData["start_date"]) ? $targetData["start_date"] . " 00:00:00" : date("Y-m-01 00:00:00", strtotime($targetData["date_added"]));
                $endDate = !empty($targetData["end_date"]) ? $targetData["end_date"] . " 23:59:59" : date("Y-m-t 23:59:59", strtotime($targetData["date_added"]));
                $targetValue = (int)$targetData["target"];
                $intakeId = !empty($targetData["intakes_intake_id"]) ? (int)$targetData["intakes_intake_id"] : 0;

                $achievedQuery = "";
                $countQuery = "";

                if ($targetTypeId == 1) {

                    $achievedQuery = "
                    SELECT 
                        l.*,
                        p.programme,
                        ls.lead_status,
                        ls.lead_status_id,
                        u.user_fname,
                        u.user_lname,
                        latest_completed.completed_at
                    FROM (
                        SELECT 
                            ll.leads_id,
                            MAX(ll.date_updated) AS completed_at
                        FROM lead_logs ll
                        WHERE ll.users_user_id = '$counselorUserId'
                        AND ll.lead_status_lead_status_id = 5
                        AND ll.date_updated BETWEEN '$startDate' AND '$endDate'
                        GROUP BY ll.leads_id
                    ) latest_completed
                    INNER JOIN leads l ON l.lead_id = latest_completed.leads_id
                    LEFT JOIN programmes p ON p.programme_id = l.programmes_programme_id
                    LEFT JOIN lead_status ls ON ls.lead_status_id = l.lead_status_lead_status_id
                    LEFT JOIN users u ON u.user_id = l.users_user_id
                    ORDER BY latest_completed.completed_at DESC
                ";

                    $countQuery = "
        SELECT COUNT(DISTINCT ll.`leads_id`) AS achieved_count
        FROM `lead_logs` ll
        WHERE ll.`users_user_id` = '$counselorUserId'
        AND ll.`lead_status_lead_status_id` = 5
        AND ll.`date_updated` BETWEEN '$startDate' AND '$endDate'
    ";
                   
                } elseif ($targetTypeId == 2) {
                  
                   $achievedQuery = "
        SELECT 
            l.*,
            p.programme,
            ls.lead_status,
            ls.lead_status_id,
            u.user_fname,
            u.user_lname,
            `lead_student_reg_status`.*
        FROM `leads` l
        LEFT JOIN `programmes` p ON p.`programme_id` = l.`programmes_programme_id`
        LEFT JOIN `lead_status` ls ON ls.`lead_status_id` = l.`lead_status_lead_status_id`
         LEFT JOIN `lead_student_reg_status` 
            ON l.lead_student_reg_status_lead_student_reg_status_id = lead_student_reg_status.lead_student_reg_status_id
        LEFT JOIN `users` u ON u.`user_id` = l.`users_user_id`
        WHERE l.`added_by` = '$counselorUserId'
        AND l.`date_added` BETWEEN '$startDate' AND '$endDate'
        ORDER BY l.`lead_id` DESC
    ";

                    $countQuery = "
        SELECT COUNT(DISTINCT l.`lead_id`) AS achieved_count
        FROM `leads` l
        WHERE l.`added_by` = '$counselorUserId'
        AND l.`date_added` BETWEEN '$startDate' AND '$endDate'
    ";
                } 
                $countResult = Database::search($countQuery);
                $countData = $countResult->fetch_assoc();

                $achievedCount = (int)($countData["achieved_count"] ?? 0);
                $pendingCount = max($targetValue - $achievedCount, 0);
                $achievedPercentage = $targetValue > 0 ? round(($achievedCount / $targetValue) * 100) : 0;
                $achievedPercentageBar = min($achievedPercentage, 100);

                $achievedLeads = Database::search($achievedQuery);

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Counselor Target Summary - Admin Dashboard | <?= Project::getBaseName() ?></title>

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

                <body class="overflow-hidden">
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
                                                            <span class="main-topic lh-lg">Counselor Target Summary</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./Performance.php">Performance</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./counselor-performance?user-id=<?= $id; ?>"><?= $targetData['title'] . "." . $targetData['user_fname'] . " " . $targetData['user_lname']; ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="#">Counselor Target Summary</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <style>
                                                .progress {
                                                    background-color: var(--light-theme) !important;
                                                    border-radius: 20px;
                                                }

                                                .progress-bar {
                                                    border-radius: 20px;
                                                }
                                            </style>
                                            <div class="col-12 mb-4">
                                                <div class="component-theme p-4">

                                                    <div class="row align-items-center gy-4">

                                                        <div class="col-12 col-xl-4">
                                                            <div class="d-flex align-items-center">
                                                                <div class="hwr-70 table-btn-primary d-flex align-items-center justify-content-center">
                                                                    <iconify-icon icon="streamline:target"
                                                                        width="34"
                                                                        height="34"
                                                                        class="text-highlight"></iconify-icon>
                                                                </div>

                                                                <div class="ms-3">
                                                                    <div class="text-12 fw-semibold sub-text text-uppercase">
                                                                        Target Overview
                                                                    </div>

                                                                    <h4 class="main-text fw-bold mb-1">
                                                                        <?= $targetValue ?> <?= $targetTypeId == 1 ? 'Registrations' : 'Leads' ?>
                                                                    </h4>

                                                                    <div class="text-14 sub-text">
                                                                        <?= date("d M Y", strtotime($startDate)) ?> -
                                                                        <?= date("d M Y", strtotime($endDate)) ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-xl-5">
                                                            <div class="row g-3">


                                                                <div class="col-4">
                                                                    <div class="bg-light-green br-8 p-3 text-center h-100">
                                                                        <div class="text-12 fw-semibold text-dark-green text-uppercase">
                                                                            Achieved
                                                                        </div>
                                                                        <div class="text-24 fw-bold main-text">
                                                                            <?= $achievedCount ?>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <div class="col-4">
                                                                    <div class="bg-light-yellow br-8 p-3 text-center h-100">
                                                                        <div class="text-12 fw-semibold text-dark-yellow text-uppercase">
                                                                            Pending
                                                                        </div>
                                                                        <div class="text-24 fw-bold main-text">
                                                                            <?= $pendingCount ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-4">
                                                                    <div class="bg-light-orange br-8 p-3 text-center h-100">
                                                                        <div class="text-12 fw-semibold text-dark-orange text-uppercase">
                                                                            Target
                                                                        </div>
                                                                        <div class="text-24 fw-bold main-text">
                                                                            <?= $targetValue ?>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-xl-3">
                                                            <div class="text-end text-xl-end text-start">
                                                                <div class="text-12 fw-semibold sub-text text-uppercase mb-2">
                                                                    Achievement Progress
                                                                </div>

                                                                <div class="d-flex align-items-center gap-2">
                                                                    <div class="progress flex-grow-1" style="height: 10px;">
                                                                        <div class="progress-bar bg-dark-theme"
                                                                            style="width: <?= $achievedPercentageBar ?>%;">
                                                                        </div>
                                                                    </div>

                                                                    <span class="text-16 fw-bold main-text">
                                                                        <?= $achievedPercentage ?>%
                                                                    </span>
                                                                </div>

                                                                <div class="text-12 sub-text mt-2">
                                                                    <?= $achievedCount ?> completed out of <?= $targetValue ?>
                                                                </div>
                                                            </div>
                                                        </div>

                                                    </div>

                                                </div>
                                            </div>

                                            <div class="col-12 p-4 component-theme">
                                                <div class="row mb-3">
                                                    <div class="col-12">
                                                        <h5 class="fw-bold mb-1 sub-topic">Achieved Target</h5>
                                                        <p class="sub-text text-14 mb-0">
                                                            Leads matched to the selected target period and target type.
                                                        </p>
                                                    </div>
                                                </div>

                                                <div class="overflow-x-auto">
                                                    <table class="table bl-solid m-0 p-0 g-0 align-middle">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Lead ID</th>
                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Full Name</th>
                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Contact</th>
                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Date</th>
                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php
                                                            if ($achievedLeads->num_rows > 0) {
                                                                $status_classes = [
                                                                    "1"  => "table-btn-yellow",
                                                                    "2"  => "table-btn-primary",
                                                                    "3"  => "table-btn-danger",
                                                                    "4"  => "table-btn-success",
                                                                    "5"  => "table-btn-pink",
                                                                ];
                                                                $ribbonAllowed = [2, 3, 4, 7, 9, 10, 14];
                                                                while ($lead = $achievedLeads->fetch_assoc()) {
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
                                                                        <td class="py-3">
                                                                            <span class="text-14 main-text"><?= $lead["name"] ?></span>
                                                                        </td>
                                                                        <td class="py-3">
                                                                            <span class="text-14 main-text"><?= $lead["contact"] ?></span>
                                                                        </td>
                                                                        <td class="py-3">
                                                                            <span class="text-14 main-text"><?= !empty($lead["programme"]) ? $lead["programme"] : "-" ?></span>
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
                                                                        <td class="py-3">
                                                                            <span class="text-14 main-text">
                                                                                <?= !empty($displayDate) ? (new DateTime($displayDate))->format("Y-m-d h:i A") : "-" ?>
                                                                            </span>
                                                                        </td>
                                                                        <td class="py-3 align-middle">
                                                                            <div class="dropdown">
                                                                                <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text-color cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lead-view?lead-id=<?= $lead['lead_id']; ?>">
                                                                                            <iconify-icon icon="carbon:view" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                            View</a></li>
                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick='window.location.href = "edit-lead?lead-id=<?= $lead["lead_id"]; ?>"'>
                                                                                            <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                            Edit</a></li>
                                                                                </ul>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php
                                                                }
                                                            } else {
                                                                ?>
                                                                <tr>
                                                                    <td colspan="7" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                                                    </td>
                                                                </tr>
                                                            <?php
                                                            }
                                                            ?>
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
?>