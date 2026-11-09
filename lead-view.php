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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 1) || UserPermissions::hasPermission('leads', 10))) {
            header("Location: ./dashboard");
        } else {

            if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lead-id']) && !empty($_GET['lead-id']))) {
                header("Location: ./inquiries");
            } else {
                require_once "./includes/connection.php";
                $id = $_GET['lead-id'];

                $query = "
            SELECT 
                leads.*,
                users.*,
               programmes.*,
              gender.*,
              lead_source.*,
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
            LEFT JOIN `gender` ON `leads`.`gender_gender_id` = `gender`.`gender_id`
            LEFT JOIN `lead_source` ON `leads`.`lead_source_lead_source_id` = `lead_source`.`lead_source_id`
            LEFT JOIN (
                                                        SELECT `leads_id`, MAX(`date_updated`) AS latest_log
                                                        FROM `lead_logs`
                                                        GROUP BY `leads_id`
                                                    ) AS latest_logs ON `leads`.`lead_id` = `latest_logs`.`leads_id`
            WHERE `leads`.`lead_id` = '" . $id . "'
        ";

                if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                    $query .= " AND `leads`.`users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                }

                $lead = Database::search($query);
                if ($lead->num_rows == 0) {
                    header("Location: ./inquiries");
                } else {
                    $leadData = $lead->fetch_assoc();

                    $query = "SELECT * FROM `lead_logs`
                    LEFT JOIN `users` ON `lead_logs`.`users_user_id` = `users`.`user_id`
                    LEFT JOIN `lead_status` 
                    ON `lead_logs`.`lead_status_lead_status_id` = `lead_status`.`lead_status_id`
                    LEFT JOIN `lead_student_reg_status` 
                    ON `lead_logs`.`lead_student_reg_status_lead_student_reg_status_id` = `lead_student_reg_status`.`lead_student_reg_status_id`
                    WHERE `lead_logs`.`leads_id` = '" . $id . "' 
                    GROUP BY `lead_logs`.`lead_log_id`
                    ORDER BY `lead_logs`.`lead_log_id` DESC";

                    $logs = Database::search($query);

                    $statusUpdateLogs = Database::search("SELECT COUNT(*) AS count FROM `lead_logs`
                    LEFT JOIN `users` ON `lead_logs`.`users_user_id` = `users`.`user_id`
                    LEFT JOIN `lead_status` 
                    ON `lead_logs`.`lead_status_lead_status_id` = `lead_status`.`lead_status_id`
                    LEFT JOIN `lead_student_reg_status` 
                    ON `lead_logs`.`lead_student_reg_status_lead_student_reg_status_id` = `lead_student_reg_status`.`lead_student_reg_status_id`
                    WHERE `lead_logs`.`leads_id` = '" . $id . "' 
                    AND `lead_status`.`lead_status_id` IS NOT NULL
                    GROUP BY `lead_logs`.`lead_log_id`
                    ORDER BY `lead_logs`.`lead_log_id` DESC")->fetch_assoc();

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Lead View - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">

                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";

                                    if ($leadData['sid'] == '1') {
                                        load_side_menu::load("new-leads");
                                    } else {
                                        load_side_menu::load("inquiries");
                                    }

                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                                    <div class="row p-0 m-0 g-0 h-100">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            if ($leadData['sid'] == '1') {
                                                selectItem::setSelected("new-leads");
                                            } else {
                                                selectItem::setSelected("inquiries");
                                            }
                                            ?>
                                        </div>

                                        <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-table.php";
                                            ?>
                                        </div>

                                        <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"> # <?= $id ?> Lead</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="<?= $leadData['sid'] == 1 ? "./new-leads" : "./inquiries" ?>"><?= $leadData['sid'] == 1 ? "New Leads" : "All Leads" ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">View Lead</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                                                        ?>
                                                            <div class="col-12 col-md-4 mt-3 m-md-0 p-0 d-flex justify-content-end align-items-center">
                                                                <div class="row gx-3 justify-content-end w-100">
                                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <div class="dropdown w-100">
                                                                            <?php
                                                                            if (!empty($statusUpdateLogs) && $statusUpdateLogs['count'] > 0) {
                                                                                $leadConvertStudent = Database::search("SELECT * FROM `students_has_intakes` WHERE `leads_lead_id`='" . $leadData['nid'] . "'");
                                                                                if ($leadConvertStudent->num_rows == 0) {
                                                                            ?>
                                                                                    <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                                        Action
                                                                                    </button>
                                                                                    <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                                        <li><a class="dropdown-item" href="student-registration?lead-id=<?= $leadData['nid'] ?>" data-i18n="">Convert to Student</a></li>
                                                                                        <?php
                                                                                        if ($leadData['programmes_programme_id'] != null) {
                                                                                        ?>
                                                                                            <li>
                                                                                                <a class="dropdown-item" href="#"
                                                                                                    onclick="sendRegistrationLink('<?= base64_encode($leadData['nid']) ?>','<?= $leadData['reg_payment_enabled'] ?>','<?= $leadData['reg_payment_amount'] ?>');">
                                                                                                    Send Registration Link
                                                                                                </a>
                                                                                            </li>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <li><a class="dropdown-item" href="#" data-bs-target="#programmeWarningModal" data-bs-toggle="modal">Send Registration Link</a></li>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </ul>
                                                                                    <?php
                                                                                } else if ($leadConvertStudent->num_rows == 1) {
                                                                                    if ($leadData['lead_student_reg_status_lead_student_reg_status_id'] == 2 || $leadData['lead_student_reg_status_lead_student_reg_status_id'] == 3) {
                                                                                    ?>
                                                                                        <button class="custom-btn w-100" onclick="window.location = 'reserved-registration-application?lead-id=<?= $leadData['nid'] ?>'">
                                                                                            Student Details
                                                                                        </button>
                                                                                    <?php
                                                                                    } else {
                                                                                    ?>
                                                                                        <button class="custom-btn w-100" onclick="window.location = 'lead-registered-student-details?lead-id=<?= $leadData['nid'] ?>'">
                                                                                            Student Details
                                                                                        </button>
                                                                            <?php
                                                                                    }
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </div>
                                                                    </div>
                                                                    <?php
                                                                    if ($leadData['sid'] != 6) {
                                                                    ?>
                                                                        <div class="col-6 m-md-0 d-flex justify-content-end align-items-center">
                                                                            <button class="custom-btn w-100" type="button" data-bs-target="#updateLead" data-bs-toggle="modal">
                                                                                Update Lead Status
                                                                            </button>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                            <?php
                                                        } else {
                                                            $leadConvertStudent = Database::search("SELECT * FROM `students_has_intakes` WHERE `leads_lead_id`='" . $leadData['nid'] . "'");
                                                            if ($leadConvertStudent->num_rows > 0) {
                                                            ?>
                                                                <button class="custom-btn w-100" onclick="window.location = 'lead-registered-student-details?lead-id=<?= $leadData['nid'] ?>'">
                                                                    Student Details
                                                                </button>
                                                        <?php
                                                            }
                                                        }
                                                        ?>
                                                    </div>
                                                </div>

                                                <div class="payment-card-container">
                                                    <div class="payment-card">
                                                        <div class="card-background"></div>

                                                        <div class="card-content">
                                                            <div class="payment-header">
                                                                <div class="reference-section">
                                                                    <span class="reference-label">PROGRAMME</span>
                                                                    <h2 class="reference-number"><?= $leadData['programme'] == "" ? "N/A" : $leadData['programme']; ?></h2>
                                                                    <hr>
                                                                </div>

                                                                <div class="amount-section">
                                                                    <div class="amount-display">
                                                                        <span class="amount-label">Assigned Counselor</span>
                                                                        <span class="amount-value">
                                                                            <?= $leadData['user_fname'] == "" ? "N/A" : $leadData['user_fname'] . " " . $leadData['user_lname']; ?>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- ALWAYS SHOW -->
                                                            <span class="reference-label mt-3">PERSONAL DETAILS</span>
                                                            <div class="details-row d-flex flex-column flex-lg-row">
                                                                <div class="detail-item">
                                                                    <div class="detail-icon align-self-start">
                                                                        <iconify-icon icon="wordpress:term-name" width="16" height="16"></iconify-icon>
                                                                    </div>
                                                                    <div class="detail-content">
                                                                        <span class="detail-value">Name : <?= $leadData["name"] == "" ? "N/A" : $leadData["name"]; ?></span>
                                                                        <span class="detail-value">Contact : <?= $leadData["contact"] == "" ? "N/A" : $leadData["contact"]; ?></span>
                                                                        <span class="detail-value">Whatsapp : <?= $leadData["whatsapp"] == "" ? "N/A" : $leadData["whatsapp"]; ?></span>
                                                                    </div>
                                                                </div>

                                                                <div class="detail-item">
                                                                    <div class="detail-icon align-self-start">
                                                                        <iconify-icon icon="mdi:source-merge" width="16" height="16"></iconify-icon>
                                                                    </div>
                                                                    <div class="detail-content align-self-start">
                                                                        <span class="detail-value">Interested Programme : <?= $leadData["programme"] == "" ? "N/A" : $leadData["programme"]; ?></span>
                                                                        <span class="detail-value">Lead Source : <?= $leadData["lead_source"] == "" ? "N/A" : $leadData["lead_source"]; ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- READ MORE BUTTON -->
                                                            <button
                                                                type="button"
                                                                class="card-read-more-btn"
                                                                onclick="toggleLeadCardDetails('leadCardDetails<?= $leadData['lead_id']; ?>', this)">
                                                                <span class="btn-text">Read More</span>
                                                                <iconify-icon icon="mdi:chevron-down" width="18" height="18"></iconify-icon>
                                                            </button>

                                                            <!-- HIDDEN CONTENT -->
                                                            <div class="lead-extra-details" id="leadCardDetails<?= $leadData['lead_id']; ?>">
                                                                <span class="reference-label mt-3">MORE PERSONAL DETAILS</span>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon align-self-start">
                                                                            <iconify-icon icon="wordpress:term-name" width="16" height="16"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-value">Date of Birth : <?= $leadData["date_of_birth"] == "" ? "N/A" : $leadData["date_of_birth"]; ?></span>
                                                                            <span class="detail-value">Gender : <?= $leadData["gender"] == "" ? "N/A" : $leadData["gender"]; ?></span>
                                                                            <span class="detail-value">Address : <?= $leadData["address"] == "" ? "N/A" : $leadData["address"]; ?></span>
                                                                            <span class="detail-value">Email : <?= $leadData["email"] == "" ? "N/A" : $leadData["email"]; ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon align-self-start">
                                                                            <iconify-icon icon="material-symbols:school-outline-rounded" width="16" height="16"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content align-self-start">
                                                                            <span class="detail-value">School : <?= $leadData["school"] == "" ? "N/A" : $leadData["school"]; ?></span>
                                                                            <span class="detail-value">Grade : <?= $leadData["grade"] == "" ? "N/A" : $leadData["grade"]; ?></span>
                                                                            <span class="detail-value">University/College : <?= $leadData["college"] == "" ? "N/A" : $leadData["college"]; ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <span class="reference-label mt-3">OTHER</span>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="proicons:note" width="16" height="16"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Note</span>
                                                                            <span class="detail-value"><?= $leadData["note"] == "" ? "N/A" : $leadData["note"]; ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <span class="reference-label mt-3">SCHEDULE DETAILS</span>
                                                                <div class="details-row d-flex flex-column flex-lg-row">
                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z" />
                                                                            </svg>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Remind Date</span>
                                                                            <span class="detail-value"><?= $leadData["remind_date"] ?? '--/--/----'; ?></span>
                                                                        </div>
                                                                    </div>

                                                                    <div class="detail-item">
                                                                        <div class="detail-icon">
                                                                            <iconify-icon icon="ri:time-line" width="16" height="16"></iconify-icon>
                                                                        </div>
                                                                        <div class="detail-content">
                                                                            <span class="detail-label">Call Time</span>
                                                                            <span class="detail-value"><?= $leadData["remind_time"] != null ? date('g:i A', strtotime($leadData["remind_time"])) : '--/--'; ?></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 mt-4 component-theme">
                                                    <div class="row p-4 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">

                                                                        <span class="text-14 dark-theme fw-medium"><?= $logs->num_rows; ?> Logs</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                                <div class="overflow-x-auto p-0 m-0 g-0" id="users-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lead Status</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Registration Status</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php

                                                                            if ($logs->num_rows > 0) {
                                                                                $status_classes = [
                                                                                    "1"  => "table-btn-yellow",
                                                                                    "2"  => "table-btn-primary",
                                                                                    "3"  => "table-btn-danger",
                                                                                    "4"  => "table-btn-success",
                                                                                    "5"  => "table-btn-pink",
                                                                                ];
                                                                                while ($data = $logs->fetch_assoc()) {
                                                                                    $statusStyle = GetLeadStatusStyle::get($data['lead_status_lead_status_id']);

                                                                            ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <?php
                                                                                                if ($data["user_id"] != null) {

                                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                                ?>
                                                                                                        <img src="./assets/imgs/users/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                    <?php
                                                                                                    } else {
                                                                                                        $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                        $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                    ?>
                                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                    <span class="text-14 main-text mx-2"><?= $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                                <?php
                                                                                                } else {
                                                                                                ?>
                                                                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><iconify-icon icon="meteor-icons:robot" width="16" height="16" class="dark-theme"></iconify-icon></div>
                                                                                                    <span class="text-14 main-text mx-2">System</span>
                                                                                                <?php
                                                                                                }
                                                                                                ?>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo (new DateTime($data["date_updated"]))->format('Y-m-d h:i A') ?></span></td>
                                                                                        <td class="py-2 align-middle">
                                                                                            <?php
                                                                                            if (empty($data["lead_status"])) {
                                                                                            ?><span class="text-14 main-text">N/A</span><?php
                                                                                                                                    } else {
                                                                                                                                        ?>
                                                                                                <label class="table-btn w-100 text-center" style="background: <?= htmlspecialchars($statusStyle['label_bg']) ?>; color: <?= htmlspecialchars($statusStyle['label_text']) ?>;"> <?php echo $data["lead_status"]; ?></label>
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
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['note'] == null ? "N/A" : $data['note']; ?></span></td>
                                                                                    </tr>
                                                                                <?php
                                                                                }
                                                                            } else {
                                                                                ?>
                                                                                <tr class="justify-content-center align-middle">
                                                                                    <td colspan="13" class="align-middle justify-content-center">
                                                                                     <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>
                                                                                    </td>
                                                                                </tr>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </tbody>
                                                                    </table>
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

                        <?php
                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                        ?>
                            <div class="modal fade br-0" id="updateLead" aria-hidden="true" aria-labelledby="updateLeadLabel" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                    <div class="modal-content p-4">
                                        <div class="modal-header p-0 border-0">
                                            <h5 class="modal-title">Update Lead</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-0 mt-3">
                                            <div class="col-12">
                                                <div class="row gy-3 align-content-center">
                                                    <div class="col-12 col-md-6 col-xl-6 d-flex flex-column">
                                                        <label class="text-14 main-text required" for="">New Status</label>
                                                        <select id="new-status" class="form-select custom-select mt-2">
                                                            <option value="0" selected disabled>- Select Status -</option>
                                                            <?php
                                                            $query = "SELECT * FROM `lead_status` WHERE `lead_status_id` NOT IN (1,6)";

                                                            $user = Database::search($query);
                                                            if ($user->num_rows > 0) {
                                                                while ($row = $user->fetch_assoc()) {
                                                            ?>
                                                                    <option value="<?php echo ($row["lead_status_id"]); ?>" <?= $leadData['sid'] == $row["lead_status_id"] ? 'selected' : ''  ?>><?php echo ($row["lead_status"]); ?> </option>
                                                            <?php
                                                                }
                                                            }
                                                            ?>
                                                        </select>
                                                        <span id="status-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6"></div>
                                                    <div class="col-12 col-md-6 col-xl-6 d-flex flex-column" id="call-date-area">
                                                        <label class="text-14 main-text" for="" id="call-date-label">Call Date</label>
                                                        <input type="date" class="form-control custom-input mt-2" id="cdate">
                                                        <span id="cdate-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-xl-6 d-flex flex-column" id="call-time-area">
                                                        <label class="text-14 main-text" for="" id="call-time-label">Call Time</label>
                                                        <input type="time" class="form-control custom-input mt-2" id="ctime">
                                                        <span id="ctime-feedback" class="text-dark-red text-12"></span>
                                                    </div>

                                                    <div class="col-12 d-flex flex-column container-fluid mt-3">
                                                        <label class="text-14 main-text" for="">Note</label>
                                                        <textarea name="note" id="note" class="form-control mt-2 br-0" rows="6"></textarea>
                                                        <span id="note-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer p-0 mt-4 border-0">
                                            <button class="col-8 col-md-4 custom-btn m-0" onclick="updateLead(this,<?= $leadData['nid'] ?>)">Update Lead</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="programmeWarningModal" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow">
                                        <!-- Close -->
                                        <div class="d-flex justify-content-end p-3">
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>

                                        <div class="modal-body text-center px-4 pb-4">

                                            <!-- Icon -->
                                            <iconify-icon icon="mdi:alert-circle-outline"
                                                width="50"
                                                class="text-warning mb-3">
                                            </iconify-icon>

                                            <!-- Title -->
                                            <h5 class="fw-semibold mb-2">
                                                Programme Not Selected
                                            </h5>

                                            <!-- Message -->
                                            <p class="text-muted">
                                                Please select a programme to enable registration link sending.
                                            </p>

                                            <!-- Programme Selector -->
                                            <div class="text-start mb-3">
                                                <label class="form-label text-13 main-text">Select Programme</label>

                                                <select id="programme-select" class="form-select custom-select" onchange="enableSaveChangesWithProgramme(this.value);">
                                                    <option value="0" selected disabled>- Select Programme -</option>
                                                    <?php
                                                    $programme = Database::search("SELECT * FROM `programmes`");
                                                    while ($data = $programme->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $data["programme_id"]; ?>"><?php echo $data["programme"]; ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                                <span id="programme-feedback" class="text-dark-red text-12"></span>
                                            </div>

                                            <!-- Info -->
                                            <div class="alert alert-warning small text-start">
                                                <ul class="mb-0 ps-3">
                                                    <li>Registration link depends on selected programme</li>
                                                    <li>Make sure correct programme is assigned</li>
                                                </ul>
                                            </div>

                                            <!-- Action Buttons -->
                                            <div class="d-flex justify-content-center gap-2">

                                                <button class="btn btn-light px-4 rounded-0"
                                                    data-bs-dismiss="modal">
                                                    Cancel
                                                </button>

                                                <button id="saveProgrammeBtn"
                                                    class="btn btn-primary px-4 rounded-0"
                                                    disabled onclick="updateLeadProgramme(this,<?= $leadData['nid'] ?>)">
                                                    Save Programme
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                         
                            <!-- ── Registration Link Modal ──────────────────────────────────── -->
                            <div class="modal fade duplicate-lead-modal" id="registrationLinkModal" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered duplicate-lead-dialog">
                                    <div class="modal-content duplicate-lead-content border-0">

                                        <button type="button" class="duplicate-lead-close" data-bs-dismiss="modal">
                                            <iconify-icon icon="mdi:close" width="18" height="18"></iconify-icon>
                                        </button>

                                        <div class="modal-body text-center px-4 py-4">

                                            <div class="duplicate-lead-icon mx-auto mb-3">
                                                <iconify-icon icon="solar:link-circle-bold" width="46" height="46"></iconify-icon>
                                            </div>

                                            <h5 class="duplicate-lead-title mb-2">
                                                Registration Link Ready
                                            </h5>

                                            <p class="duplicate-lead-text mb-4">
                                                Share this link with the student to complete the registration process.
                                            </p>

                                            <!-- Registration Link Input -->
                                            <div class="registration-link-box mb-4">
                                                <input type="text"
                                                    id="registrationLinkInput"
                                                    class="registration-link-input"
                                                    readonly>
                                            </div>

                                            <!-- ── Registration Payment Section ──────────────── -->
                                            <div class="reg-payment-section mb-4">

                                                <!-- Toggle Row -->
                                                <div class="reg-payment-toggle-row">
                                                    <div class="reg-payment-toggle-left">
                                                        <div class="reg-payment-icon-wrap">
                                                            <iconify-icon icon="solar:card-bold" width="16" height="16"></iconify-icon>
                                                        </div>
                                                        <div class="reg-payment-toggle-text">
                                                            <span class="reg-payment-label">Registration Payment</span>
                                                            <span class="reg-payment-sublabel">Require upfront payment with link</span>
                                                        </div>
                                                    </div>
                                                    <label class="reg-payment-switch">
                                                        <input type="checkbox" id="regPaymentEnabled" onchange="toggleRegPayment(this)">
                                                        <span class="reg-payment-slider"></span>
                                                    </label>
                                                </div>

                                                <!-- Amount Input (collapsed by default) -->
                                                <div class="reg-payment-amount-wrap" id="regPaymentAmountWrap">
                                                    <div class="reg-payment-amount-inner">
                                                        <div class="reg-payment-amount-row">
                                                            <label class="reg-payment-amount-label" for="regPaymentAmount">
                                                                Registration Amount
                                                                <span class="reg-required-star">*</span>
                                                            </label>
                                                            <div class="reg-amount-input-group">
                                                                <span class="reg-amount-currency">LKR</span>
                                                                <input type="number"
                                                                    id="regPaymentAmount"
                                                                    class="reg-amount-input"
                                                                    placeholder="0.00"
                                                                    min="0.01"
                                                                    step="0.01">
                                                            </div>
                                                            <div class="text-12 text-danger mt-1 text-start" id="regPaymentAmountFeedback"></div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                            <!-- ── End Registration Payment Section ──────────── -->

                                            <div class="registration-link-footer">
                                                <button type="button"
                                                    class="btn duplicate-lead-secondary rounded-0 w-100"
                                                    data-bs-dismiss="modal">
                                                    Close
                                                </button>
                                                <button type="button"
                                                    class="btn duplicate-lead-primary rounded-0 w-100"
                                                    onclick="updateLeadStatusAfterLink(this, <?= $leadData['nid'] ?>)">
                                                    Copy &amp; Update Status
                                                </button>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php
                        }
                        ?>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
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