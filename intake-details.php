<?php
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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            header("Location: ./intakes");
        } else {
            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intakes");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                $cardData = Database::search("WITH payment_totals_cte AS (
                    SELECT 
                        SUM(CASE WHEN `payment_status_payment_status_id` = 8 THEN amount ELSE 0 END) AS total_payments,
                        SUM(CASE WHEN `payment_status_payment_status_id` = 1 THEN amount ELSE 0 END) AS awaiting_payments,
                        SUM(CASE WHEN `payment_status_payment_status_id` = 2 THEN amount ELSE 0 END) AS pending_approval,
                        SUM(CASE WHEN `payment_status_payment_status_id` IN (4,7) THEN amount ELSE 0 END) AS reject_payments
                    FROM course_payments 
                    WHERE `payment_plan_has_students_has_intakes_id` 
                    IN (SELECT `payment_plan_has_students_has_intakes_id` 
                        FROM `payment_plan_has_students_has_intakes` 
                        WHERE `students_has_intakes_students_has_intakes_id` 
                        IN (SELECT `students_has_intakes_id` 
                            FROM `students_has_intakes` 
                            WHERE `intakes_intake_id`='" . $id . "'))
                )
                SELECT
                    (SELECT COUNT(*) FROM students_has_intakes WHERE `intakes_intake_id`='" . $id . "' ) AS student_count,
                    (SELECT COUNT(*) FROM lecturers WHERE `subjects_subject_id` IN (SELECT `subject_id` FROM `subjects` WHERE `levels_level_id` IN (SELECT `level_id` FROM `levels` WHERE `intakes_intake_id`='" . $id . "'))) AS lecturers_count,
                    (SELECT COUNT(*) FROM levels WHERE `intakes_intake_id`='" . $id . "') AS levels_count,
                    (SELECT COUNT(*) FROM subjects WHERE `levels_level_id` IN (SELECT `level_id` FROM `levels` WHERE `intakes_intake_id`='" . $id . "')) AS subjects_count,
                    total_payments,
                    awaiting_payments,
                    pending_approval,
                    reject_payments,
                    CASE
                        WHEN total_payments >= 1000000 THEN CONCAT(ROUND(total_payments / 1000000, 1), 'M')
                        WHEN total_payments >= 1000 THEN CONCAT(ROUND(total_payments / 1000, 1), 'K')
                        ELSE CAST(total_payments AS CHAR)
                    END AS total_payments_formatted,
                    CASE
                        WHEN awaiting_payments >= 1000000 THEN CONCAT(ROUND(awaiting_payments / 1000000, 1), 'M')
                        WHEN awaiting_payments >= 1000 THEN CONCAT(ROUND(awaiting_payments / 1000, 1), 'K')
                        ELSE CAST(awaiting_payments AS CHAR)
                    END AS awaiting_payments_formatted,
                    CASE
                        WHEN pending_approval >= 1000000 THEN CONCAT(ROUND(pending_approval / 1000000, 1), 'M')
                        WHEN pending_approval >= 1000 THEN CONCAT(ROUND(pending_approval / 1000, 1), 'K')
                        ELSE CAST(pending_approval AS CHAR)
                    END AS pending_approval_formatted,
                    CASE
                        WHEN reject_payments >= 1000000 THEN CONCAT(ROUND(reject_payments / 1000000, 1), 'M')
                        WHEN reject_payments >= 1000 THEN CONCAT(ROUND(reject_payments / 1000, 1), 'K')
                        ELSE CAST(reject_payments AS CHAR)
                    END AS reject_payments_formatted
                FROM payment_totals_cte");

                $cardData = $cardData->fetch_assoc();

                $isHasStudentActionsPermissions = SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id) || UserPermissions::hasPermission('intake_students', 1, $id);
                $isHasPaymentsActionsPermissions = SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 1, $id) || UserPermissions::hasPermission('intake_payments', 2, $id);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Intake Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    <style>
                        .accordion {
                            --bs-accordion-btn-color: var(--sub-topic);
                        }

                        .accordion-button::after {
                            order: -1;
                            margin-left: 0;
                            margin-right: 20px;
                            transform: rotate(0deg);
                        }

                        .accordion-button:not(.collapsed)::after {
                            transform: rotate(180deg);
                        }

                        .accordion-button {
                            justify-content: flex-start;
                        }

                        .accordion-icon {
                            transition: transform 0.3s ease, color 0.3s ease;
                        }

                        .bd-chart-canvas-wrap {
                            position: relative;
                            height: 280px;
                        }

                        .bd-range-toggle {
                            display: flex;
                        }

                        .bd-range-toggle button {
                            flex: 1;
                        }

                        .bd-at-risk-panel {
                            border: 1px solid #f1b0b0;
                            background: var(--light-red);
                        }

                        .bd-at-risk-row {
                            background: var(--custom-white);
                        }

                        .bd-streak-badge {
                            background: #ef4444;
                            color: #fff;
                            font-weight: 600;
                        }

                        .bd-at-risk-empty {
                            color: #6c757d;
                        }

                        .bd-context-line {
                            line-height: 1.3;
                        }

                        /* Uniform Status Breakdown (same look as uniform-status-breakdown.php) */
                        .ub-chart-wrap {
                            position: relative;
                            height: 260px;
                        }

                        .ub-context-line {
                            line-height: 1.3;
                        }

                        .ub-pending-row {
                            background: #fffaf0;
                        }
                    </style>
                </head>

                <body class="overflow-hidden" onload="initializeSelectedIntake(<?= $id ?>);">
                    <ul class="notifications"></ul>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
                                load_side_menu::load("course-overview");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("course-overview");
                                        ?>
                                    </div>

                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-dashboards.php"; ?>
                                    </div>

                                    <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Intake Details</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./programes?faculty-id=<?= $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href='./intakes?programe-id=<?= $intakeData['programme_id'] ?>'><?= $intakeData["programme"] ?></a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $intakeData["intake"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6)) {
                                                    ?>
                                                        <div class="col-2 d-flex align-items-center justify-content-end mt-3 mt-lg-0 m-md-0 text-end">
                                                            <button class="bg-transparent border-0 align-middle" type="button" onclick="window.location.href='intake-permissions?intake-id=<?= $intakeData['intake_id'] ?>'">
                                                                <iconify-icon id="sort-icon" icon="ic:twotone-lock-person" width="30" height="30" class="dark-theme p-0 m-0"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <?php
                                            require_once "./includes/classes/BatchDashboardSummary.php";

                                            if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
                                                $dashboardCards = BatchDashboardSummary::getLecturerBatchDashboardSummary($id);
                                            } else {
                                                $dashboardCards = BatchDashboardSummary::getBatchDashboardSummary($id);
                                            }
                                            ?>

                                            <div class="container-fluid p-0 mb-4">
                                                <div class="row g-3">
                                                    <?php foreach ($dashboardCards as $card) { ?>
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
                                                                        <?php if ($card['title'] != "Monthly New Admissions") { ?>
                                                                            <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?></div>
                                                                        <?php } else { ?>
                                                                            <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?> <span class="text-dark-orange text-18">+<?= htmlspecialchars($card['multi_class']) ?></span><span class="text-dark-orange text-14 fw-light mx-3">(Multi Class)</span></div>
                                                                        <?php } ?>

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

                                            <div class="accordion accordion-flush p-0 m-0" id="accordionFlush">

                                                <!-- ===================== LEVELS ===================== -->
                                                <div class="accordion-item mb-3 border-0">
                                                    <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-success">
                                                        <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start"
                                                            type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseOne"
                                                            aria-expanded="false" aria-controls="flush-collapseOne">
                                                            <span class="sub-topic">Levels</span>
                                                        </button>

                                                        <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake', 2, $id)) { ?>
                                                            <div class="d-flex align-items-center w-15">
                                                                <button class="custom-btn w-100" data-bs-target="#addLevel" data-bs-toggle="modal">
                                                                    Add New
                                                                </button>
                                                            </div>
                                                        <?php } ?>
                                                    </h2>
                                                    <div id="flush-collapseOne" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                        <div class="accordion-body bg-theme pt-3 px-0 m-0 border-0">
                                                            <div class="col-12 p-0">
                                                                <div class="row p-0 m-0">
                                                                    <?php
                                                                    $level_query = "
                                                        SELECT 
                                                            `levels`.*, 
                                                            COUNT(`subjects`.`levels_level_id`) AS subject_count
                                                        FROM `levels`
                                                        LEFT JOIN `subjects` 
                                                            ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                        WHERE `intakes_intake_id` = '" . $id . "'
                                                    ";

                                                                    if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
                                                                        $level_query .= " AND `levels`.`level_id` IN (SELECT `levels_level_id` FROM lecturers 
                                                        INNER JOIN subjects ON subjects.subject_id = lecturers.subjects_subject_id
                                                        WHERE lecturers.users_user_id = '" . $sessionUserObject['user_id'] . "'
                                                        AND lecturers.status_status_id = 1) ";
                                                                    }

                                                                    $level_query .= " GROUP BY `levels`.`level_id`";
                                                                    $levels = Database::search($level_query);
                                                                    if ($levels->num_rows > 0) {
                                                                        while ($level = $levels->fetch_assoc()) {
                                                                    ?>
                                                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                                                    <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                                                        <?php if (!empty($level['level_cover']) && file_exists("./assets/imgs/levels-covers/" . $level['level_cover'])) { ?>
                                                                                            <img src="./assets/imgs/levels-covers/<?= $level['level_cover'] ?>" class="w-100 h-auto" alt="level img">
                                                                                        <?php } else { ?>
                                                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="level img">
                                                                                        <?php } ?>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                                                        <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                                                            <?= htmlspecialchars($level['level']) ?>
                                                                                        </h6>
                                                                                        <p class="text-14 main-text lh-lg mb-0">
                                                                                            Number of Subjects : <?= htmlspecialchars($level['subject_count']) ?>
                                                                                        </p>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-3 p-0 mt-3 m-md-0 m-lg-0">
                                                                                        <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                                                            <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake', 10)) { ?>
                                                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                                    <button class="custom-danger-btn w-100" onclick="loadLevelEditModel('<?= $level['level_id'] ?>','<?= $level['level'] ?>','<?= $level['level_cover'] ?>')">Edit</button>
                                                                                                </div>
                                                                                            <?php } ?>

                                                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                                                <button class="custom-btn w-100" onclick="window.location.href='level-details?level-id=<?= $level['level_id']; ?>'">View</button>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                    } else {
                                                                        ?>
                                                                        <div class="col-12 px-4 py-3 component-theme h-100">
                                                                            <div class="row align-items-center justify-content-center h-100">
                                                                                <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                    <div class="no-data-state">
                                                                                        <div class="no-data-icon-wrap">
                                                                                            <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                        </div>
                                                                                        <p class="no-data-text">No data</p>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- ===================== STUDENTS ===================== -->
                                                <?php
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_students', $id)) {
                                                ?>
                                                    <div class="accordion-item mb-3 border-0">
                                                        <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-yellow">
                                                            <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseTwo" aria-expanded="false" aria-controls="flush-collapseTwo">
                                                                <span class="sub-topic">Students</span>
                                                            </button>

                                                            <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id)) { ?>
                                                                <div class="d-flex align-items-center w-15">
                                                                    <div class="dropdown w-100">
                                                                        <div class="d-flex align-items-center justify-content-center custom-btn fw-normal w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                            Actions
                                                                        </div>
                                                                        <ul class="dropdown-menu w-100" style="min-width: max-content;">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="add-intake-to-registered-students?intake-id=<?= $id; ?>">
                                                                                    <iconify-icon icon="ic:baseline-how-to-reg" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                    Add Registered Student</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="add-student-to-intake?intake-id=<?= $id; ?>">
                                                                                    <iconify-icon icon="akar-icons:person-add" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                    Add New Student</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="upload-intake-student-bulk?intake-id=<?= $id; ?>">
                                                                                    <iconify-icon icon="iconmind:bulk-endpoint-duotone-bold" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                    Add New Students Bulk</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-groups?intake-id=<?= (int)$id ?>">
                                                                                    <iconify-icon icon="akar-icons:people-group" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                    Manage Groups</a></li>

                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                        </h2>
                                                        <div id="flush-collapseTwo" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                            <div class="accordion-body p-4">
                                                                <div class="col-12 p-0 component-theme" id>
                                                                    <div class="row p-0 m-0 g-0 align-content-center">
                                                                        <div class="col-12 component-theme mb-4">
                                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                        <?php
                                                                                        $studentCount = Database::search("SELECT COUNT(*) as 'count' FROM `students_has_intakes` WHERE `intakes_intake_id` = '" . $id . "'");
                                                                                        $data = $studentCount->fetch_assoc();
                                                                                        ?>
                                                                                        <span class="text-14 dark-theme fw-medium" id="student-count"><?= $data["count"]; ?> Students</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-8 <?= $isHasStudentActionsPermissions ? 'col-md-6 col-lg-7' : 'col-md-8 col-lg-9' ?> d-flex flex-column">
                                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="filterIntakeHasStudents('<?= $id ?>')">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-1 col-lg-1' : 'col-12 col-md-2 col-lg-1' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-3 col-lg-2' : 'd-none' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="downloadIntakeStudentsCSV(<?= $id; ?>)">
                                                                                        Students
                                                                                        <iconify-icon icon="line-md:download-loop" width="24" height="24" class="text-white mx-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>

                                                                                <div class="col-12 collapse" id="studentsFilter">
                                                                                    <div class="card card-body border-0 p-0">
                                                                                        <div class="col-12">
                                                                                            <div class="row">
                                                                                                <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 4)) { ?>
                                                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                                        <select class="form-select custom-select" aria-label="Default select example" id="update-selector" onchange="filterIntakeHasStudents('<?= $id ?>')">
                                                                                                            <option selected value="0">- Update Students -</option>
                                                                                                            <option value="1">Active Users</option>
                                                                                                            <option value="2">Inactive Users</option>
                                                                                                            <option value="3">Discontinue Users</option>
                                                                                                        </select>
                                                                                                    </div>
                                                                                                    <div class="col-12 col-md-2 col-lg-2 d-flex flex-column justify-content-end">
                                                                                                        <button class="custom-btn w-100" type="button" onclick="applyIntakeHasStudentsUpdate(<?= $id ?>)">Apply</button>
                                                                                                    </div>
                                                                                                <?php } ?>
                                                                                            </div>
                                                                                            <div class="row justify-content-end">
                                                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column">
                                                                                                    <select name="" id="province" class="form-select custom-select mt-2" onchange="loadDistrict(),handleIntakeHasStudentPagination('<?= $id ?>','filter');">
                                                                                                        <option value="0">- Select -</option>
                                                                                                        <?php
                                                                                                        $province = Database::search("SELECT * FROM `province`");
                                                                                                        while ($data = $province->fetch_assoc()) {
                                                                                                        ?>
                                                                                                            <option value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                                                                        <?php } ?>
                                                                                                    </select>
                                                                                                    <span id="province-feedback" class="text-dark-red text-12"></span>
                                                                                                </div>
                                                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column">
                                                                                                    <select name="" id="district" class="form-select custom-select mt-2" onchange="loadCities(),handleIntakeHasStudentPagination('<?= $id ?>','filter');">
                                                                                                        <option value="0">- Select -</option>
                                                                                                        <?php
                                                                                                        $district = Database::search("SELECT * FROM `district`");
                                                                                                        while ($data = $district->fetch_assoc()) {
                                                                                                        ?>
                                                                                                            <option value="<?php echo $data["district_id"]; ?>"><?php echo $data["district"]; ?></option>
                                                                                                        <?php } ?>
                                                                                                    </select>
                                                                                                    <span id="district-feedback" class="text-dark-red text-12"></span>
                                                                                                </div>
                                                                                                <div class="col-12 col-md-6 col-lg-2 d-flex flex-column">
                                                                                                    <select name="" id="city" class="form-select custom-select mt-2" onchange="handleIntakeHasStudentPagination('<?= $id ?>','filter')">
                                                                                                        <option value="0">- Select -</option>
                                                                                                        <?php
                                                                                                        $city = Database::search("SELECT * FROM `city`");
                                                                                                        while ($data = $city->fetch_assoc()) {
                                                                                                        ?>
                                                                                                            <option value="<?php echo $data["city_id"]; ?>"><?php echo $data["city"]; ?></option>
                                                                                                        <?php } ?>
                                                                                                    </select>
                                                                                                    <span id="city-feedback" class="text-dark-red text-12"></span>
                                                                                                </div>
                                                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                                    <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleIntakeHasStudentPagination('<?= $id ?>','filter')">
                                                                                                        <option selected value="0">- Filter Data -</option>
                                                                                                        <option value="1">Active</option>
                                                                                                        <option value="2">Inactive</option>
                                                                                                        <option value="3">Discontinued</option>
                                                                                                    </select>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleIntakeHasStudentPagination('<?= $id ?>','filter')">
                                                                                        <option selected value="10">10</option>
                                                                                        <option value="25">25</option>
                                                                                        <option value="50">50</option>
                                                                                        <option value="100">100</option>
                                                                                        <option value="250">250</option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="table-responsive-wrapper">
                                                                            <div class="table-responsive" id="students-table-container">
                                                                                <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                                                    <thead>
                                                                                        <tr class="br-t-5">
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                    <div class="d-flex justify-content-between align-items-center w-100" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'sortByName')">
                                                                                                        <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                        <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'sortById')">
                                                                                                <div class="d-flex flex-row justify-content-between align-items-center cursor-pointer">
                                                                                                    <span class="text-14 main-text fw-medium me-2">Registration Number</span>
                                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Email Address</th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Mobile</th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'sortByDate')">
                                                                                                <div class="d-flex flex-row justify-content-between align-items-center cursor-pointer">
                                                                                                    <span class="text-14 main-text fw-medium me-2">Admission Date</span>
                                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                                            <?php
                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id) || UserPermissions::hasPermission('intake_students', 10, $id)) {
                                                                                            ?>
                                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                                            <?php } ?>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <?php

                                                                                    $query = "SELECT 
                                                                                                    `shi`.`students_has_intakes_id` AS `shiId`,
                                                                                                    `s`.`student_id` AS `sId`,
                                                                                                    `shi`.`student_id`,
                                                                                                    `ld`.`first_name`,
                                                                                                    `ld`.`last_name`,
                                                                                                    `ld`.`email`,
                                                                                                    `s`.`mobile`,
                                                                                                    `s`.`profile_img`,
                                                                                                    `shi`.`addmission_date`,
                                                                                                    `t`.`title`,
                                                                                                    `st`.`status_id`,
                                                                                                    `st`.`status`,
                                                                                                    `ld`.`verification`,
                                                                                                    `ld`.`reset_time`
                                                                                                FROM `students_has_intakes` AS `shi`
                                                                                                INNER JOIN `students` AS `s` 
                                                                                                    ON `shi`.`students_student_id` = `s`.`student_id`
                                                                                                INNER JOIN `login_details` AS `ld`
                                                                                                    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                                                                INNER JOIN `intakes` AS `i` 
                                                                                                    ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                                                                INNER JOIN `title` AS `t` 
                                                                                                    ON `s`.`title_title_id` = `t`.`title_id`
                                                                                                INNER JOIN `status` AS `st` 
                                                                                                    ON `shi`.`status_status_id` = `st`.`status_id`
                                                                                                WHERE `shi`.`intakes_intake_id` = '" . $id . "'
                                                                                                ";

                                                                                    if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                                                                        $query .= " AND `shi`.`counselor_user_id` = '" . $sessionUserObject['user_id'] . "'";
                                                                                    }

                                                                                    $query .= "ORDER BY `shi`.`students_has_intakes_id` DESC";

                                                                                    $results = Database::search($query);
                                                                                    ?>
                                                                                    <tbody id="students-table-data" data-student-count="<?= $results->num_rows ?>">
                                                                                        <?php

                                                                                        $pageno = 1;
                                                                                        $results_count = $results->num_rows;
                                                                                        $result_per_page = 10;

                                                                                        if ($results_count > $result_per_page) {
                                                                                            $number_of_pages = ceil($results_count / $result_per_page);
                                                                                        } else {
                                                                                            $number_of_pages = 1;
                                                                                        }

                                                                                        $page_results = ($pageno - 1) * $result_per_page;
                                                                                        $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                                        if ($users->num_rows > 0) {
                                                                                            while ($data = $users->fetch_assoc()) {
                                                                                        ?>
                                                                                                <tr id="student-data-row">
                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["shiId"]; ?>" onchange="checkRowEvents(<?= $data['shiId']; ?>);">
                                                                                                            <?php
                                                                                                            if (!empty($data["profile_img"])) {
                                                                                                                $fileUrl = './assets/imgs/students/' . $data["profile_img"];
                                                                                                                if (!file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                                    $fileUrl = Project::getBasePortalUrl() . '/assets/imgs/students/' . $data["profile_img"];
                                                                                                                }
                                                                                                            ?>
                                                                                                                <img src="<?php echo $fileUrl; ?>" class="hwr-40" alt="Students image">
                                                                                                            <?php
                                                                                                            } else {
                                                                                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                            ?>
                                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                            <?php
                                                                                                            }
                                                                                                            ?>
                                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["email"]; ?></span></td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["mobile"]; ?></span></td>
                                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo date("Y-m-d h:i A", strtotime($data["addmission_date"])); ?></span></td>

                                                                                                    <td class="py-2 align-middle">
                                                                                                        <?php
                                                                                                        $status_classes = [
                                                                                                            "1" => "table-btn-success",
                                                                                                            "2" => "table-btn-danger",
                                                                                                            "3" => "table-btn-pink",
                                                                                                        ];
                                                                                                        $sbutton_class = $status_classes[$data["status_id"]] ?? '';
                                                                                                        ?>
                                                                                                        <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["status"]; ?></button>
                                                                                                    </td>

                                                                                                    <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id) || UserPermissions::hasPermission('intake_students', 10, $id)) { ?>
                                                                                                        <td class="py-3 align-middle">
                                                                                                            <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                            <div class="dropdown">
                                                                                                                <ul class="dropdown-menu border-0 shadow br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                                    <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="sendStudentTimeTable('<?php echo $data['shiId']; ?>')">
                                                                                                                            <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                            Send Time Table</a></li>
                                                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php if (!empty($data['verification']) && empty($data['reset_time'])) { ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                                                                                <iconify-icon icon="lets-icons:send-fill" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Resend Registration Link</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php } else { ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="resendStudentRegistrationEmail('<?php echo $data['sId']; ?>')">
                                                                                                                                <iconify-icon icon="solar:password-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Resend Password Reset Link</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php
                                                                                                                    }
                                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 10, $id)) {
                                                                                                                    ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="edit-intake-student?student-id=<?= $data['shiId']; ?>">
                                                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Edit</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                    <?php
                                                                                                                    }
                                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id)) {
                                                                                                                    ?>
                                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payments?student-id=<?= $data['shiId']; ?>">
                                                                                                                                <iconify-icon icon="iconoir:wallet" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                Payments</a></li>
                                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                        <?php
                                                                                                                    }
                                                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 4, $id)) {
                                                                                                                        if ($data["status_id"] == "1") {
                                                                                                                        ?>
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateIntakeStudentStatus(2,<?= $id ?>,'<?php echo $data['shiId']; ?>')">
                                                                                                                                    <iconify-icon icon="lsicon:minus-filled" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Deactivate</a></li>
                                                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="openDiscontinuConfirmModal(3,<?= $id ?>,'<?php echo $data['shiId']; ?>')">
                                                                                                                                    <iconify-icon icon="fluent-mdl2:leave-user" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Discontinue</a></li>
                                                                                                                        <?php
                                                                                                                        } else if ($data["status_id"] == "2") {
                                                                                                                        ?>
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="updateIntakeStudentStatus(1,<?= $id ?>,'<?php echo $data['shiId']; ?>')">
                                                                                                                                    <iconify-icon icon="dashicons:plus-alt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Activate</a></li>
                                                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" onclick="openDiscontinuConfirmModal(3,<?= $id ?>,'<?php echo $data['shiId']; ?>')">
                                                                                                                                    <iconify-icon icon="fluent-mdl2:leave-user" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    Discontinu</a></li>
                                                                                                                    <?php
                                                                                                                        }
                                                                                                                    }
                                                                                                                    ?>
                                                                                                                </ul>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    <?php } ?>
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
                                                                                        <?php } ?>
                                                                                    </tbody>
                                                                                </table>
                                                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container3">
                                                                                    <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                                        <div class="row p-4 align-items-center">
                                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'previousPage');">
                                                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                                <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                            </button>
                                                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                                <ul class="pagination-list d-flex mb-0">
                                                                                                    <?php
                                                                                                    $shudderPages1 = false;
                                                                                                    $shudderPages2 = false;

                                                                                                    function renderPageButton($id, $i, $isActive = false)
                                                                                                    {
                                                                                                        $activeClass = $isActive ? 'active' : '';
                                                                                                        $action = $isActive ? '' : "onclick=\"handleIntakeHasStudentPagination('{$id}','goToPage', '{$i}')\"";
                                                                                                        echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                                                    }

                                                                                                    for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                                        if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                                            renderPageButton($id, $i, $pageno == $i);
                                                                                                        } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                                                                            $shudderPages1 = true;
                                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                        } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                                                                            $shudderPages2 = true;
                                                                                                            echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                        }
                                                                                                    }
                                                                                                    ?>
                                                                                                </ul>
                                                                                            </div>
                                                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleIntakeHasStudentPagination(<?= $id ?>,'nextPage',<?= $number_of_pages ?>);">
                                                                                                <span class="text-14 main-text fw-semibold">Next</span>
                                                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
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
                                                    </div>
                                                <?php
                                                }

                                                // ===================== PAYMENTS =====================
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id)) {
                                                ?>
                                                    <div class="accordion-item mb-3 border-0">
                                                        <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-pink">
                                                            <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseThree" aria-expanded="false" aria-controls="flush-collapseThree">
                                                                <span class="sub-topic flex-grow-1">Payments</span>
                                                            </button>
                                                            <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 2, $id)) { ?>
                                                                <div class="d-flex align-items-center w-15">
                                                                    <div class="dropdown w-100">
                                                                        <div class="d-flex align-items-center justify-content-center custom-btn fw-normal w-100 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                                            Actions
                                                                        </div>
                                                                        <ul class="dropdown-menu w-100" style="min-width: max-content;">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-payment-plans?intake-id=<?= $id ?>">
                                                                                    <iconify-icon icon="fluent-mdl2:plan-view" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                    Intake Payment Plans</a></li>
                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="assign-payment-plan-to-students?intake-id=<?= $id ?>">
                                                                                    <iconify-icon icon="gg:assign" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                    Assign Payment Plan to Students</a></li>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php } ?>
                                                        </h2>
                                                        <div id="flush-collapseThree" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                            <div class="accordion-body p-4">
                                                                <div class="col-12 p-0 component-theme">
                                                                    <div class="row p-0 m-0 g-0">
                                                                        <div class="col-12 mb-4">
                                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                                <div class="col-12 col-md-2 d-flex flex-column">
                                                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                        <?php
                                                                                        $query = "SELECT
                                                                                    `cp`.`course_payments_id`,
                                                                                    `ps`.`payment_status`,
                                                                                    `ps`.`payment_status_id`,
                                                                                    `cp`.`due_date`,
                                                                                    `cp`.`amount`,
                                                                                    `cp`.`discount`,
                                                                                    `cp`.`discount_type_discount_type_id`,
                                                                                    `shi`.`student_id`,
                                                                                    `ld`.`first_name`,
                                                                                    `ld`.`last_name`,
                                                                                    `t`.`title`,
                                                                                    `s`.`profile_img`,
                                                                                    `c`.`code`,
                                                                                    `c`.`decimal_places`,
                                                                                    `pt`.`payment_type`,
                                                                                    IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                                    IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                                                                    IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                                    IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                                FROM `course_payments` AS `cp`
                                                                                INNER JOIN `payment_status` AS `ps` 
                                                                                    ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                                INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
                                                                                    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                                                                                INNER JOIN `payment_category` AS `pc`
                                                                                    ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                                                                                INNER JOIN `currencies` AS `c`
                                                                                    ON `pc`.`currencies_currency_id` = `c`.`currency_id`
                                                                                INNER JOIN `payment_type` AS `pt`
                                                                                    ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`    
                                                                                INNER JOIN `students_has_intakes` AS `shi` 
                                                                                    ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                                                INNER JOIN `students` AS `s` 
                                                                                    ON `shi`.`students_student_id` = `s`.`student_id`
                                                                                INNER JOIN `login_details` AS `ld`
                                                                                    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                                                                                INNER JOIN `title` AS `t` 
                                                                                    ON `s`.`title_title_id` = `t`.`title_id`
                                                                                LEFT JOIN (
                                                                                    SELECT
                                                                                        course_payments_course_payments_id,
                                                                                        SUM(amount) AS panelty_amount
                                                                                    FROM panelty_payments
                                                                                    GROUP BY course_payments_course_payments_id
                                                                                ) pp
                                                                                    ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                                                LEFT JOIN (
                                                                                    SELECT
                                                                                        course_payments_course_payments_id,
                                                                                        SUM(paid_amount) AS paid_amount,
                                                                                        SUM(discount_amount) AS total_discount,
                                                                                        SUM(panelty_amount) AS total_sub_panelty_amount
                                                                                    FROM course_payment_has_payments
                                                                                    WHERE payment_status_payment_status_id IN (2,3)
                                                                                    GROUP BY course_payments_course_payments_id
                                                                                ) cpp
                                                                                    ON cp.course_payments_id = cpp.course_payments_course_payments_id 
                                                                                WHERE `shi`.`intakes_intake_id` = '" . $id . "'
                                                                                
                                                                                ";

                                                                                        if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                                                                            $query .= " AND `shi`.`counselor_user_id` = '" . $sessionUserObject['user_id'] . "'";
                                                                                        }

                                                                                        $query .= "
                                                                                                GROUP BY `cp`.`course_payments_id`
                                                                                                ORDER BY `cp`.`course_payments_id` DESC";

                                                                                        $results = Database::search($query);
                                                                                        ?>
                                                                                        <span class="text-14 dark-theme fw-medium"><?= $results->num_rows; ?> Awaiting Payments</span>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="col-12 <?= $isHasStudentActionsPermissions ? 'col-md-6 col-lg-7' : 'col-md-8 col-lg-9' ?> d-flex flex-column">
                                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="payment-search-text" onkeyup="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                    </div>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-1 col-lg-1' : 'col-12 col-md-2 col-lg-1' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#intakePaymentFilter" aria-expanded="false" aria-controls="intakePaymentFilter">
                                                                                        <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <div class="<?= $isHasStudentActionsPermissions ? 'col-6 col-md-3 col-lg-2' : 'd-none' ?> d-flex flex-column justify-content-end">
                                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="downloadIntakePaymentsReport(this,<?= $id; ?>,'<?= $intakeData['intake'] ?>')">
                                                                                        Payments
                                                                                        <iconify-icon icon="line-md:download-loop" width="24" height="24" class="text-white mx-2"></iconify-icon>
                                                                                    </button>
                                                                                </div>
                                                                                <div class="col-12 collapse collapse-horizontal" id="intakePaymentFilter">
                                                                                    <div class="row justify-content-end">
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-category-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                                <option selected value="0">- Payment Category -</option>
                                                                                                <?php
                                                                                                $paymentCategories = Database::search("SELECT * FROM `payment_category`");
                                                                                                while ($paymentCategory = $paymentCategories->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?= $paymentCategory['payment_category_id'] ?>"><?= $paymentCategory['payment_category'] ?></option>
                                                                                                <?php } ?>
                                                                                            </select>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-plan-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter'),loadPaymentTypes()">
                                                                                                <option selected value="0">- Payment Plans -</option>
                                                                                                <?php
                                                                                                $paymentPlans = Database::search("SELECT * FROM `payment_plan`");
                                                                                                while ($paymentPlan = $paymentPlans->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?= $paymentPlan['payment_plan_id'] ?>"><?= $paymentPlan['payment_plan'] ?></option>
                                                                                                <?php } ?>
                                                                                            </select>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-type-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                                <option selected value="0">- Payment Type -</option>
                                                                                                <?php
                                                                                                $paymentType = Database::search("SELECT * FROM `payment_type`");
                                                                                                while ($paymenttype = $paymentType->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?= $paymenttype['payment_type_id'] ?>"><?= $paymenttype['payment_type'] ?></option>
                                                                                                <?php } ?>
                                                                                            </select>
                                                                                        </div>
                                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                                            <select class="form-select custom-select" aria-label="Default select example" id="payment-status-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
                                                                                                <option selected value="0">- Payment Status -</option>
                                                                                                <?php
                                                                                                $paymentStatus = Database::search("SELECT * FROM `payment_status`");
                                                                                                while ($status = $paymentStatus->fetch_assoc()) {
                                                                                                ?>
                                                                                                    <option value="<?= $status['payment_status_id'] ?>"><?= $status['payment_status'] ?></option>
                                                                                                <?php } ?>
                                                                                            </select>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>

                                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="payment-show-result-selector" onchange="handleIntakeHasPaymentsPagination(<?= $id ?>,'filter')">
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
                                                                                <div class="table-responsive-wrapper">
                                                                                    <div class="table-responsive" id="payments-table-container">
                                                                                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                                                            <thead>
                                                                                                <tr class="br-t-5">
                                                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortByName')">
                                                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortById')">
                                                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                                            <span class="text-14 main-text fw-medium me-2">Registration Number</span>
                                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                        </div>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                                                        <span class="text-14 main-text fw-medium me-2">Payment Type</span>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortByDate')">
                                                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                                            <span class="text-14 main-text fw-medium me-2">Due Date</span>
                                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                        </div>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'sortByAmount')">
                                                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                                                            <span class="text-14 main-text fw-medium me-2">Amount</span>
                                                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                                        </div>
                                                                                                    </th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Balance Amount</th>
                                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                                                    <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $id)) { ?>
                                                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                                                    <?php } ?>
                                                                                                </tr>
                                                                                            </thead>
                                                                                            <tbody id="user-table-data">
                                                                                                <?php

                                                                                                $pageno = 1;
                                                                                                $results_count = $results->num_rows;
                                                                                                $result_per_page = 10;

                                                                                                $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                                                                                                $page_results = ($pageno - 1) * $result_per_page;

                                                                                                $query_paginated = $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results;
                                                                                                $users = Database::search($query_paginated);

                                                                                                if ($users->num_rows > 0) {
                                                                                                    while ($data = $users->fetch_assoc()) {
                                                                                                        $discountAmount = $data["discount"];
                                                                                                        if ($data["discount_type_discount_type_id"] == 3) {
                                                                                                            $discountAmount = $data["amount"] / 100 * $data["discount"];
                                                                                                        }
                                                                                                        $payableAmount = $data["amount"] - $discountAmount - $data["total_discount"] + $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                                                        $balance = $payableAmount - $data["paid_amount"];
                                                                                                ?>
                                                                                                        <tr>
                                                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                                    <?php
                                                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                                                    ?>
                                                                                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                                                    <?php
                                                                                                                    } else {
                                                                                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                                                    ?>
                                                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                                                    <?php } ?>
                                                                                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                                                </div>
                                                                                                            </td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["payment_type"]; ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["due_date"] != '' ? date("Y-m-d", strtotime($data["due_date"])) : 'N/A'; ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"> <?= $data['code'] . " " . number_format($payableAmount, 00); ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data["paid_amount"], 00) ?></span></td>
                                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($balance, 00); ?></span></td>
                                                                                                            <td class="py-2 align-middle">
                                                                                                                <?php
                                                                                                                $status_classes = [
                                                                                                                    "0" => "table-btn-purple",
                                                                                                                    "1" => "table-btn-yellow",
                                                                                                                    "5" => "table-btn-green-blue",
                                                                                                                ];
                                                                                                                $sbutton_class = $status_classes[$data["payment_status_id"]] ?? '';
                                                                                                                ?>
                                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                                                                            </td>
                                                                                                            <?php if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 15, $id)) { ?>
                                                                                                                <td class="py-3 align-middle">
                                                                                                                    <div class="dropdown">
                                                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                                            <li>
                                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="intake-student-payment?payment-id=<?= $data['course_payments_id'] ?>">
                                                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                    View
                                                                                                                                </a>
                                                                                                                            </li>
                                                                                                                            <?php
                                                                                                                            if (
                                                                                                                                SessionUser::ifUserIsPro($sessionUserRole) ||
                                                                                                                                SessionUser::ifUserIsAcademicPro($sessionUserRole) ||
                                                                                                                                UserPermissions::validPermisisonType('payments', 0) || UserPermissions::validPermisisonType('intake_payments', 24, $id)
                                                                                                                            ) {
                                                                                                                                if ($balance > 0) {
                                                                                                                            ?>
                                                                                                                                    <li>
                                                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="add-receipt-to-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>">
                                                                                                                                            <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                            Add Reciept
                                                                                                                                        </a>
                                                                                                                                    </li>
                                                                                                                                <?php } ?>
                                                                                                                                <li>
                                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1" href="update-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>">
                                                                                                                                        <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                                        Edit
                                                                                                                                    </a>
                                                                                                                                </li>
                                                                                                                            <?php } ?>
                                                                                                                        </ul>
                                                                                                                    </div>
                                                                                                                </td>
                                                                                                            <?php } ?>
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
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </td>
                                                                                                    </tr>
                                                                                                <?php } ?>
                                                                                            </tbody>
                                                                                        </table>
                                                                                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container4">
                                                                                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                                                <div class="row p-4 align-items-center">
                                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'previousPage')">
                                                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                                                    </button>
                                                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                                                        <ul class="pagination-list d-flex mb-0">
                                                                                                            <?php
                                                                                                            $shudderPages1 = false;
                                                                                                            $shudderPages2 = false;

                                                                                                            function renderPaymentPageButton($catId, $i, $isActive = false)
                                                                                                            {
                                                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                                                $action = $isActive ? '' : "onclick=\"handleIntakeHasPaymentsPagination('{$catId}','goToPage', '{$i}')\"";
                                                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                                            }

                                                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                                                    renderPaymentPageButton($id, $i, $pageno == $i);
                                                                                                                } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                                                                                    $shudderPages1 = true;
                                                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                                } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                                                                                    $shudderPages2 = true;
                                                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                                                }
                                                                                                            }
                                                                                                            ?>
                                                                                                        </ul>
                                                                                                    </div>
                                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleIntakeHasPaymentsPagination(<?= $id ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                                                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
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
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }

                                                // ===================== ATTENDANCE =====================
                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsMarketingSRO($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1, $id)) {
                                                ?>
                                                    <div class="accordion-item mb-3 border-0">
                                                        <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-purple">
                                                            <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFour" aria-expanded="false" aria-controls="flush-collapseFour">
                                                                <span class="sub-topic">Attendance</span>
                                                            </button>
                                                        </h2>
                                                        <div id="flush-collapseFour" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                            <div class="accordion-body">
                                                                <div class="row p-0 m-0 g-0">

                                                                    <!-- FILTERS (intake is fixed, so only subject + period) -->
                                                                    <div class="col-12 mb-4 component-theme">
                                                                        <div class="row gy-3 align-items-end">
                                                                            <div class="col-12 col-md-3">
                                                                                <label class="text-14 main-text mb-2 d-block">Subject</label>
                                                                                <select id="bd-subject-selector" class="form-select custom-select" onchange="bdLoadBreakdown()">
                                                                                    <option value="0" selected>All Subjects</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-3">
                                                                                <label class="text-14 main-text mb-2 d-block">Range Type</label>
                                                                                <div class="bd-range-toggle">
                                                                                    <button type="button" id="bd-mode-month" class="custom-btn" onclick="bdSetMode('month')">Month</button>
                                                                                    <button type="button" id="bd-mode-range" class="custom-outline-btn" onclick="bdSetMode('range')">Date Range</button>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-12 col-md-4" id="bd-month-wrapper">
                                                                                <label class="text-14 main-text mb-2 d-block">Month</label>
                                                                                <input type="month" id="bd-month-input" class="form-control custom-input" value="<?= date('Y-m') ?>" onchange="bdLoadBreakdown()">
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-none" id="bd-range-wrapper">
                                                                                <label class="text-14 main-text mb-2 d-block">Date Range</label>
                                                                                <div class="d-flex gap-2">
                                                                                    <input type="date" id="bd-start-date" class="form-control custom-input" value="<?= date('Y-m-01') ?>" onchange="bdLoadBreakdown()">
                                                                                    <input type="date" id="bd-end-date" class="form-control custom-input" value="<?= date('Y-m-d') ?>" onchange="bdLoadBreakdown()">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-12 col-md-2">
                                                                                <button type="button" class="custom-btn w-100" onclick="bdLoadBreakdown()">
                                                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon> Refresh
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <span id="bd-filter-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                                                    </div>

                                                                    <!-- SUMMARY CARDS -->
                                                                    <div class="container-fluid p-0 mb-4">
                                                                        <div class="row g-3">
                                                                            <?php
                                                                            $bdCards = [
                                                                                ['id' => 'total-students', 'title' => 'Students',           'icon' => 'mdi:account-group-outline',  'period' => 'In this intake'],
                                                                                ['id' => 'lectures-held',  'title' => 'Lectures Held',      'icon' => 'mdi:calendar-check-outline', 'period' => 'In the selected period'],
                                                                                ['id' => 'avg-attendance', 'title' => 'Average Attendance', 'icon' => 'mdi:chart-line',             'period' => 'Present + Watched, across period'],
                                                                                ['id' => 'at-risk-count',  'title' => 'Needs Attention',    'icon' => 'mdi:alert-outline',          'period' => '3+ consecutive absences'],
                                                                            ];
                                                                            foreach ($bdCards as $card) { ?>
                                                                                <div class="col-12 col-md-6 col-lg-3">
                                                                                    <div class="stats-mini-card bl-solid">
                                                                                        <div class="stats-mini-top">
                                                                                            <div class="stats-mini-left">
                                                                                                <div class="stats-mini-icon"><iconify-icon icon="<?= $card['icon'] ?>" width="16" height="16"></iconify-icon></div>
                                                                                                <span class="stats-mini-title"><?= $card['title'] ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="stats-mini-body">
                                                                                            <div class="stats-mini-content">
                                                                                                <div class="stats-mini-value" id="bd-value-<?= $card['id'] ?>">-</div>
                                                                                                <div class="stats-mini-meta"><span class="stats-mini-period"><?= $card['period'] ?></span></div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            <?php } ?>
                                                                        </div>
                                                                    </div>

                                                                    <!-- CHARTS -->
                                                                    <div class="col-12 mb-4">
                                                                        <div class="row g-3">
                                                                            <div class="col-12 col-lg-8">
                                                                                <div class="p-4 component-theme h-100 bl-solid">
                                                                                    <div class="text-14 main-text fw-medium mb-1">Daily Attendance Trend</div>
                                                                                    <div class="text-12 sub-text mb-3">% of held lecture slots marked Present/Watched each day</div>
                                                                                    <div class="bd-chart-canvas-wrap"><canvas id="bd-trend-chart"></canvas></div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4">
                                                                                <div class="p-4 component-theme h-100 bl-solid">
                                                                                    <div class="text-14 main-text fw-medium mb-1">Status Breakdown</div>
                                                                                    <div class="text-12 sub-text mb-3">Present / Watched / Half Verify / Absent</div>
                                                                                    <div class="bd-chart-canvas-wrap"><canvas id="bd-status-chart"></canvas></div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- NEEDS ATTENTION (search + min absences + per page + pagination) -->
                                                                    <div class="col-12 p-4 mb-4 bd-at-risk-panel">
                                                                        <div class="row p-0 m-0 g-0 mb-3 align-items-center">
                                                                            <div class="col-auto"><iconify-icon icon="mdi:alert-circle-outline" width="22" height="22" class="text-dark-red"></iconify-icon></div>
                                                                            <div class="col">
                                                                                <span class="text-14 main-text fw-medium d-block">Needs Attention - Consecutive Absences</span>
                                                                                <span class="text-12 sub-text">Students whose most recent lectures in this period were all missed</span>
                                                                            </div>
                                                                        </div>

                                                                        <div class="row gy-3 gx-3 mb-3 align-items-end justify-content-end">
                                                                            <div class="col-12 col-md-5">
                                                                                <div class="h-100 d-flex align-items-center p-1 bl-solid component-theme">
                                                                                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pt-2 pb-2"></iconify-icon>
                                                                                    <input type="text" id="bd-risk-search" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search student name or ID..." oninput="PagedTable.get('bdRisk').refresh()">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-6 col-md-3">
                                                                                <select id="bd-risk-min" class="form-select custom-select" onchange="PagedTable.get('bdRisk').refresh()">
                                                                                    <option value="3" selected>3+ absences</option>
                                                                                    <option value="5">5+ absences</option>
                                                                                    <option value="7">7+ absences</option>
                                                                                    <option value="10">10+ absences</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-6 col-md-4 d-flex align-items-center justify-content-end">
                                                                                <label class="pe-2 sub-text">Results Per Page</label>
                                                                                <select id="bd-risk-per-page" class="form-select custom-select w-25" onchange="PagedTable.get('bdRisk').refresh()">
                                                                                    <option value="5">5</option>
                                                                                    <option value="10" selected>10</option>
                                                                                    <option value="25">25</option>
                                                                                    <option value="50">50</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>

                                                                        <div class="table-responsive">
                                                                            <table class="table bl-solid m-0 p-0 g-0">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th class="px-4 py-3 bg-table-header main-text fw-medium">Student</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Student ID</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Consecutive Absences</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Last Attended</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="bd-at-risk-body">
                                                                                    <tr>
                                                                                        <td colspan="4" class="text-center py-4 text-14 bd-at-risk-empty">Loading...</td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="bd-risk-pager"></div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- PER STUDENT (search + attendance band + sort + per page + pagination) -->
                                                                    <div class="col-12 component-theme">
                                                                        <div class="row p-0 m-0 g-0 mb-3">
                                                                            <span class="text-14 main-text fw-medium">Per-Student Breakdown</span>
                                                                            <span class="text-12 sub-text">Filter, sort and page through every student</span>
                                                                        </div>

                                                                        <div class="row gy-3 gx-3 mb-3 align-items-end justify-content-end">
                                                                            <div class="col-12 col-md-4">
                                                                                <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                                    <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pt-2 pb-2"></iconify-icon>
                                                                                    <input type="text" id="bd-stu-search" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search student name or ID..." oninput="PagedTable.get('bdStu').refresh()">
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-6 col-md-3">
                                                                                <select id="bd-stu-band" class="form-select custom-select" onchange="PagedTable.get('bdStu').refresh()">
                                                                                    <option value="all" selected>- All Attendance -</option>
                                                                                    <option value="lt50">Below 50%</option>
                                                                                    <option value="50to75">50% - 74%</option>
                                                                                    <option value="75to90">75% - 89%</option>
                                                                                    <option value="gte90">90% and above</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-6 col-md-2">
                                                                                <select id="bd-stu-sort" class="form-select custom-select" onchange="PagedTable.get('bdStu').refresh()">
                                                                                    <option value="low" selected>Lowest first</option>
                                                                                    <option value="high">Highest first</option>
                                                                                    <option value="name">Name A-Z</option>
                                                                                    <option value="absent">Most absent</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-3 d-flex align-items-center justify-content-end">
                                                                                <label class="pe-2 sub-text">Results Per Page</label>
                                                                                <select id="bd-stu-per-page" class="form-select custom-select w-25" onchange="PagedTable.get('bdStu').refresh()">
                                                                                    <option value="10" selected>10</option>
                                                                                    <option value="25">25</option>
                                                                                    <option value="50">50</option>
                                                                                    <option value="100">100</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>

                                                                        <div class="table-responsive">
                                                                            <table class="table bl-solid m-0 p-0 g-0">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th class="px-4 py-3 bg-table-header main-text fw-medium">Student</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Student ID</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Present</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Watched</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Half Verify</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Absent</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Lectures Held</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Attendance %</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="bd-table-body">
                                                                                    <tr>
                                                                                        <td colspan="8" class="text-center py-4 text-14 sub-text">Loading breakdown...</td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="bd-stu-pager"></div>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php
                                                }

                                                // ===================== UNIFORM STATUS BREAKDOWN =====================
                                                if ($intakeData["programme_id"] == 2 && (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('intake_payments', $id))) {
                                                    $iubCards = [
                                                        ['id' => 'total',             'title' => 'Eligible Students', 'icon' => 'mdi:account-group-outline',  'period' => 'With a uniform fee'],
                                                        ['id' => 'pending-payments',  'title' => 'Pending Payments',  'icon' => 'mdi:cash-clock',             'period' => 'Fee not fully paid'],
                                                        ['id' => 'payments-received', 'title' => 'Payments Received', 'icon' => 'mdi:cash-check',             'period' => 'Fee fully paid'],
                                                        ['id' => 'uniforms-pending',  'title' => 'Uniforms Pending',  'icon' => 'mdi:tshirt-crew-outline',    'period' => 'Not yet distributed'],
                                                        ['id' => 'uniforms-received', 'title' => 'Uniforms Received', 'icon' => 'mdi:check-decagram-outline', 'period' => 'Distribution confirmed'],
                                                        ['id' => 'collected',         'title' => 'Fee Collection',    'icon' => 'mdi:cash-multiple',          'period' => 'Total uniform fees paid'],
                                                    ];
                                                ?>
                                                    <div class="accordion-item mb-3 border-0">
                                                        <h2 class="accordion-header d-flex justify-content-between align-items-center border-0 p-4 table-btn-green-blue">
                                                            <button class="accordion-button collapsed border-0 py-3 px-0 bg-transparent shadow-none flex-grow-1 text-start" type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseFive" aria-expanded="false" aria-controls="flush-collapseFive">
                                                                <span class="sub-topic">Uniform Status Breakdown</span>
                                                            </button>
                                                        </h2>
                                                        <div id="flush-collapseFive" class="accordion-collapse collapse" data-bs-parent="#accordionFlush">
                                                            <div class="accordion-body">
                                                                <div class="row p-0 m-0 g-0">

                                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                                        <span class="text-14 sub-text lh-base d-block">Track uniform fee payments and confirm uniform distribution for eligible students in this intake</span>
                                                                    </div>

                                                                    <!-- FILTERS -->
                                                                    <div class="col-12 p-4 mb-4 component-theme">
                                                                        <div class="row gy-3 align-items-end">
                                                                            <div class="col-12 col-md-4">
                                                                                <label class="text-14 main-text mb-2 d-block">Search</label>
                                                                                <input type="text" id="iub-search" class="form-control custom-input" placeholder="Student name or ID" oninput="iubSearchInput()">
                                                                            </div>
                                                                            <div class="col-12 col-md-3">
                                                                                <label class="text-14 main-text mb-2 d-block">Payment Status</label>
                                                                                <select id="iub-payment-status" class="form-select custom-select" onchange="iubLoad()">
                                                                                    <option value="">All</option>
                                                                                    <option value="PENDING">Pending Payment</option>
                                                                                    <option value="RECEIVED">Payment Received</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-3">
                                                                                <label class="text-14 main-text mb-2 d-block">Uniform Status</label>
                                                                                <select id="iub-uniform-status" class="form-select custom-select" onchange="iubLoad()">
                                                                                    <option value="">All</option>
                                                                                    <option value="PENDING">Pending Uniform</option>
                                                                                    <option value="RECEIVED">Uniform Received</option>
                                                                                </select>
                                                                            </div>
                                                                            <div class="col-12 col-md-2">
                                                                                <button type="button" class="custom-btn w-100" onclick="iubLoad()">
                                                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon> Refresh
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <span id="iub-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                                                    </div>

                                                                    <!-- SUMMARY CARDS -->
                                                                    <div class="container-fluid p-0 mb-4">
                                                                        <div class="row g-3">
                                                                            <?php foreach ($iubCards as $c) { ?>
                                                                                <div class="col-12 col-md-6 col-lg-2">
                                                                                    <div class="stats-mini-card">
                                                                                        <div class="stats-mini-top">
                                                                                            <div class="stats-mini-left">
                                                                                                <div class="stats-mini-icon"><iconify-icon icon="<?= htmlspecialchars($c['icon']) ?>" width="16" height="16"></iconify-icon></div>
                                                                                                <span class="stats-mini-title"><?= htmlspecialchars($c['title']) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="stats-mini-body">
                                                                                            <div class="stats-mini-content">
                                                                                                <div class="stats-mini-value" id="iub-value-<?= $c['id'] ?>">-</div>
                                                                                                <div class="stats-mini-meta"><span class="stats-mini-period"><?= htmlspecialchars($c['period']) ?></span></div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            <?php } ?>
                                                                        </div>
                                                                    </div>

                                                                    <!-- CHARTS -->
                                                                    <div class="col-12 mb-4">
                                                                        <div class="row g-3">
                                                                            <div class="col-12 col-lg-4">
                                                                                <div class="p-4 component-theme h-100">
                                                                                    <div class="text-14 main-text fw-medium mb-3">Payment Status</div>
                                                                                    <div class="ub-chart-wrap"><canvas id="iub-payment-chart"></canvas></div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4">
                                                                                <div class="p-4 component-theme h-100">
                                                                                    <div class="text-14 main-text fw-medium mb-3">Uniform Distribution</div>
                                                                                    <div class="ub-chart-wrap"><canvas id="iub-uniform-chart"></canvas></div>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-12 col-lg-4">
                                                                                <div class="p-4 component-theme h-100">
                                                                                    <div class="text-14 main-text fw-medium mb-3">Collected vs Outstanding</div>
                                                                                    <div class="ub-chart-wrap"><canvas id="iub-fee-chart"></canvas></div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- TABLE (count + results per page + pagination) -->
                                                                    <div class="col-12 p-4 component-theme">
                                                                        <div class="row gy-3 gx-3 mb-3 align-items-center justify-content-between">
                                                                            <div class="col-6 col-md-3">
                                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                    <span class="text-14 dark-theme fw-medium" id="iub-count">0 Students</span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="col-6 col-md-4 d-flex align-items-center justify-content-end">
                                                                                <label class="pe-2 sub-text">Results Per Page</label>
                                                                                <select id="iub-per-page" class="form-select custom-select w-25" onchange="PagedTable.get('iubStudents').refresh()">
                                                                                    <option value="10" selected>10</option>
                                                                                    <option value="25">25</option>
                                                                                    <option value="50">50</option>
                                                                                    <option value="100">100</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>

                                                                        <div class="table-responsive" style="overflow-x:auto">
                                                                            <table class="table bl-solid m-0 p-0 g-0">
                                                                                <thead>
                                                                                    <tr class="br-t-5">
                                                                                        <th class="px-4 py-3 bg-table-header main-text fw-medium">Student</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium">Student ID</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Scheduled</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Paid</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Outstanding</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Payment</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Uniform</th>
                                                                                        <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody id="iub-table-body">
                                                                                    <tr>
                                                                                        <td colspan="9" class="text-center py-4 text-14 sub-text">Open this section to load...</td>
                                                                                    </tr>
                                                                                </tbody>
                                                                            </table>
                                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="iub-pager"></div>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
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
                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake', 2, $id)) {
                    ?>
                        <!-- level Add Model => Start -->
                        <div class="modal fade br-0" id="addLevel" aria-hidden="true" aria-labelledby="addLevelLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="addLevelLabel">Add Level</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Level Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="level-name">
                                                    <span class="text-12 text-dark-red" id="al-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Level Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="level cover" id="level-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 col-xl-3 d-flex mt-3 mt-lg-0">
                                                            <div class="d-flex flex-column justify-content-end g-0 p-0 m-0 flex-grow-1">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Upload your level cover here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-1 mt-md-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('cover-input','level-cover');">
                                                                        Upload Image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                    </button>
                                                                    <input type="file" id="cover-input" class="d-none" accept="image/*" />
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-5 col-xl-6 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" onclick="addLevel(this,<?= $id; ?>)">Save Level</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="al-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Level Add Model => End -->
                    <?php
                    }
                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake', 10, $id)) {
                    ?>
                        <!-- Level Edit Modal => Start -->
                        <div class="modal fade br-0" id="editLevel" aria-hidden="true" aria-labelledby="editLevelLabel" tabindex="-1">
                            <div class="modal-dialog modal-xl modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="editLevelLabel">Edit Level</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div class="col-12 p-0">
                                            <div class="row p-0 gy-3 gx-3 align-content-center">
                                                <div class="col-12 d-flex flex-column">
                                                    <label for="" class="text-14 main-text required">Level Name</label>
                                                    <input type="text" class="form-control custom-input mt-2" id="edit-level-name">
                                                    <span class="text-12 text-dark-red" id="el-name-feedback"></span>
                                                </div>
                                                <div class="col-12 d-flex flex-column justify-content-end">
                                                    <label for="" class="text-14 main-text required">Level Cover</label>
                                                    <div class="row gx-4 mt-2 d-flex align-items-stretch">
                                                        <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                                                            <img src="./assets/imgs/courses-thumbnails/image.png" class="w-100 h-auto" alt="edit level cover" id="edit-level-cover">
                                                        </div>
                                                        <div class="col-12 col-lg-4 d-flex mt-3 mt-lg-0">
                                                            <div class="justify-content-center g-0 p-0 m-0 flex-grow-1 d-flex flex-column">
                                                                <div class="col-12">
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Upload your payment slip here.</p>
                                                                    <p class="text-14 sub-topic g-0 p-0 m-0">Important guidelines:</p>
                                                                    <p class="text-14 sub-text g-0 p-0 m-0">Supported format:</p>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpeg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.jpg</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">or</span>
                                                                    <span class="text-14 sub-topic g-0 p-0 m-0">.png</span>
                                                                </div>
                                                                <div class="col-12 d-flex justify-content-end mt-3">
                                                                    <button class="custom-btn d-flex align-items-center justify-content-center w-100" onclick="uploadCover('edit-cover-input','edit-level-cover');">
                                                                        Select another image
                                                                        <iconify-icon icon="ic:sharp-upload" width="20" height="20" class="mx-3"></iconify-icon>
                                                                        <input type="file" id="edit-cover-input" class="d-none" accept="image/*" />
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-lg-5 mt-3 mt-lg-0 d-flex align-items-md-end justify-content-end">
                                                            <button class="col-12 col-lg-8 custom-btn m-0" id="save-edit">Save Changes</button>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red" id="el-cover-feedback"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Level Edit Modal => End -->
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 1, $id)) {
                    ?>
                        <div class="modal fade br-0" id="csvDownload" aria-hidden="true" aria-labelledby="csvDownloadLabel" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                                <div class="modal-content p-4">
                                    <div class="modal-header p-0 border-0">
                                        <h1 class="modal-title text-16 text-dark-color" id="csvDownloadLabel">Download Students CSV</h1>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body p-0 mt-3">
                                        <div id="waitingMessage" class="text-center">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <h3 class="mt-3">Preparing Your CSV File...</h3>
                                            <p class="text-muted">Please wait while we generate your download.</p>
                                        </div>

                                        <div id="successMessage" class="text-center d-none">
                                            <div class="checkmark-circle">
                                                <div class="checkmark"></div>
                                            </div>
                                            <h3 class="mt-3">Download Ready!</h3>
                                            <p class="text-muted">Your CSV file has been successfully generated.</p>
                                            <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                    }

                    if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake', 2, $id) || UserPermissions::hasPermission('intake', 10, $id)) {
                    ?>
                        <!-- cover crop modal => Start -->
                        <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Crop Image</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center d-flex  align-items-center justify-content-center">
                                        <img id="image" class="img-fluid bg-black" style="width: 75%;height: auto;" />
                                    </div>
                                    <div class="modal-footer">
                                        <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- cover crop modal => End -->
                    <?php
                    }
                    ?>

                    <!-- Student status change modal (Activate / Deactivate / Discontinue, single or bulk).
                         Reason + note are saved to students_has_intakes_logs. Behaviour: intakeStudentStatus.js -->
                    <div class="modal fade" id="studentStatusModal" tabindex="-1" aria-labelledby="studentStatusModalTitle" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow">
                                <div class="d-flex justify-content-end pe-3 pt-3 position-relative">
                                    <button type="button" class="btn-close text-end" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-4 pt-0">
                                    <div class="text-center mb-3">
                                        <iconify-icon id="ss-icon" icon="mdi:account-sync-outline" width="48" height="48" class="dark-theme"></iconify-icon>
                                        <h5 class="fw-semibold mt-2 mb-1" id="studentStatusModalTitle">Change Student Status</h5>
                                        <p class="text-muted text-14 mb-0" id="ss-summary">Checking selected students...</p>
                                    </div>

                                    <div class="alert alert-warning text-start small mb-3 d-none" id="ss-discontinue-warning">
                                        <div class="d-flex align-items-start gap-2">
                                            <iconify-icon icon="mdi:alert-outline" width="20"></iconify-icon>
                                            <div>
                                                <strong>This action will:</strong>
                                                <ul class="mb-0 ps-3">
                                                    <li>Discontinue the student(s) from the selected intake</li>
                                                    <li>They will no longer be considered active students in this intake</li>
                                                    <li>This action <strong>cannot be undone</strong></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-12 sub-text mb-3 d-none" id="ss-skipped"></div>

                                    <div class="d-flex flex-column mb-3 d-none" id="ss-deactivate-date-wrap">
                                        <label class="text-14 main-text required" for="ss-deactivate-date">Deactivate Date</label>
                                        <input type="date" class="form-control custom-input mt-2" id="ss-deactivate-date">
                                        <span class="text-12 sub-text mt-1" id="ss-deactivate-date-help">Students are activated now and deactivated automatically on this date.</span>
                                        <span class="text-12 text-dark-red" data-ss-error="deactivateDate"></span>
                                    </div>

                                    <div class="d-flex flex-column mb-3 d-none" id="ss-reason-wrap">
                                        <label class="text-14 main-text required" for="ss-reason">Reason</label>
                                        <select class="form-select custom-select mt-2" id="ss-reason">
                                            <option value="">- Select Reason -</option>
                                        </select>
                                        <span class="text-12 text-dark-red" data-ss-error="reason"></span>
                                    </div>

                                    <div class="d-flex flex-column mb-2">
                                        <label class="text-14 main-text" for="ss-note" id="ss-note-label">Note</label>
                                        <textarea class="form-control custom-input mt-2" id="ss-note" rows="3" maxlength="2000" placeholder="Add any details about this status change"></textarea>
                                        <span class="text-12 text-dark-red" data-ss-error="note"></span>
                                    </div>

                                    <span class="text-12 text-dark-red d-block mb-2" id="ss-error"></span>

                                    <div class="d-flex justify-content-center gap-3 mt-3">
                                        <button type="button" class="btn btn-outline-secondary px-4 br-0" data-bs-dismiss="modal">Cancel</button>
                                        <button type="button" id="ss-confirm" class="btn btn-danger px-4 br-0" disabled>Confirm</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Uniform: confirm distribution modal -->
                    <div class="modal fade" id="iub-confirm-modal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content p-2">
                                <div class="modal-header">
                                    <h5 class="modal-title main-text">Confirm Uniform Distribution</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-14 main-text" id="iub-confirm-msg"></p>
                                    <label class="text-14 main-text mb-2 d-block" for="iub-note">Distribution notes</label>
                                    <textarea id="iub-note" class="form-control custom-input" rows="3" maxlength="500" placeholder="e.g. Collected in person, sizes issued..."></textarea>
                                    <span id="iub-confirm-error" class="text-dark-red text-12 d-block mt-2"></span>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="custom-outline-btn" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="custom-btn" id="iub-confirm-btn" onclick="iubSubmit()">Confirm</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Uniform: distribution details modal -->
                    <div class="modal fade" id="iub-details-modal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content p-2">
                                <div class="modal-header">
                                    <h5 class="modal-title main-text">Distribution Details</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body" id="iub-details-body"></div>
                                <div class="modal-footer"><button type="button" class="custom-outline-btn" data-bs-dismiss="modal">Close</button></div>
                            </div>
                        </div>
                    </div>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/fileChooser.js"></script>
                    <script src="./assets/js/intakeStudentStatus.js?v=<?= Project::getVersion() ?>"></script>
                    <script>
                        const INTAKE_ID = <?= (int)$id ?>;
                    </script>
                    <script>
                        class PagedTable {
                            static registry = {};
                            static get(key) {
                                return PagedTable.registry[key];
                            }

                            constructor(o) {
                                Object.assign(this, o);
                                this.all = [];
                                this.page = 1;
                                PagedTable.registry[o.key] = this;
                            }
                            perPage() {
                                return parseInt(document.getElementById(this.perPageId)?.value || 10, 10);
                            }
                            setData(rows) {
                                this.all = Array.isArray(rows) ? rows : [];
                                this.page = 1;
                                this.render();
                            }
                            refresh() {
                                this.page = 1;
                                this.render();
                            }
                            go(action, p) {
                                const pages = this.totalPages();
                                if (action === 'goToPage') this.page = parseInt(p, 10);
                                else if (action === 'previousPage' && this.page > 1) this.page--;
                                else if (action === 'nextPage' && this.page < pages) this.page++;
                                this.render();
                            }
                            filtered() {
                                let r = this.filterFn ? this.all.filter(this.filterFn) : this.all.slice();
                                if (this.sortFn) r.sort(this.sortFn);
                                return r;
                            }
                            totalPages() {
                                return Math.max(1, Math.ceil(this.filtered().length / this.perPage()));
                            }

                            render() {
                                const rows = this.filtered();
                                const per = this.perPage();
                                const pages = Math.max(1, Math.ceil(rows.length / per));
                                if (this.page > pages) this.page = pages;
                                const slice = rows.slice((this.page - 1) * per, this.page * per);

                                const body = document.getElementById(this.bodyId);
                                body.innerHTML = slice.length ?
                                    slice.map(this.renderRow).join('') :
                                    `<tr class="justify-content-center align-middle"><td colspan="${this.colspan}" class="align-middle">
                 <div class="d-flex align-items-center justify-content-center"><div class="no-data-state">
                 <div class="no-data-icon-wrap"><iconify-icon icon="ph:empty-bold"></iconify-icon></div>
                 <p class="no-data-text">No data</p></div></div></td></tr>`;

                                if (this.countId) {
                                    const el = document.getElementById(this.countId);
                                    if (el) el.textContent = `${rows.length} ${this.countLabel || 'Results'}`;
                                }
                                this.renderPager(pages);
                                if (this.onRender) this.onRender(rows);
                            }

                            renderPager(pages) {
                                const k = this.key;
                                let nums = '',
                                    d1 = false,
                                    d2 = false;
                                const dots = "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                for (let i = 1; i <= pages; i++) {
                                    if (i === 1 || i === pages || Math.abs(this.page - i) <= 2) {
                                        const active = i === this.page;
                                        nums += `<li class='page-item ${active ? 'active' : ''}'><button class='py-2 px-3' ${active ? '' : `onclick="PagedTable.get('${k}').go('goToPage',${i})"`}>${i}</button></li>`;
                                    } else if (!d1 && i < this.page - 2 && i > 1) {
                                        d1 = true;
                                        nums += dots;
                                    } else if (!d2 && i > this.page + 2 && i < pages) {
                                        d2 = true;
                                        nums += dots;
                                    }
                                }
                                document.getElementById(this.pagerId).innerHTML = `
        <nav class="col-12 px-2 pe-2 m-0 container-fluid"><div class="row p-4 align-items-center">
            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="PagedTable.get('${k}').go('previousPage')">
                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon><span class="text-14 main-text fw-semibold">Previous</span></button>
            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center"><ul class="pagination-list d-flex mb-0">${nums}</ul></div>
            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="PagedTable.get('${k}').go('nextPage')">
                <span class="text-14 main-text fw-semibold">Next</span>
                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon></button>
        </div></nav>`;
                            }
                        }

                        /* ============ Helpers ============ */
                        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;'
                        } [c]));
                        const val = (id) => (document.getElementById(id)?.value || '').trim();

                        /* ============ 1) ATTENDANCE: shared normalizer pieces ============ */
                        const bdAvatarHtml = (r) => r.img ?
                            `<img src="./assets/imgs/students/${esc(r.img)}" class="hwr-40" alt="">` :
                            `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${esc(r.initials)}</div>`;

                        const bdBase = (r) => ({
                            name: `${r.title ? r.title + '. ' : ''}${r.first_name || ''} ${r.last_name || ''}`.trim(),
                            sid: r.sId ?? '',
                            img: r.profile_img || '',
                            initials: (((r.first_name || '')[0] || '') + ((r.last_name || '')[0] || '')).toUpperCase()
                        });

                        /* ============ 1) ATTENDANCE: Needs Attention ============ */
                        const bdNormRisk = (r) => ({
                            ...bdBase(r),
                            streak: +r.absence_streak || 0,
                            last: r.last_attended_date ?
                                new Date(r.last_attended_date).toLocaleDateString('en-GB', {
                                    day: '2-digit',
                                    month: 'short'
                                }) : 'Never in period'
                        });

                        new PagedTable({
                            key: 'bdRisk',
                            bodyId: 'bd-at-risk-body',
                            pagerId: 'bd-risk-pager',
                            perPageId: 'bd-risk-per-page',
                            colspan: 4,
                            filterFn: (r) => {
                                const q = val('bd-risk-search').toLowerCase();
                                const min = parseInt(val('bd-risk-min') || 3, 10);
                                return r.streak >= min && (!q || `${r.name} ${r.sid}`.toLowerCase().includes(q));
                            },
                            sortFn: (a, b) => b.streak - a.streak,
                            renderRow: (r) => `<tr class="bd-at-risk-row">
        <td class="px-4 py-3 align-middle"><div class="d-flex align-items-center">${bdAvatarHtml(r)}
            <span class="text-14 main-text mx-2">${esc(r.name)}</span></div></td>
        <td class="py-2 align-middle text-14 main-text">${esc(r.sid)}</td>
        <td class="py-2 align-middle text-center"><span class="table-btn bd-streak-badge d-inline-flex py-1 px-2 text-12">${r.streak} in a row</span></td>
        <td class="py-2 align-middle text-center text-14 main-text">${esc(r.last)}</td></tr>`,
                        });

                        /* ============ 2) ATTENDANCE: Per-student ============ */
                        const bdNormStu = (r) => ({
                            ...bdBase(r),
                            present: +r.present_days || 0,
                            watched: +r.watched_days || 0,
                            half: +r.half_verify_days || 0,
                            absent: +r.absent_days || 0,
                            held: +r.lectures_held || 0,
                            pct: +r.attendance_pct || 0
                        });

                        new PagedTable({
                            key: 'bdStu',
                            bodyId: 'bd-table-body',
                            pagerId: 'bd-stu-pager',
                            perPageId: 'bd-stu-per-page',
                            colspan: 8,
                            filterFn: (r) => {
                                const q = val('bd-stu-search').toLowerCase();
                                if (q && !`${r.name} ${r.sid}`.toLowerCase().includes(q)) return false;
                                switch (val('bd-stu-band')) {
                                    case 'lt50':
                                        return r.pct < 50;
                                    case '50to75':
                                        return r.pct >= 50 && r.pct < 75;
                                    case '75to90':
                                        return r.pct >= 75 && r.pct < 90;
                                    case 'gte90':
                                        return r.pct >= 90;
                                    default:
                                        return true;
                                }
                            },
                            sortFn: (a, b) => {
                                switch (val('bd-stu-sort')) {
                                    case 'high':
                                        return b.pct - a.pct;
                                    case 'name':
                                        return a.name.localeCompare(b.name);
                                    case 'absent':
                                        return b.absent - a.absent;
                                    default:
                                        return a.pct - b.pct;
                                }
                            },
                            renderRow: (r) => {
                                const cls = r.pct >= 80 ? 'table-btn-success' : r.pct >= 65 ? 'table-btn-yellow' : 'table-btn-danger';
                                return `<tr>
        <td class="px-4 py-3 align-middle"><div class="d-flex align-items-center">${bdAvatarHtml(r)}
            <span class="text-14 main-text mx-2">${esc(r.name)}</span></div></td>
        <td class="py-2 align-middle text-14 main-text">${esc(r.sid)}</td>
        <td class="py-2 align-middle text-center text-14 main-text">${r.present}</td>
        <td class="py-2 align-middle text-center text-14 main-text">${r.watched}</td>
        <td class="py-2 align-middle text-center text-14 main-text">${r.half}</td>
        <td class="py-2 align-middle text-center text-14 main-text">${r.absent}</td>
        <td class="py-2 align-middle text-center text-14 main-text">${r.held}</td>
        <td class="py-2 align-middle text-center"><span class="table-btn ${cls} d-inline-flex py-1 px-2 text-12">${r.pct}%</span></td></tr>`;
                            },
                        });

                        /* Called from bdLoadBreakdown() in the attendance JS file */
                        function bdRenderAtRisk(list) {
                            PagedTable.get('bdRisk').setData((list || []).map(bdNormRisk));
                        }

                        function bdRenderStudents(list) {
                            PagedTable.get('bdStu').setData((list || []).map(bdNormStu));
                        }

                        /* ============ 3) UNIFORM STATUS BREAKDOWN (unchanged) ============ */
                        const IUB_PAYMENT_PAGE_URL = './add-receipt-to-student-payment.php?payment-id=';
                        const iubCharts = {};
                        let iubLoaded = false,
                            iubConfirmShiId = 0,
                            iubSearchTimer = null;

                        const iubEl = (id) => document.getElementById(id);
                        const iubMoney = (n) => Number(n || 0).toLocaleString('en-LK', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        const iubPost = (url, body) => fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(body)
                        }).then(r => r.json());

                        function iubFilters() {
                            return {
                                intake_id: INTAKE_ID,
                                payment_status: val('iub-payment-status'),
                                uniform_status: val('iub-uniform-status'),
                                search: val('iub-search'),
                            };
                        }

                        function iubSearchInput() {
                            clearTimeout(iubSearchTimer);
                            iubSearchTimer = setTimeout(iubLoad, 300);
                        }

                        function iubActionHtml(s) {
                            const item = (icon, label, attrs) => `
        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" ${attrs}>
            <iconify-icon icon="${icon}" width="20" height="20" class="sub-text me-3"></iconify-icon>${label}</a></li>`;
                            let items;
                            if (s.uniform_status === 'RECEIVED') {
                                items = item('mdi:eye-outline', 'View Details', `href="#" onclick="iubDetails(${s.shi_id}); return false;"`);
                            } else if (s.payment_status === 'PENDING') {
                                items = item('token:pay', 'Make Payment', `href="${IUB_PAYMENT_PAGE_URL}${s.course_payments_id}"`);
                            } else {
                                items = item('mdi:check-decagram-outline', 'Confirm Uniform Distribution', `href="#" onclick="iubOpenConfirm(${s.shi_id}); return false;"`);
                            }
                            return `<div class="dropdown">
        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
        <ul class="dropdown-menu dropdown-menu-end border-0 shadow br-5">${items}</ul></div>`;
                        }

                        new PagedTable({
                            key: 'iubStudents',
                            bodyId: 'iub-table-body',
                            pagerId: 'iub-pager',
                            perPageId: 'iub-per-page',
                            colspan: 9,
                            countId: 'iub-count',
                            countLabel: 'Students',
                            renderRow: (s) => {
                                const initials = ((s.first_name || '').charAt(0) + (s.last_name || '').charAt(0)).toUpperCase();
                                const avatar = s.profile_img ?
                                    `<img src="./assets/imgs/students/${esc(s.profile_img)}" class="hwr-40" alt="">` :
                                    `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${esc(initials)}</div>`;
                                const payOk = s.payment_status === 'RECEIVED';
                                const uniOk = s.uniform_status === 'RECEIVED';
                                return `<tr>
            <td class="px-4 py-3 align-middle"><div class="d-flex align-items-center">${avatar}
                <span class="text-14 main-text mx-2">${esc(s.title)}. ${esc(s.first_name)} ${esc(s.last_name)}</span></div></td>
            <td class="py-2 align-middle"><span class="text-14 main-text">${esc(s.sId)}</span></td>
            <td class="py-2 align-middle text-end text-14 main-text">${iubMoney(s.scheduled)}</td>
            <td class="py-2 align-middle text-end text-14 main-text">${iubMoney(s.paid)}</td>
            <td class="py-2 align-middle text-end text-14 main-text">${iubMoney(s.outstanding)}</td>
            <td class="py-2 align-middle text-center"><span class="table-btn ${payOk ? 'table-btn-success' : 'table-btn-yellow'} d-inline-flex py-1 px-2 text-12">${payOk ? 'Payment Received' : 'Pending Payment'}</span></td>
            <td class="py-2 align-middle text-center"><span class="table-btn ${uniOk ? 'table-btn-success' : 'table-btn-danger'} d-inline-flex py-1 px-2 text-12">${uniOk ? 'Uniform Received' : 'Pending Uniform'}</span></td>
            <td class="py-2 align-middle text-center">${iubActionHtml(s)}</td></tr>`;
                            },
                        });

                        function iubLoad() {
                            iubEl('iub-feedback').innerText = '';
                            iubEl('iub-table-body').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">Loading...</td></tr>';
                            iubPost('api/getUniformBreakdown.php', iubFilters()).then(data => {
                                if (data.error) {
                                    iubEl('iub-feedback').innerText = data.error;
                                    iubEl('iub-table-body').innerHTML = `<tr><td colspan="9" class="text-center py-4 text-14 sub-text">${esc(data.error)}</td></tr>`;
                                    return;
                                }
                                iubRenderCards(data.summary);
                                iubRenderCharts(data.summary);
                                PagedTable.get('iubStudents').setData(data.students || []);
                            }).catch(() => {
                                iubEl('iub-feedback').innerText = 'Something went wrong loading the breakdown. Please try again.';
                                iubEl('iub-table-body').innerHTML = '<tr><td colspan="9" class="text-center py-4 text-14 sub-text">Failed to load data.</td></tr>';
                            });
                        }

                        function iubRenderCards(s) {
                            iubEl('iub-value-total').innerText = s.total;
                            iubEl('iub-value-pending-payments').innerText = s.pending_payments;
                            iubEl('iub-value-payments-received').innerText = s.payments_received;
                            iubEl('iub-value-uniforms-pending').innerText = s.uniforms_pending;
                            iubEl('iub-value-uniforms-received').innerText = s.uniforms_received;
                            iubEl('iub-value-collected').innerText = iubMoney(s.collected);
                        }

                        function iubDrawChart(key, canvasId, type, labels, values, colors, extra) {
                            if (iubCharts[key]) {
                                iubCharts[key].data.datasets[0].data = values;
                                iubCharts[key].update();
                                return;
                            }
                            iubCharts[key] = new Chart(iubEl(canvasId).getContext('2d'), {
                                type,
                                data: {
                                    labels,
                                    datasets: [{
                                        data: values,
                                        backgroundColor: colors,
                                        borderWidth: 0
                                    }]
                                },
                                options: Object.assign({
                                    responsive: true,
                                    maintainAspectRatio: false
                                }, extra),
                            });
                        }

                        function iubRenderCharts(s) {
                            const legend = {
                                plugins: {
                                    legend: {
                                        position: 'bottom',
                                        labels: {
                                            boxWidth: 10,
                                            font: {
                                                size: 11
                                            }
                                        }
                                    }
                                }
                            };
                            iubDrawChart('pay', 'iub-payment-chart', 'doughnut', ['Payment Received', 'Pending Payment'], [s.payments_received, s.pending_payments], ['#22c55e', '#f59e0b'], legend);
                            iubDrawChart('uni', 'iub-uniform-chart', 'doughnut', ['Uniform Received', 'Pending Uniform'], [s.uniforms_received, s.uniforms_pending], ['#3b82f6', '#ef4444'], legend);
                            iubDrawChart('fee', 'iub-fee-chart', 'bar', ['Collected', 'Outstanding'], [s.collected, s.outstanding], ['#22c55e', '#ef4444'], {
                                scales: {
                                    y: {
                                        beginAtZero: true
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                },
                            });
                        }

                        function iubFind(shiId) {
                            return PagedTable.get('iubStudents').all.find(r => String(r.shi_id) === String(shiId));
                        }

                        function iubOpenConfirm(shiId) {
                            const s = iubFind(shiId);
                            if (!s) return;
                            iubConfirmShiId = shiId;
                            iubEl('iub-note').value = '';
                            iubEl('iub-confirm-error').innerText = '';
                            iubEl('iub-confirm-btn').disabled = false;
                            iubEl('iub-confirm-msg').innerText = `Confirm that ${s.first_name} ${s.last_name} (${s.sId}) has received their uniform? This cannot be undone.`;
                            bootstrap.Modal.getOrCreateInstance(iubEl('iub-confirm-modal')).show();
                        }

                        function iubSubmit() {
                            const btn = iubEl('iub-confirm-btn');
                            btn.disabled = true; // prevents double submit
                            iubPost('api/uniformDistribution.php', {
                                action: 'confirm',
                                shi_id: iubConfirmShiId,
                                note: iubEl('iub-note').value.trim(),
                            }).then(d => {
                                if (d.error) {
                                    showToast('error', 'Uniform distribution confirmation failed');
                                    iubEl('iub-confirm-error').innerText = d.error;
                                    btn.disabled = false;
                                    return;
                                }
                                showToast('success', 'Uniform distribution confirmed successfully');
                                bootstrap.Modal.getOrCreateInstance(iubEl('iub-confirm-modal')).hide();
                                iubLoad();
                            }).catch((e) => {
                                console.error(e);
                                iubEl('iub-confirm-error').innerText = 'Something went wrong. Please try again.';
                                btn.disabled = false;
                            });
                        }

                        function iubDetails(shiId) {
                            iubEl('iub-details-body').innerHTML = '<span class="text-14 sub-text">Loading...</span>';
                            bootstrap.Modal.getOrCreateInstance(iubEl('iub-details-modal')).show();
                            iubPost('api/uniformDistribution.php', {
                                action: 'details',
                                shi_id: shiId
                            }).then(d => {
                                if (d.error) {
                                    iubEl('iub-details-body').innerHTML = `<span class="text-14 text-dark-red">${esc(d.error)}</span>`;
                                    return;
                                }
                                const x = d.details;
                                iubEl('iub-details-body').innerHTML = `
            <div class="text-14 main-text mb-2"><strong>Confirmed by:</strong> ${esc(x.confirmed_by || '-')}</div>
            <div class="text-14 main-text mb-2"><strong>Date &amp; time:</strong> ${esc(x.confirmed_at)}</div>
            <div class="text-14 main-text"><strong>Notes:</strong><br>${esc(x.note || 'No notes').replace(/\n/g, '<br>')}</div>`;
                            }).catch(() => {
                                iubEl('iub-details-body').innerHTML = '<span class="text-14 text-dark-red">Could not load details.</span>';
                            });
                        }

                        /* Lazy-load when the accordion is first opened */
                        document.addEventListener('DOMContentLoaded', () => {
                            const el = document.getElementById('flush-collapseFive');
                            if (el) el.addEventListener('shown.bs.collapse', () => {
                                if (!iubLoaded) {
                                    iubLoaded = true;
                                    iubLoad();
                                }
                            });
                        });
                    </script>

                    <script>
                        /* =====================================================================
                         * PASTE INTO assets/js/functions.js — REPLACE the existing versions of:
                         *   openDiscontinuConfirmModal, updateIntakeStudentStatus,
                         *   applySelectedIntakeStudentUpdate, applyIntakeHasStudentsUpdate
                         * (delete the old ones; keeping both causes "Identifier already declared").
                         *
                         * Same names and arguments as before, so every existing onclick — including
                         * rows re-rendered by filterIntakeHasStudents() — now opens the status
                         * modal (reason + note + log) instead of changing the status straight away.
                         * The modal itself is in intakeStudentStatus.js (loaded by intake-details.php).
                         * ===================================================================== */

                        // Row action: Discontinue (was a confirm modal; now the status modal with reason)
                        const openDiscontinuConfirmModal = (update, intake, studentId) => {
                            issOpenStatusModal(intake, update, {
                                checked: [parseInt(studentId, 10)]
                            });
                        }

                        // Row action: Activate / Deactivate (one student). Doesn't touch the bulk selection.
                        const updateIntakeStudentStatus = (update, intakeId, id) => {
                            issOpenStatusModal(intakeId, update, {
                                checked: [parseInt(id, 10)]
                            });
                        }

                        // Bulk: uses the current selection (selectedUsers / unSelectedUsers).
                        const applySelectedIntakeStudentUpdate = (id, update) => {
                            if (update == 0) {
                                showToast("warning", "Please select the desired student status change option.");
                                return;
                            }
                            issOpenStatusModal(id, update, {
                                checked: typeof selectedUsers !== 'undefined' ? selectedUsers : [],
                                unchecked: typeof unSelectedUsers !== 'undefined' ? unSelectedUsers : [],
                            });
                        }

                        // Bulk "Apply" button next to the "- Update Students -" selector
                        function applyIntakeHasStudentsUpdate(id) {
                            applySelectedIntakeStudentUpdate(id, $("#update-selector").val());
                        }
                    </script>
                </body>

                </html>
<?php
            }
        }
    }
} ?>