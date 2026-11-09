<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/UserPermissionsClass.php";
require_once "./includes/Project.php";
require_once "./api/automatedPaneltyaymentSchedule.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {

            $id = $_GET['student-id'];

            $studentHasIntake = Database::search("SELECT
                `faculty`,
                `faculty_id`,
                `programme`,
                `programme_id`,
                `intake_group`,
                `intake`,
                `intake_id`,
                `students_has_intakes`.`student_id`,
                `students`.`student_id` AS `admissionId`,
                `first_name`,
                `last_name`,
                `title`,
                `students_has_intakes_id`
            FROM `students_has_intakes`
            INNER JOIN `students` 
                ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `login_details`
                ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`    
            INNER JOIN `title` 
                ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `intakes` 
                ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
             
            INNER JOIN `programmes` 
                ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
            INNER JOIN `faculties` 
                ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
        ");


            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./students");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('course_overview'))) {
                    header("Location: ./faculties");
                } else {

                    $datetime = new DateTime('now', new DateTimeZone('Asia/Colombo'));
                    $formatedDateTime = $datetime->format('Y-m-d H:i:s');
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Batch Student Assignments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("students");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("students");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-table.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Student Assignments - Admission Number : <?= $studentHasIntakeData['admissionId'] ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='batch-details?batch-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake']; ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?php echo $studentHasIntakeData['title'] . "." . $studentHasIntakeData['first_name'] . " " . $studentHasIntakeData['last_name']; ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php

                                                $assignmentsExists = false;

                                                $subjectHasAssignments = Database::search("SELECT `assignment_id`,
                                                `assignment_code`,`assignment`,`attachment`,`assignments`.`start_date`,`assignments`.`end_date`,
                                                `assignment_status`,`assignment_status_assignment_status_id`,`subjects`.`subject` as `subject`
                                                FROM `assignments` 
                                                INNER JOIN `assignment_status` ON `assignments`.`assignment_status_assignment_status_id`=`assignment_status`.`assignment_status_id`
                                                INNER JOIN `subjects` ON `assignments`.`subjects_subject_id`=`subjects`.`subject_id`
                                                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                
                                                WHERE `intakes_intake_id` = '" . $studentHasIntakeData['intake_id'] . "' AND `assignment_status_assignment_status_id`!='1'
                                                AND `assignments`.`end_date` >= '" . $formatedDateTime . "'
                                                ORDER BY `assignment_id` DESC");

                                                if ($subjectHasAssignments->num_rows > 0) {
                                                    $assignmentsExists = true;
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <label class="text-16 main-text fw-bold mb-2">Upcoming & Ongoing Assignments</label>
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="students-assignments-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Assignment</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Start Date</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">End Date</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Duration</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Download</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Upload</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php

                                                                            if ($subjectHasAssignments->num_rows > 0) {
                                                                                while ($subjectHasAssignmentsData = $subjectHasAssignments->fetch_assoc()) {

                                                                                    $start = new DateTime($subjectHasAssignmentsData["start_date"], new DateTimeZone('Asia/Colombo'));
                                                                                    $end = new DateTime($subjectHasAssignmentsData["end_date"], new DateTimeZone('Asia/Colombo'));
                                                                                    $interval = $start->diff($end);

                                                                                    $days = $interval->days;
                                                                                    $hours = $interval->h;
                                                                                    $minutes = $interval->i;

                                                                                    $duration;

                                                                                    if ($days >= 30) {
                                                                                        $duration = floor($days / 30) . ' month' . (floor($days / 30) > 1 ? 's' : '');
                                                                                    } elseif ($days >= 7) {
                                                                                        $duration = floor($days / 7) . ' week' . (floor($days / 7) > 1 ? 's' : '');
                                                                                    } elseif ($days >= 1) {
                                                                                        $duration = $days . ' day' . ($days > 1 ? 's' : '');
                                                                                        if ($hours > 0) {
                                                                                            $duration .= ' ' . $hours . ' hour' . ($hours > 1 ? 's' : '');
                                                                                        }
                                                                                        $duration = $duration;
                                                                                    } else {
                                                                                        $duration = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
                                                                                    }

                                                                            ?>
                                                                                    <tr>
                                                                                        <td colspan="2" class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                    <iconify-icon icon="material-symbols-light:assignment-rounded" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                </div>
                                                                                                <span class="text-14 main-text mx-2"><?= $subjectHasAssignmentsData["assignment_code"] . "-" . $subjectHasAssignmentsData["assignment"] ?></span>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $subjectHasAssignmentsData["start_date"] ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $subjectHasAssignmentsData["end_date"] ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $duration ?></span></td>

                                                                                        <?php
                                                                                        if ($datetime > $start) {
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn table-btn-dark-theme active"
                                                                                                    onclick="downloadAssignment(
                                                        '<?= htmlspecialchars($subjectHasAssignmentsData['subject'] . '-' . $subjectHasAssignmentsData['assignment_code'] . '-' . $subjectHasAssignmentsData['assignment'], ENT_QUOTES) ?>',
                                                        '<?= htmlspecialchars($subjectHasAssignmentsData['attachment'], ENT_QUOTES) ?>'
                                                    )">
                                                                                                    Download
                                                                                                </button>
                                                                                            </td>
                                                                                            <?php
                                                                                            $studentHasAssignment = Database::search("SELECT `students_has_assignments_id` FROM `students_has_assignments`
                                                                                WHERE `assignments_assignment_id`='" . $subjectHasAssignmentsData['assignment_id'] . "' AND `students_student_id`='" . $studentHasIntakeData["admissionId"] . "'");
                                                                                            if ($studentHasAssignment->num_rows > 0) {
                                                                                            ?>
                                                                                                <td class="py-2 align-middle">
                                                                                                    <button class="table-btn table-btn-yellow">Submitted</button>
                                                                                                    <button class="table-btn table-btn-purple active mx-3" onclick=" window.location.href = 'submit-assignment.php?assignment-id=<?= $subjectHasAssignmentsData['assignment_id'] ?>'">Re Upload</button>
                                                                                                </td>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <td class="py-2 align-middle">
                                                                                                    <button class="table-btn table-btn-pink active" onclick=" window.location.href = 'submit-assignment.php?assignment-id=<?= $subjectHasAssignmentsData['assignment_id'] ?>'">Upload</button>
                                                                                                </td>
                                                                                            <?php
                                                                                            }
                                                                                        } else {
                                                                                            ?>
                                                                                            <td colspan="4" class="py-2 align-middle justify-content-center">
                                                                                                <div class="d-flex flex-row align-items-center justify-content-center">
                                                                                                    <button class="table-btn table-btn-purple">Upcoming</button>
                                                                                                </div>

                                                                                            </td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
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
                                                <?php
                                                }

                                                $lateSubmissionEnabledAssignments = Database::search("SELECT `assignment_id`,`assignment_code`,`assignment`,`attachment`,`start_date`,`end_date`,`assignment_status`,`assignment_status_assignment_status_id`,`late_submission_end_date`,`subject`
                                                FROM `assignments` 
                                                INNER JOIN `assignment_status` ON `assignments`.`assignment_status_assignment_status_id`=`assignment_status`.`assignment_status_id`
                                                 INNER JOIN `subjects` ON `assignments`.`subjects_subject_id`=`subjects`.`subject_id`
                                                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                               
                                                WHERE `intakes_intake_id` = '" . $studentHasIntakeData['intake_id'] . "' AND `assignment_status_assignment_status_id`!='1'
                                                AND `start_date` < '" . date("Y-m-d H:i:s") . "' 
                                                AND `end_date` < '" . $formatedDateTime . "' 
                                                AND `late_submission_end_date` >= '" . $formatedDateTime . "'
                                                ORDER BY `assignment_id` DESC");

                                                if ($lateSubmissionEnabledAssignments->num_rows > 0) {
                                                    $assignmentsExists = true;
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <label class="text-16 main-text fw-bold mb-2">Late Submission Enable Assignments</label>
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="students-assignments-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Assignment</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">End Date</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Remaining Time</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Download</th>
                                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Upload</th>

                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php

                                                                            if ($lateSubmissionEnabledAssignments->num_rows > 0) {
                                                                                while ($data = $lateSubmissionEnabledAssignments->fetch_assoc()) {

                                                                                    $end_date = new DateTime($data['late_submission_end_date'], new DateTimeZone('Asia/Colombo'));

                                                                                    $interval = $datetime->diff($end_date);
                                                                                    $remaining = $interval->format('%d days, %h hours, %i minutes, and %s seconds');

                                                                            ?>
                                                                                    <tr>
                                                                                        <td colspan="2" class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                    <iconify-icon icon="material-symbols-light:assignment-rounded" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                </div>
                                                                                                <span class="text-14 main-text mx-2"><?= $data["assignment_code"] . "-" . $data["assignment"] ?></span>
                                                                                            </div>
                                                                                        </td>

                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["late_submission_end_date"] ?></span></td>
                                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $remaining; ?></span></td>

                                                                                        <td class="py-2 align-middle">
                                                                                            <button class="table-btn table-btn-primary active"
                                                                                                onclick="downloadAssignment('<?= $data['subject'] . '-' . $data['assignment_code'] . '-' . $data['assignment'] ?>', '<?= $data['attachment'] ?>')">
                                                                                                Download
                                                                                            </button>
                                                                                        </td>
                                                                                        <?php

                                                                                        $studentHasAssignment = Database::search("SELECT `students_has_assignments_id`,`assignment_marks_status`,`assignment_marks_status_id`,`submission_id`,`submission` FROM `students_has_assignments`
                                                                                        INNER JOIN `assignment_marks_status` ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id`=`assignment_marks_status`.`assignment_marks_status_id`
                                                                                        INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                                                                        WHERE `assignments_assignment_id`='" . $data['assignment_id'] . "' AND `students_student_id`='" . $studentHasIntakeData["admissionId"] . "'");

                                                                                        if ($studentHasAssignment->num_rows > 0) {
                                                                                            $studentHasAssignmentData = $studentHasAssignment->fetch_assoc();
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn table-btn-primary">Submitted</button>
                                                                                                <button class="table-btn table-btn-purple active mx-3" onclick=" window.location.href = 'submit-assignment.php?assignment-code=<?= $data['assignment_code'] ?>'">Re Upload</button>
                                                                                            </td>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn table-btn-pink active" onclick=" window.location.href = 'submit-assignment.php?assignment-code=<?= $data['assignment_code'] ?>'">Upload</button>
                                                                                            </td>
                                                                                        <?php
                                                                                        }

                                                                                        ?>
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
                                                <?php
                                                }

                                                $subjectHasCompleteAssignments = Database::search("SELECT 
                                `assignment_id`,
                                `assignment_code`,
                                `assignment`,
                                `attachment`,
                                `start_date`,
                                `end_date`,
                                `assignment_status`,
                                `assignment_status_assignment_status_id`,
                                `recorrection_apply_end_date`,
                                `late_submission_end_date`
                            FROM 
                                `assignments`
                            INNER JOIN 
                                `assignment_status` 
                                ON `assignments`.`assignment_status_assignment_status_id` = `assignment_status`.`assignment_status_id`
                            INNER JOIN 
                                `subjects` 
                                ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                            INNER JOIN 
                                `levels` 
                                ON `subjects`.`levels_level_id` = `levels`.`level_id`
                            WHERE 
                                `intakes_intake_id` = '" . $studentHasIntakeData['intake_id'] . "'
                                AND `assignment_status_assignment_status_id` != '1'
                                AND (`end_date` < '" . $formatedDateTime . "'
                                OR `assignment_id` IN (SELECT `assignments_assignment_id` FROM `students_has_assignments` WHERE `students_student_id` = '" . $studentHasIntakeData["admissionId"] . "'))
                            ORDER BY 
                                `assignment_id` DESC;
                            ");

                                                if ($subjectHasCompleteAssignments->num_rows > 0) {
                                                    $assignmentsExists = true;
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <label class="text-16 main-text fw-bold mb-2">Complete Assignments</label>
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="students-assignments-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-assignments-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium mx-2">Assignment</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Submit Status</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Mark</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php

                                                                            if ($subjectHasCompleteAssignments->num_rows > 0) {
                                                                                while ($data = $subjectHasCompleteAssignments->fetch_assoc()) {
                                                                            ?>
                                                                                    <tr>
                                                                                        <td colspan="2" class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                    <iconify-icon icon="material-symbols-light:assignment-rounded" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                </div>
                                                                                                <span class="text-14 main-text mx-2"><?= $data["assignment_code"] . "-" . $data["assignment"] ?></span>
                                                                                            </div>
                                                                                        </td>

                                                                                        <?php

                                                                                        $studentHasAssignment = Database::search("SELECT `students_has_assignments_id`,`assignment_marks_status`,`assignment_marks_status_id`,`submission_id`,`submission`,`upload` FROM `students_has_assignments`
                                        INNER JOIN `assignment_marks_status` ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id`=`assignment_marks_status`.`assignment_marks_status_id`
                                        INNER JOIN `assignment_submission` ON `students_has_assignments`.`submission_submission_id`=`assignment_submission`.`submission_id`
                                        WHERE `assignments_assignment_id`='" . $data['assignment_id'] . "' AND `students_student_id`='" . $studentHasIntakeData["admissionId"] . "'");
                                                                                        if ($studentHasAssignment->num_rows > 0) {
                                                                                            $studentHasAssignmentData = $studentHasAssignment->fetch_assoc();
                                                                                        ?>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                <?php
                                                                                                $status_classes = [
                                                                                                    "1"  => "table-btn-success",
                                                                                                    "2"  => "table-btn-yellow",
                                                                                                    "3"  => "table-btn-danger",
                                                                                                ];
                                                                                                $sbutton_class = $status_classes[$studentHasAssignmentData["submission_id"]];
                                                                                                ?>
                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $studentHasAssignmentData["submission"]; ?> Submission</button>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                <button class="table-btn table-btn-dark-theme w-100" onclick="viewStudentSubmitAssignment('<?= $studentHasAssignmentData['upload'] ?>')">View Assignment</button>
                                                                                            </td>

                                                                                            <?php
                                                                                            if ($studentHasAssignmentData["assignment_marks_status_id"] > 3) {
                                                                                                $assignmentMark = Database::search("SELECT `marks` FROM `lecturers_has_students_has_assignments` WHERE `students_has_assignments_students_has_assignments_id`='" . $studentHasAssignmentData["students_has_assignments_id"] . "'");
                                                                                                if ($assignmentMark->num_rows > 0) {
                                                                                                    $assignmentMarkData = $assignmentMark->fetch_assoc();
                                                                                            ?>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <span><?= $assignmentMarkData["marks"] ?></span>
                                                                                                    </td>

                                                                                                    <?php
                                                                                                    $recorrection = Database::search("SELECT * FROM `assignment_recorrection` WHERE `students_has_assignments_students_has_assignments_id`='" . $studentHasAssignmentData['students_has_assignments_id'] . "'");
                                                                                                    if ($recorrection->num_rows > 0) {
                                                                                                    ?>
                                                                                                        <td class="px-4 py-2 align-middle">
                                                                                                            <button class="table-btn table-btn-success w-100">Re-Correction Applied</button>
                                                                                                        </td>
                                                                                                        <?php
                                                                                                    } else {
                                                                                                        if ($studentHasAssignmentData['assignment_marks_status_id'] != 5) {
                                                                                                            if ($data['recorrection_apply_end_date'] > date("Y-m-d H:i:s")) {
                                                                                                        ?>
                                                                                                                <td class="px-4 pe-0 py-2 align-middle">
                                                                                                                    <button
                                                                                                                        class="d-flex align-items-center table-btn bg-dark-theme text-white"
                                                                                                                        onclick="location.href='apply-assignment-recorrection?assignment-code=<?= $data['assignment_code'] ?>'">
                                                                                                                        Apply Recorrection
                                                                                                                        <span
                                                                                                                            class="ms-2"
                                                                                                                            data-bs-toggle="tooltip"
                                                                                                                            data-bs-placement="top"
                                                                                                                            title="(Recorrection Apply End Date: <?= $data['recorrection_apply_end_date'] ?>)">

                                                                                                                            <iconify-icon icon="akar-icons:info" width="18" class="text-white"></iconify-icon>
                                                                                                                        </span>
                                                                                                                    </button>

                                                                                                                </td>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <td></td>
                                                                                                            <?php
                                                                                                            }
                                                                                                        } else {
                                                                                                            ?>
                                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                                <button class="table-btn table-btn-success w-100">Re-Correction Applied</button>
                                                                                                            </td>
                                                                                                    <?php
                                                                                                        }
                                                                                                    }
                                                                                                } else {
                                                                                                    ?>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                        <button class="table-btn table-btn-yellow w-100">Pending</button>
                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle">
                                                                                                    </td>
                                                                                                <?php
                                                                                                }
                                                                                            } else {
                                                                                                ?>
                                                                                                <td class="px-4 py-2 align-middle">
                                                                                                    <?php
                                                                                                    $status_classes = [
                                                                                                        "1"  => "table-btn-yellow",
                                                                                                        "2"  => "table-btn-pink",
                                                                                                        "3"  => "table-btn-orange",
                                                                                                    ];
                                                                                                    $sbutton_class = $status_classes[$studentHasAssignmentData["assignment_marks_status_id"]];
                                                                                                    ?>
                                                                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $studentHasAssignmentData["assignment_marks_status"]; ?></button>
                                                                                                </td>
                                                                                                <td class="px-4 py-2 align-middle"></td>
                                                                                            <?php
                                                                                            }
                                                                                            ?>

                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                                <button class="table-btn table-btn-danger w-100">Not Submitted</button>
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                            </td>
                                                                                            <td class="px-4 py-2 align-middle">
                                                                                            </td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
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
                                                <?php
                                                }

                                                $recorrectionApplied = Database::search("
    SELECT 
        DISTINCT(`students_has_assignments_id`),
        `students_has_assignments_id`,
        `uploaded_at`,
        `upload`,
        `submission_id`,
        `submission`,
        `assignment_id`,
        `assignment_code`,
        `assignment`,
        `assignment_marks_status_id`,
        `assignment_marks_status`
    FROM 
        `students_has_assignments`
                                                   
    INNER JOIN 
        `assignment_submission` 
        ON `students_has_assignments`.`submission_submission_id` = `assignment_submission`.`submission_id`
    INNER JOIN 
        `assignments`
        ON `students_has_assignments`.`assignments_assignment_id`=`assignments`.`assignment_id`
    INNER JOIN 
        `assignment_marks_status`
        ON `students_has_assignments`.`assignment_marks_status_assignment_marks_status_id`=`assignment_marks_status`.`assignment_marks_status_id`        
    INNER JOIN 
                                `subjects` 
                                ON `assignments`.`subjects_subject_id` = `subjects`.`subject_id`
                            INNER JOIN 
                                `levels` 
                                ON `subjects`.`levels_level_id` = `levels`.`level_id`
                            WHERE 
                                `intakes_intake_id` = '" . $studentHasIntakeData['intake_id'] . "'
        AND `students_has_assignments`.`students_student_id`='" . $studentHasIntakeData['admissionId'] . "'
        AND (
            `assignment_marks_status_assignment_marks_status_id` = '5'
            OR `students_has_assignments_id` IN (
                SELECT `students_has_assignments_students_has_assignments_id` 
                FROM `assignment_recorrection`
            )
        )
    ORDER BY 
        `students_has_assignments`.`uploaded_at` DESC
");

                                                if ($recorrectionApplied->num_rows > 0) {
                                                    $assignmentsExists = true;
                                                ?>
                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <label class="text-16 main-text fw-bold mb-2">Re-Correction Applied Assignments</label>
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="students-recorrection-assignments-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="students-recorrection-assignments-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                        <span class="text-14 main-text fw-medium">Assignment</span>
                                                                                    </div>
                                                                                </th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Submit Status</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Assignment</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Mark</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Payment Status</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            <?php

                                                                            if ($recorrectionApplied->num_rows > 0) {
                                                                                while ($data = $recorrectionApplied->fetch_assoc()) {
                                                                                    $hasRecorrectionFee = Database::search("SELECT * FROM `assignment_recorrection_fee` WHERE `assignments_assignment_id` = '" . $data['assignment_id'] . "'");
                                                                            ?>
                                                                                    <tr class="br-t-5">
                                                                                        <td colspan="2" class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                    <iconify-icon icon="material-symbols-light:assignment-rounded" width="20" height="20" class="dark-theme"></iconify-icon>
                                                                                                </div>
                                                                                                <span class="text-14 main-text mx-2"><?= $data["assignment_code"] . "-" . $data["assignment"] ?></span>
                                                                                            </div>
                                                                                        </td>

                                                                                        <td class="py-2 align-middle">
                                                                                            <?php
                                                                                            $status_classes = [
                                                                                                "1"  => "table-btn-success",
                                                                                                "2"  => "table-btn-yellow",
                                                                                                "3"  => "table-btn-danger",
                                                                                            ];
                                                                                            $sbutton_class = $status_classes[$data["submission_id"]];
                                                                                            ?>
                                                                                            <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["submission"]; ?> Submission</button>
                                                                                        </td>

                                                                                        <td class="py-2 align-middle">
                                                                                            <button class="table-btn table-btn-purple w-100" onclick="viewStudentSubmitAssignment('<?= $data['upload'] ?>')">View Assignment</button>
                                                                                        </td>
                                                                                        <?php

                                                                                        $assignmentAssignLecturer = Database::search("SELECT `title`,`user_fname`,`user_lname`,`profile_img`,`marks` FROM `assignment_recorrection`
                                    INNER JOIN `lecturers` ON `assignment_recorrection`.`lecturers_lecturers_id`=`lecturers`.`lecturers_id`
                                    INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id` 
                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                    WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");

                                                                                        if ($assignmentAssignLecturer->num_rows > 0) {
                                                                                            $assignmentAssignLecturer = $assignmentAssignLecturer->fetch_assoc();
                                                                                        ?>
                                                                                            <td class="pt-3 pb-3 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                                                                    <?php
                                                                                                    if ($assignmentAssignLecturer['marks'] == null) {

                                                                                                        $status_classes = [
                                                                                                            "1"  => "table-btn-yellow",
                                                                                                            "2"  => "table-btn-pink",
                                                                                                            "3"  => "table-btn-orange",
                                                                                                        ];
                                                                                                        $sbutton_class = $status_classes[$studentHasAssignmentData["assignment_marks_status_id"]];
                                                                                                    ?>
                                                                                                        <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $studentHasAssignmentData["assignment_marks_status"]; ?></button>

                                                                                                    <?php
                                                                                                    } else {
                                                                                                    ?>
                                                                                                        <span class="text-14 main-text"><?= $assignmentAssignLecturer['marks']; ?></span>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </div>
                                                                                            </td>
                                                                                        <?php
                                                                                        } else {
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn table-btn-yellow w-100">Pending</button>
                                                                                            </td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>

                                                                                        <?php
                                                                                        $paymnet_status = 'Awaiting Payment';
                                                                                        $payment_status_id = '1';
                                                                                        $payment_id;
                                                                                        if ($hasRecorrectionFee->num_rows > 0) {
                                                                                            $studentRecorrectionPayment = Database::search("SELECT * FROM `assignment_recorrection_payment`
                                        INNER JOIN `payment_status` ON `assignment_recorrection_payment`.`payment_status_payment_status_id` = `payment_status`.`payment_status_id` 
                                        WHERE `students_has_assignments_students_has_assignments_id`='" . $data['students_has_assignments_id'] . "'");
                                                                                            if ($studentRecorrectionPayment->num_rows > 0) {
                                                                                                $psdata =  $studentRecorrectionPayment->fetch_assoc();
                                                                                                $payment_status_id = $psdata['payment_status_payment_status_id'];
                                                                                                $paymnet_status = $psdata['payment_status'];
                                                                                                $payment_id = $psdata['assignment_recorrection_payment_id'];
                                                                                            }
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <?php
                                                                                                $status_classes = [
                                                                                                    "0"     => "table-btn-purple",
                                                                                                    "1"  => "table-btn-yellow",
                                                                                                    "2"  => "table-btn-orange",
                                                                                                    "3"  => "table-btn-success",
                                                                                                    "4"  => "table-btn-danger",
                                                                                                    "5"  => "table-btn-orange",
                                                                                                    "6"  => "table-btn-success",
                                                                                                    "7"  => "table-btn-danger",
                                                                                                    "8"  => "table-btn-success",
                                                                                                ];
                                                                                                $sbutton_class = $status_classes[$payment_status_id];
                                                                                                ?>
                                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $paymnet_status; ?></button>
                                                                                            </td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>

                                                                                        <?php
                                                                                        if ($payment_status_id > 1) {
                                                                                        ?>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn table-btn-primary" onclick="window.location.href='assignment-recorrection-payment.php?payment-id=<?= $payment_id; ?>'">View Payment</button>
                                                                                            </td>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
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
                                                <?php
                                                }

                                                if (!$assignmentsExists) {
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
                                                                                    </div>                                                        </div>
                                                    </div>
                                                <?php
                                                }
                                                ?>
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