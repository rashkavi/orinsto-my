<?php
require_once './includes/UserClass.php';
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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['complain-id']) && !empty($_GET['complain-id']))) {
            if (SessionUser::ifUserIsPro($sessionUserRole)) {
                header("Location: ./complains");
            } else {
                header("Location: ./complains-stages");
            }
        } else {
            require_once "./includes/connection.php";
            $id = $_GET['complain-id'];

            $query = "
            SELECT * FROM `complains`
            INNER JOIN `users` ON `complains`.`users_user_id` = `users`.`user_id` 
            WHERE `complain_id` = '" . $_GET["complain-id"] . "'
        ";

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole == "Developer")) {
                $query .= " AND `users_user_id` = '" . $sessionUserObject["user_id"] . "'";
            }

            $complain = Database::search($query);
            if ($complain->num_rows == 0) {
                header("Location: ./complains");
            } else {
                $complainData = $complain->fetch_assoc();

                $hasPermissionUpdateLead = false;
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Complain View - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                load_side_menu::load("complains");

                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                                <div class="row p-0 m-0 g-0 h-100">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("complains");
                                        ?>
                                    </div>
                                    <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                                        <?php
                                        include "./includes/components/skeleton-header.php";
                                        include "./includes/components/skeleton-add-edit.php";
                                        ?>
                                    </div>

                                    <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg"> # <?= $id ?> Complain</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text-color text-decoration-none cursor-pointer" href="./complains">Complains</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text-color text-decoration-none">View Complain</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if ($hasPermissionUpdateLead) {
                                                    ?>
                                                        <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" data-bs-target="#updateLead" data-bs-toggle="modal">
                                                                Update Complain
                                                            </button>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="payment-card-container">
                                                <div class="payment-card">
                                                    <!-- Background elements -->
                                                    <div class="card-background"></div>

                                                    <!-- Card content -->
                                                    <div class="card-content">
                                                        <div class="payment-header">
                                                            <!-- Reference on left -->
                                                            <div class="reference-section">
                                                                <span class="reference-label">Name</span>
                                                                <h2 class="reference-number"><?= $complainData['name']; ?></h2>
                                                                <hr>
                                                            </div>
                                                            <!-- Amount on right -->
                                                            <div class="amount-section">
                                                                <div class="amount-display">
                                                                    <span class="amount-label">Registration Number</span>
                                                                    <span class="amount-value"><?= $complainData['registration_no']; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- Details in a row below -->
                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                            <!-- contact -->
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <iconify-icon icon="duo-icons:menu" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Intake</span>
                                                                    <span class="detail-value"><?= $complainData["intake"]; ?></span>
                                                                </div>
                                                            </div>
                                                            <!-- contact -->
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <iconify-icon icon="material-symbols:call-outline" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Contact Number</span>
                                                                    <span class="detail-value"><?= $complainData["contact"]; ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <iconify-icon icon="solar:calendar-date-broken" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Complain Date & Time</span>
                                                                    <span class="detail-value"><?= date("Y-m-d H:i A", strtotime($complainData["datetime"])); ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <iconify-icon icon="duo-icons:user" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Assigned User</span>
                                                                    <span class="detail-value"><?= $complainData["user_fname"]." ".$complainData["user_lname"]; ?></span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                            <!-- contact -->
                                                            <div class="detail-item">
                                                                <div class="detail-icon">
                                                                    <iconify-icon icon="hugeicons:complaint" width="16" height="16"></iconify-icon>
                                                                </div>
                                                                <div class="detail-content">
                                                                    <span class="detail-label">Complain</span>
                                                                    <span class="detail-value"><?= $complainData["complain"]; ?></span>
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
                                                                    <?php
                                                                    $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `complains_logs` WHERE `complains_id`='" . $id . "'");
                                                                    $data = $logs_count->fetch_assoc();
                                                                    ?>
                                                                    <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> Logs</span>
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
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody id="user-table-data">
                                                                        <?php
                                                                        $query = "SELECT * FROM `complains_logs`
                                                                    LEFT JOIN `users` ON `complains_logs`.`users_user_id` = `users`.`user_id`
                                                                                                                                  
                                                                    WHERE `complains_logs`.`complains_id` = '" . $id . "' ORDER BY `complains_logs`.`complains_log_id` DESC";

                                                                        $users = Database::search($query);
                                                                        if ($users->num_rows > 0) {
                                                                            while ($data = $users->fetch_assoc()) {

                                                                        ?>
                                                                                <tr>
                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                            <?php
                                                                                            if ($data["users_id"] != null) {

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
                                                                                    <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo (new DateTime($data["datetime"]))->format('Y-m-d h:i A') ?></span></td>
                                                                                    <td class="py-2 align-middle">
                                                                                        <p class="text-14 main-text"><?php echo $data["note"] ?></p>
                                                                                    </td>
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
?>