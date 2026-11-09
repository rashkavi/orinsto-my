<?php

/**
 * Lecture Feedback Summary Report — Admin
 * -----------------------------------------
 * Modeled on your attendance-breakdown.php: same include pattern, same
 * component-theme / stats-mini-card / table markup and JS structure,
 * so it should drop straight into the same admin app shell.
 *
 * ASSUMPTIONS (schema I don't have — flagged so you can correct fast):
 * - `lecture_feedback` table: lecture_feedback_id, time_table_time_table_id,
 *   students_student_id, rating (1-10), remark, created_at
 * - Faculty/Programme/Intake resolution for a student re-uses the exact
 *   same join chain as attendance-breakdown.php:
 *   students_has_intakes -> intakes -> programmes -> faculties
 * - Subject/Lecture Type resolution re-uses the same chain as your
 *   lecture-schedule endpoint: time_table -> subjects -> lecture_type
 * - This report is NOT counselor-scoped (full admin view across all
 *   students) since nothing in the request said otherwise. If you want
 *   it scoped to "my assigned students" like attendance-breakdown, say
 *   so and I'll add the same counselor_id resolution block.
 */
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

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        $lecture_id = 0;

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecture-id']) && !empty($_GET['lecture-id'])) {
            $lecture_id = $_GET['lecture-id'];

            $query = "
            SELECT 
                `tt`.`time_table_id`,
                `lt`.`lecture_type`,
                `lt`.`lecture_type_id`,
                `tts`.`time_table_status_id`,
                `tts`.`time_table_status`,
                `f`.`faculty`,
                `p`.`programme`,
                `p`.`programme_id`,
                `i`.`intake_id`,
                `i`.`intake`,
                `l`.`level`,
                `l`.`level_id`,
                `s`.`subject_id`,
                `s`.`subject`,
                `s`.`subject_cover`,
                `s`.`subject_type_subject_type_id`,
                `tt`.`date`,
                `tt`.`start_time`,
                `tt`.`end_time`,
                `tt`.`lecture_title`,
                `u`.`user_fname`,
                `u`.`user_lname`,
                `u`.`profile_img`,
                `t`.`title`
            FROM `time_table` AS `tt`
            INNER JOIN `lecture_type` AS `lt`
                ON `tt`.`lecture_type_lecture_type_id` = `lt`.`lecture_type_id`
            INNER JOIN `time_table_status` AS `tts`
                ON `tt`.`time_table_status_time_table_status_id` = `tts`.`time_table_status_id`  
            INNER JOIN `lecturers` AS `lec`
                ON `tt`.`lecturers_lecturers_id` = `lec`.`lecturers_id`
            INNER JOIN `users` AS `u`
                ON `lec`.`users_user_id` = `u`.`user_id`
            INNER JOIN `title` AS `t`
                ON `u`.`title_title_id` = `t`.`title_id`
            INNER JOIN `subjects` AS `s`
                ON `lec`.`subjects_subject_id` = `s`.`subject_id`
            INNER JOIN `levels` AS `l`
                ON `s`.`levels_level_id` = `l`.`level_id`
            INNER JOIN `intakes` AS `i`
                ON `l`.`intakes_intake_id` = `i`.`intake_id`
         
            INNER JOIN `programmes` AS `p`
                ON `i`.`programmes_programme_id` = `p`.`programme_id`
          
            INNER JOIN `faculties` AS `f`
                ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
            WHERE 
             `tt`.`time_table_status_time_table_status_id` IN (1,5)
            AND `tt`.`time_table_id` = '" . $lecture_id . "'
                        
        ";
            $lecture = Database::search($query);

            if ($lecture->num_rows == 0) {
                header("Location: ./lecture-schedule");
            }
        }

        $timezone = new DateTimeZone("Asia/Colombo");
        $dateTime = new DateTime("now", $timezone);
        $today = $dateTime->format('Y-m-d');

        $defaultStart = $dateTime->format('Y-m-01');
        $defaultEnd   = $today;

        // ------------------------------------------------------------------
        // Faculty dropdown — full list (admin-wide report, unlike the
        // counselor-scoped one on attendance-breakdown.php).
        // Programme/Intake/Subject load via AJAX as the admin narrows down
        // (api/getLectureFeedbackFilterOptions.php).
        // ------------------------------------------------------------------
        $facultiesQuery = "SELECT faculty_id, faculty FROM faculties ORDER BY faculty ASC";
        $allFaculties = Database::search($facultiesQuery)->fetch_all(MYSQLI_ASSOC);

        $summaryCards = [
            [
                'id'    => 'total-feedback',
                'title' => 'Feedback Submitted',
                'icon'  => 'mdi:message-check-outline',
                'period' => 'In the selected period',
            ],
            [
                'id'    => 'pending-feedback',
                'title' => 'Pending Feedback',
                'icon'  => 'mdi:message-alert-outline',
                'period' => 'Completed lectures, no submission yet',
            ],
            [
                'id'    => 'response-rate',
                'title' => 'Response Rate',
                'icon'  => 'mdi:chart-donut',
                'period' => 'Submitted vs. eligible lectures',
            ],
            [
                'id'    => 'avg-rating',
                'title' => 'Average Rating',
                'icon'  => 'mdi:star-outline',
                'period' => 'Out of 10, across submissions',
            ],
        ];
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Lecture Feedback Report - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            <style>
                /* 5-tier rating badge — same colour scale as the student
                   feedback modal (Bad -> Absolutely Good), reusing your
                   existing .table-btn-* variants so it matches everywhere
                   else ratings/statuses show up in the admin UI. */
                .lfr-rating-badge {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    font-weight: 700;
                    border-radius: 999px;
                }

                .lfr-remark-cell {
                    max-width: 260px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: inline-block;
                    vertical-align: middle;
                }

                .lfr-remark-empty {
                    color: var(--sub-text);
                    font-style: italic;
                }

                .lfr-status-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 11px;
                    font-weight: 700;
                    padding: 4px 10px;
                    border-radius: 999px;
                }

                .lfr-status-pill.submitted {
                    background: var(--light-green);
                    color: var(--dark-green);
                }

                .lfr-status-pill.pending {
                    background: var(--light-red);
                    color: var(--dark-red);
                }

                .lfr-actions-dropdown .dropdown-toggle::after {
                    display: none;
                }

                .lfr-actions-dropdown .btn-three-dot {
                    width: 32px;
                    height: 32px;
                    border-radius: 8px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: transparent;
                    border: none;
                    color: var(--sub-text);
                }

                /* .lfr-actions-dropdown .btn-three-dot:hover {
                    background: var(--light-theme);
                    color: var(--dark-theme);
                } */

                #lfrViewModal .modal-content {
                    border: none;
                    border-radius: 14px;
                    overflow: hidden;
                }

                #lfrViewModal .modal-header {
                    background: linear-gradient(135deg, var(--gradient-start), var(--gradient-mid), var(--gradient-end));
                    color: #fff;
                    border: none;
                }

                #lfrViewModal .modal-header .btn-close {
                    filter: brightness(0) invert(1);
                }

                #lfrViewModal .lfr-detail-row {
                    display: flex;
                    justify-content: space-between;
                    gap: 20px;
                    padding: 12px 0;
                    border-bottom: 1px dashed var(--line-color);
                }

                #lfrViewModal .lfr-detail-row:last-child {
                    border-bottom: none;
                }

                #lfrViewModal .lfr-detail-label {
                    font-size: 13px;
                    color: var(--sub-text);
                    font-weight: 500;
                    flex: 0 0 40%;
                }

                #lfrViewModal .lfr-detail-value {
                    font-size: 14px;
                    color: var(--main-text-color);
                    font-weight: 700;
                    text-align: right;
                    flex: 1;
                }

                #lfrViewModal .lfr-remark-box {
                    background: var(--body-color);
                    border-radius: 8px;
                    padding: 14px;
                    font-size: 14px;
                    color: var(--main-text-color);
                    margin-top: 16px;
                    white-space: pre-wrap;
                    word-break: break-word;
                }
            </style>
        </head>

        <body class="overflow-hidden" onload="initializeLectureFeedbackReport()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        $lecture_id != 0 ? load_side_menu::load("lecture-schedule") : load_side_menu::load("lecture-feedbacks");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                $lecture_id != 0 ? selectItem::setSelected("lecture-schedule") : selectItem::setSelected("lecture-feedbacks");
                                ?>
                            </div>

                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>

                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Lecture Feedbacks</span>
                                                    <?php
                                                    if ($lecture_id == 0) {
                                                    ?>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="lecture-feedback-report-desc">
                                                            Analyze student feedback and monitor lecture performance with ease
                                                        </span>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./lecture-schedule" data-i18n="lecture-schedule">Lecture Schedule</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" data-i18n="">Lecture Feedback Report</a></li>
                                                            </ol>
                                                        </nav>
                                                    <?php
                                                    }
                                                    ?>

                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ============================ FILTER BAR ============================ -->
                                    <div class="col-12 mb-4 p-4 component-theme">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">

                                            <?php
                                            if ($lecture_id == 0) {
                                            ?>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                    <label class="text-14 main-text mb-2 d-block">Faculty</label>
                                                    <select id="lfr-faculty-selector" class="form-select custom-select" onchange="onLfrFacultyChanged(),loadLectureFeedbackReport(1)">
                                                        <option value="0" selected>All Faculties</option>
                                                        <?php foreach ($allFaculties as $fac) { ?>
                                                            <option value="<?= $fac["faculty_id"] ?>"><?= htmlspecialchars($fac["faculty"]) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                    <label class="text-14 main-text mb-2 d-block">Programme</label>
                                                    <select id="lfr-programme-selector" class="form-select custom-select" onchange="onLfrProgrammeChanged(),loadLectureFeedbackReport(1)">
                                                        <option value="0" selected>All Programmes</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                    <label class="text-14 main-text mb-2 d-block">Intake</label>
                                                    <select id="lfr-intake-selector" class="form-select custom-select" onchange="onLfrIntakeChanged(),loadLectureFeedbackReport(1)">
                                                        <option value="0" selected>All Intakes</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                    <label class="text-14 main-text mb-2 d-block">Subject</label>
                                                    <select id="lfr-subject-selector" class="form-select custom-select" onchange="onLfrSubjectChanged(),loadLectureFeedbackReport(1)">
                                                        <option value="0" selected>All Subjects</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                    <label class="text-14 main-text mb-2 d-block">Lecture</label>
                                                    <select id="lfr-lecture-selector" class="form-select custom-select" onchange="loadLectureFeedbackReport(1)">
                                                        <option value="0" selected>All Lectures</option>
                                                    </select>
                                                </div>
                                            <?php
                                            } else {
                                            ?>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none">
                                                    <label class="text-14 main-text mb-2 d-block">Faculty</label>
                                                    <select id="lfr-faculty-selector" class="form-select custom-select" onchange="onLfrFacultyChanged()">
                                                        <option value="0" selected>All Faculties</option>
                                                        <?php foreach ($allFaculties as $fac) { ?>
                                                            <option value="<?= $fac["faculty_id"] ?>"><?= htmlspecialchars($fac["faculty"]) ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none">
                                                    <label class="text-14 main-text mb-2 d-block">Programme</label>
                                                    <select id="lfr-programme-selector" class="form-select custom-select" onchange="onLfrProgrammeChanged()">
                                                        <option value="0" selected>All Programmes</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none">
                                                    <label class="text-14 main-text mb-2 d-block">Intake</label>
                                                    <select id="lfr-intake-selector" class="form-select custom-select" onchange="onLfrIntakeChanged()">
                                                        <option value="0" selected>All Intakes</option>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none">
                                                    <label class="text-14 main-text mb-2 d-block">Subject</label>
                                                    <select id="lfr-subject-selector" class="form-select custom-select" onchange="onLfrSubjectChanged()">
                                                        <option value="0" selected>All Subjects</option>
                                                    </select>
                                                </div>

                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end d-none">
                                                    <label class="text-14 main-text mb-2 d-block">Lecture</label>
                                                    <select id="lfr-lecture-selector" class="form-select custom-select">
                                                        <option value="<?= $lecture_id ?>" selected></option>
                                                    </select>
                                                </div>
                                            <?php
                                            }
                                            ?>

                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">Feedback Status</label>
                                                <select id="lfr-status-selector" class="form-select custom-select" onchange="loadLectureFeedbackReport(1)">
                                                    <option value="all" selected>All</option>
                                                    <option value="submitted">Submitted</option>
                                                    <option value="not_submitted">Not Submitted</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">Rating</label>
                                                <select id="lfr-rating-selector" class="form-select custom-select" onchange="loadLectureFeedbackReport(1)">
                                                    <option value="0" selected>All Ratings</option>
                                                    <?php for ($point = 1; $point <= 10; $point++) { ?>
                                                        <option value="<?= $point ?>"><?= $point ?> / 10</option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">Start Date</label>
                                                <input type="date" id="lfr-start-date" class="form-control custom-input" value="<?= $defaultStart ?>" onchange="loadLectureFeedbackReport(1)">
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">End Date</label>
                                                <input type="date" id="lfr-end-date" class="form-control custom-input" value="<?= $defaultEnd ?>" onchange="loadLectureFeedbackReport(1)">
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                <button type="button" class="custom-btn w-100" onclick="loadLectureFeedbackReport(1)">
                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon>
                                                    Refresh
                                                </button>
                                            </div>



                                        </div>
                                        <span id="lfr-filter-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                    </div>

                                    <!-- ============================ SUMMARY CARDS ============================ -->
                                    <div class="container-fluid p-0 mb-4">
                                        <div class="row g-3">
                                            <?php foreach ($summaryCards as $card) { ?>
                                                <div class="col-12 col-md-6 col-lg-3">
                                                    <div class="stats-mini-card">
                                                        <div class="stats-mini-top">
                                                            <div class="stats-mini-left">
                                                                <div class="stats-mini-icon">
                                                                    <iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="stats-mini-body">
                                                            <div class="stats-mini-content">
                                                                <div class="stats-mini-value" id="lfr-value-<?= $card['id'] ?>">-</div>
                                                                <div class="stats-mini-meta">
                                                                    <span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <!-- ============================ TABLE ============================ -->
                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0 mb-3 align-items-center">
                                            <div class="col">
                                                <span class="text-14 main-text fw-medium d-block">Feedback Submissions</span>
                                                <span class="text-12 sub-text">Newest first — use the filter panel above to isolate students who haven't responded yet</span>
                                            </div>
                                        </div>
                                        <div class="col-12 mb-4">
                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                    <div class="d-flex custom-div align-items-center justify-content-center">
                                                        <span class="text-14 dark-theme fw-medium" id="lfr-record-count">0 Records</span>
                                                    </div>
                                                </div>
                                                <div class="col-8 col-md-8 col-lg-10 d-flex flex-column">
                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search by student name or ID..." id="lfr-search-text" onkeyup="onLfrSearchKeyup()">
                                                    </div>
                                                </div>


                                                <div class="col-6 d-none d-lg-block"></div>
                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                    <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                    <select class="form-select custom-select w-25" id="lfr-per-page-selector" onchange="loadLectureFeedbackReport(1)">
                                                        <option value="10">10</option>
                                                        <option value="25" selected>25</option>
                                                        <option value="50">50</option>
                                                        <option value="100">100</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <span id="lfr-filter-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                        </div>
                                        <div class="table-responsive-wrapper">
                                            <table class="table bl-solid m-0 p-0 g-0" id="lfr-table">
                                                <thead>
                                                    <tr class="br-t-5">
                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 fw-medium">Student</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Student ID</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Faculty / Programme / Intake</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Subject / Lecture Type</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Status</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Rating</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Remark</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Submitted</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="lfr-table-body">
                                                    <tr id="lfr-table-loading">
                                                        <td colspan="9" class="text-center py-4 text-14 sub-text">Loading report...</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <div class="col-12 bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="lfr-pagination-container">
                                                <nav aria-label="Lecture feedback pagination" class="col-12 px-2 pe-2 m-0 container-fluid">
                                                    <div class="row p-4 align-items-center">
                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" id="lfr-prev-btn">
                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                        </button>
                                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                            <ul class="pagination-list d-flex mb-0" id="lfr-pagination-list"></ul>
                                                        </div>
                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" id="lfr-next-btn">
                                                            <span class="text-14 main-text fw-semibold">Next</span>
                                                            <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                        </button>
                                                    </div>
                                                </nav>
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

            <!-- ============================ VIEW SUBMISSION MODAL ============================ -->
            <div class="modal fade" id="lfrViewModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title mb-0">Feedback Submission</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="d-flex align-items-center mb-3" id="lfr-modal-student-block">
                                <div id="lfr-modal-avatar"></div>
                                <div class="ms-2">
                                    <div class="text-14 main-text fw-medium" id="lfr-modal-student-name">-</div>
                                    <div class="text-12 sub-text" id="lfr-modal-student-id">-</div>
                                </div>
                                <div class="ms-auto" id="lfr-modal-status-pill"></div>
                            </div>

                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Faculty</span>
                                <span class="lfr-detail-value" id="lfr-modal-faculty">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Programme</span>
                                <span class="lfr-detail-value" id="lfr-modal-programme">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Intake</span>
                                <span class="lfr-detail-value" id="lfr-modal-intake">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Subject</span>
                                <span class="lfr-detail-value" id="lfr-modal-subject">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Lecture Type</span>
                                <span class="lfr-detail-value" id="lfr-modal-lecture-type">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Lecture Date</span>
                                <span class="lfr-detail-value" id="lfr-modal-lecture-date">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Rating</span>
                                <span class="lfr-detail-value" id="lfr-modal-rating">-</span>
                            </div>
                            <div class="lfr-detail-row">
                                <span class="lfr-detail-label">Submitted On</span>
                                <span class="lfr-detail-value" id="lfr-modal-submitted-on">-</span>
                            </div>

                            <label class="text-14 main-text fw-medium mt-3 d-block">Remark</label>
                            <div class="lfr-remark-box" id="lfr-modal-remark">-</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="custom-outline-btn" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>


        </body>

        </html>
<?php
    }
}
?>