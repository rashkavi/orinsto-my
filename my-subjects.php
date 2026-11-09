<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null && !SessionUser::ifUserIsLecturer($sessionUserRole)) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>My Subjects - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                include './includes/components/skeleton-header.php';
                                include './includes/components/skeleton-courses.php'; ?>
                            </div>
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <div class="col-12">
                                                <div class="row">
                                                    <span class="main-topic lh-lg">My Subjects</span>
                                                    <span class="text-14 sub-text lh-base mt-1">View and manage your enrolled subjects, access learning materials, and stay up to date with your academic progress.</span>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <?php

                                    $subject_query = "SELECT * FROM `subjects` 
                                    INNER JOIN `levels` ON `subjects`.`levels_level_id`=`levels`.`level_id`
                                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id`=`intakes`.`intake_id`
                                    INNER JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
                                    INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
                                            LEFT JOIN `subject_group` ON `subjects`.`subject_group_subject_group_id`=`subject_group`.`subject_group_id`
                                            WHERE `subjects`.`subject_id` IN (SELECT `subjects_subject_id` FROM lecturers 
                                        WHERE lecturers.users_user_id = '" . $sessionUserObject['user_id'] . "'
                                        AND lecturers.status_status_id = 1) 
                                           
                                            ";

                                    $subject_query .= " ORDER BY `subjects`.`subject_type_subject_type_id` ASC, `subjects`.`subject_group_subject_group_id` ASC";

                                    $subjects = Database::search($subject_query);

                                    if ($subjects->num_rows > 0) {
                                        while ($subject = $subjects->fetch_assoc()) {
                                            $labelClass = "";
                                            $groupName = "";
                                            if ($subject['subject_type_subject_type_id'] == 1) {
                                                $labelClass = "label-role-9";
                                                $groupName = "Core Subject";
                                            } else {
                                                $groupIndex = ((int)$subject['subject_group_subject_group_id'] % 9) + 2;
                                                $labelClass = "label-role-" . $groupIndex;
                                                $groupName = $subject['subject_group'];
                                            }
                                    ?>
                                            <div class="col-12 px-4 py-3 component-theme mb-10">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex align-items-center p-0 m-0 g-0">
                                                        <?php
                                                        if (!empty($subject['subject_cover']) && file_exists("./assets/imgs/subjects-covers/" . $subject['subject_cover'])) {
                                                        ?>
                                                            <img src="./assets/imgs/subjects-covers/<?= $subject['subject_cover'] ?>" class="w-100 h-auto" alt="subject img">
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <img src="./assets/imgs/common/empty-thumbnail.webp" class="w-100 h-auto" alt="subject img">
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>

                                                    <div class="col-12 col-md-7 col-lg-8 px-0 px-md-3 mt-3 mt-md-0 g-0 d-flex flex-column justify-content-center">
                                                        <h6 class="text-15 text-dark-color fw-semibold lh-lg mb-0">
                                                            <?= htmlspecialchars($subject["subject"]) ?>
                                                        </h6>
                                                        <span class="text-14 sub-text lh-lg">
                                                            <?= $subject['faculty'] ?> / <?= $subject['programme'] ?> / <?= $subject['intake'] ?> / <?= $subject['level'] ?>
                                                        </span>

                                                        <p class="text-14 main-text lh-lg mt-1 mb-0">
                                                            <?php
                                                            if ($subject['subject_type_subject_type_id'] == 2) {
                                                            ?>
                                                                <span class="subject-label label-role-8">
                                                                    <iconify-icon icon="mdi:label-outline" width="15" height="15"></iconify-icon>
                                                                    Elective Subject
                                                                </span>
                                                            <?php
                                                            }
                                                            ?>
                                                            <span class="subject-label <?= $labelClass ?>">
                                                                <iconify-icon icon="<?= $subject['subject_type_subject_type_id'] == 1 ? 'mdi:book-open-page-variant' : 'pixelarticons:group' ?>" width="15" height="15"></iconify-icon>
                                                                <?= $groupName ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="col-12 col-md-3 p-0 m-0 position-relative">
                                                        <div class="row h-100 p-0 align-items-center justify-content-end">


                                                            <div class="col-6 d-flex justify-content-end align-items-center m-0">
                                                                <button class="custom-btn w-100" type="button" data-i18n="view" onclick="location.href='subject-details?subject-id=<?= $subject['subject_id'] ?>'">
                                                                    View
                                                                </button>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        }
                                    } else {
                                        ?>
                                        <div class="col-12 px-4 py-3 component-theme">
                                            <div class="row align-items-center justify-content-center">
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
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
    }
}

?>