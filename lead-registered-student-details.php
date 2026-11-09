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
            SELECT *,`students_has_intakes`.`student_id` AS `intakeStudentId`,`intakes`.`intake` FROM `students_has_intakes` 
            INNER JOIN `students` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
            INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
            INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
            INNER JOIN `leads` ON `students_has_intakes`.`leads_lead_id` = `leads`.`lead_id`
            INNER JOIN `programmes` ON `leads`.`programmes_programme_id` = `programmes`.`programme_id`
            LEFT JOIN `users` ON `students_has_intakes`.`assigned_user_id` = `users`.`user_id`
            LEFT JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
            WHERE `leads_lead_id` = '" . $id . "'
            ";

            if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                $query .= " AND `leads`.`users_user_id` = '" . $sessionUserObject['user_id'] . "'";
            }

            $lead = Database::search($query);
            if ($lead->num_rows == 0) {
                header("Location: ./inquiries");
            } else {
                $sdata = $lead->fetch_assoc();

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Lead Registered Student Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                load_side_menu::load("inquiries");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("inquiries");
                                        ?>
                                    </div>
                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-courses.php"; ?>
                                    </div>
                                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"><?= $sdata['title'] . "." . $sdata['first_name'] . " " . $sdata['last_name']; ?> - Admission Number : <?= $sdata['student_id'] ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./inquiries.php">All Leads</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./lead-view.php?lead-id=<?= $id ?>">Lead View</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Lead Registered Student</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-12 col-xxl-2 mt-3 mt-xxl-0">
                                                        <div class="row gx-3 justify-content-end">
                                                            <?php
                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                                                            ?>
                                                                <div class="col-12 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <div class="dropdown w-100">
                                                                        <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                            Action
                                                                        </button>
                                                                        <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                            <li><a class="dropdown-item" href="./student-details?student-id=<?= $sdata['student_id'] ?>">View Profile Details</a></li>
                                                                            <li><a class="dropdown-item" href="./edit-lead-student-details?student-id=<?= $sdata['students_has_intakes_id'] ?>">Edit Profile Details</a></li>
                                                                            <li><a class="dropdown-item" href="./lead-registered-student-payments?student-id=<?= $sdata['students_has_intakes_id'] ?>">Student Payments</a></li>
                                                                            <?php
                                                                            if ($sdata['intakes_intake_id'] == null) {
                                                                            ?>
                                                                                <li><a class="dropdown-item" data-bs-toggle="modal" data-bs-target="#assignIntake">Assign Student to Intake</a></li>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </ul>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="container p-0 m-0 g-0 mb-4">
                                                <div class="row g-4 d-flex">
                                                    <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 d-flex align-items-center justify-content-center table-btn-danger"> <iconify-icon icon="hugeicons:date-time" width="32" height="32" class="text-dark-red"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                Inatke</div>
                                                                            <div class="stat-value mb-0 text-gray-800">
                                                                                <?php
                                                                                if (!empty($sdata['intakes_intake_id'])) {
                                                                                    echo $sdata['intake'];
                                                                                } else {
                                                                                    echo "N/A";
                                                                                }
                                                                                ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="hugeicons:identification" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                Intake Reg. Number</div>
                                                                            <div class="stat-value mb-0 text-gray-800"><?= $sdata['intakeStudentId'] == null ? "N/A" : $sdata['intakeStudentId'] ?></div>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="hugeicons:user-ai" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                Lead Assigned User</div>
                                                                            <div class="stat-value mb-0 text-gray-800">
                                                                                <?php

                                                                                echo $sdata['user_fname'] . " " . $sdata['user_lname'];

                                                                                ?></div>
                                                                        </div>

                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-lg-6 col-xxl-3 d-flex align-items-stretch">
                                                        <div class="component-theme p-3 w-100">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <div class="hwr-70 d-flex align-items-center justify-content-center table-btn-success"> <iconify-icon icon="hugeicons:date-time" width="32" height="32" class="text-dark-green"></iconify-icon></div>
                                                                <div class="d-flex flex-column flex-grow-1 px-4">
                                                                    <div class="row no-gutters align-items-center">
                                                                        <div class="col mr-2">
                                                                            <div class="text-xs font-weight-bold sub-text text-uppercase mb-1">
                                                                                Registered Date</div>
                                                                            <div class="stat-value mb-0 text-gray-800"><?= date('Y-m-d H:i A', strtotime($sdata['addmission_date'])) ?></div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>

                                            <div class="col-12 component-theme">
                                                <div class="row p-4 m-0 g-0">
                                                    <span class="sub-topic">Updates History</span>

                                                    <div class="col-12 mt-3">
                                                        <div class="row p-0 m-0 g-0 align-content-center">
                                                            <div class="table-responsive-wrapper">
                                                                <div class="table-responsive" id="users-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                        <thead>
                                                                            <tr class="br-t-5">
                                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium w-25">
                                                                                    <div class="d-flex flex-row align-items-center">
                                                                                        <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                    </div>
                                                                                </th>

                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>


                                                                            </tr>
                                                                        </thead>
                                                                        <tbody id="user-table-data">
                                                                            <?php
                                                                            $query = "SELECT * FROM `students_has_intakes_logs`
                                                                    INNER JOIN `users` ON `students_has_intakes_logs`.`users_user_id` = `users`.`user_id`
                                                                    INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                               
                                                                    WHERE `students_has_intakes_students_has_intakes_id` = '" . $id . "' ORDER BY `date_updated` DESC";

                                                                            $users = Database::search($query);
                                                                            if ($users->num_rows > 0) {
                                                                                while ($data = $users->fetch_assoc()) {
                                                                            ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3 pe-0 align-middle">
                                                                                            <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                <?php
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
                                                                                                <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                            </div>
                                                                                        </td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= date('Y-m-d H:i A', strtotime($data["date_updated"])); ?></span></td>
                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"]; ?></span></td>

                                                                                        <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"] != null ? $data["note"] : "N/A"; ?></span></td>
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

                    <div class="modal fade" id="assignIntake" tabindex="-1" aria-labelledby="assignIntakeLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="assignIntakeLabel">Assign Student to Intake</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="col-12 p-0 g-0">
                                        <div class="row gx-4 gy-3">
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text" for="">Intake <?= $sdata["programmes_programme_id"] ?></label>

                                                <select id="intake-selector" class="form-select custom-select mt-2">
                                                    <option value="0">- Select -</option>
                                                    <?php
                                                    $intakesResult = Database::search("SELECT * FROM `intakes` WHERE `programmes_programme_id` = '" . $sdata["programme_id"] . "' ORDER BY `intake_id` ASC");
                                                    while ($data = $intakesResult->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $data["intake_id"]; ?>"><?php echo $data["intake"]; ?></option>
                                                    <?php
                                                    }
                                                    ?>

                                                </select>
                                                <span class="text-dark-red text-12" id="intake-feedback"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="custom-btn px-5" onclick="assignLeadStudentToIntake(this,'<?= $id ?>')">Save changes</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/fileChooser.js"></script>
                </body>

                </html>
<?php
            }
        }
    }
}
