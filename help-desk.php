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
   

?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Help Desk - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden">
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("help-desk");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("help-desk");
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
                                            <span class="main-topic lh-lg" data-i18n="student-inquiries">Student Inquiries</span>
                                            <span class="text-14 sub-text lh-base mt-1" data-i18n="student-inquiries-desc">Manage and respond to questions submitted by students.</span>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <?php
                                                            $countQuery = "SELECT COUNT(*) as 'count' FROM `students_has_help_desk` WHERE `help_desk_status_help_desk_status_id` !='5'";
                                                            if ($sessionUserRole != "Developer") {
                                                                $countQuery .= " AND `help_desk_category_help_desk_category_id` != '2'";
                                                            }

                                                            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 30))) {
                                                                $countQuery .= " AND `users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                                                            }
                                                            $inquiry_count = Database::search($countQuery);
                                                            $data = $inquiry_count->fetch_assoc();
                                                            ?>
                                                            <span class="text-14 dark-theme fw-medium"><?= $data['count']; ?> <span data-i18n="in-progress"></span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleInquiriesPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterAccordion" aria-expanded="false" aria-controls="filterAccordion">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="collapse" id="filterAccordion">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end <?= SessionUser::ifUserIsLecturer($sessionUserRole) ? 'd-none' : '' ?>">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="category-selector" onchange="handleInquiriesPagination('filter')">
                                                                            <option selected value="0">- Filter Category -</option>
                                                                            <?php
                                                                            $query = "SELECT * FROM `help_desk_category`";
                                                                            if ($sessionUserRole != "Developer") {
                                                                                $query .= " WHERE `help_desk_category_id` != 2";
                                                                            }
                                                                            $help_desk_categories = Database::search($query);
                                                                            if ($help_desk_categories->num_rows > 0) {
                                                                                while ($help_desk_category = $help_desk_categories->fetch_assoc()) {
                                                                            ?>
                                                                                    <option value="<?= $help_desk_category["help_desk_category_id"] ?>"><?= $help_desk_category["help_desk_category"] ?></option>
                                                                            <?php
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>

                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="status-selector" onchange="handleInquiriesPagination('filter')">
                                                                            <option selected value="0">- Filter Status -</option>
                                                                            <?php
                                                                            $help_desk_statuses = Database::search("SELECT * FROM `help_desk_status`");
                                                                            if ($help_desk_statuses->num_rows > 0) {
                                                                                while ($help_desk_status = $help_desk_statuses->fetch_assoc()) {
                                                                            ?>
                                                                                    <option value="<?= $help_desk_status["help_desk_status_id"] ?>"><?= $help_desk_status["help_desk_status"] ?></option>
                                                                            <?php
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleFAQPagination('filter')">
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
                                                        <div class="table-responsive" id="inquiries-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="inquiry-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium w-25">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                <span class="text-14 main-text fw-medium mx-2">Student</span>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Category</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assigned User</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Added At</th>
                                                                        <th scope="col" class="py-3 bg-table-header">
                                                                            <div class="d-flex flex-row align-items-center">
                                                                                <span class="text-14 main-text fw-medium">Status</span>
                                                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody id="user-table-data">
                                                                    <?php

                                                                    $query = "SELECT
                                                                
                                                                    `students`.`profile_img`,
                                                                    `login_details`.`first_name`,
                                                                    `login_details`.`last_name`,
                                                                    `help_desk_category`.`help_desk_category`,
                                                                    `help_desk`.`title` AS `help_desk_title`,
                                                                    `help_desk_status`.`help_desk_status_id`,
                                                                    `help_desk_status`.`help_desk_status`,
                                                                    `title`.`title`,
                                                                    `students_has_help_desk`.`date_added`,
                                                                    `students_has_help_desk`.`students_has_help_desk_id`,
                                                                    `users`.`user_fname`,
                                                                    `users`.`user_lname`,
                                                                    `users`.`profile_img` AS `user_profile_img`,
                                                                    `title2`.`title` AS `user_title`,
                                                                    `users`.`user_id`
                                                                FROM `students_has_help_desk`
                                                                LEFT JOIN `help_desk` 
                                                                    ON `help_desk`.`help_desk` = `students_has_help_desk`.`help_desk_help_desk`
                                                                INNER JOIN `help_desk_category` 
                                                                    ON `help_desk_category`.`help_desk_category_id` = `students_has_help_desk`.`help_desk_category_help_desk_category_id`
                                                                INNER JOIN `help_desk_status` 
                                                                    ON `help_desk_status`.`help_desk_status_id` = `students_has_help_desk`.`help_desk_status_help_desk_status_id`
                                                                INNER JOIN `students` 
                                                                    ON `students`.`student_id` = `students_has_help_desk`.`students_student_id`
                                                                INNER JOIN `login_details`
                                                                    ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
                                                                INNER JOIN `title` 
                                                                    ON `title`.`title_id` = `students`.`title_title_id`
                                                                LEFT JOIN `users` 
                                                                    ON `users`.`user_id` = `students_has_help_desk`.`users_user_id`
                                                                LEFT JOIN `title` AS `title2` 
                                                                    ON `title2`.`title_id` = `users`.`title_title_id`
                                                                ";

                                                                    if ($sessionUserRole != "Developer") {
                                                                        $query .= " WHERE `students_has_help_desk`.`help_desk_category_help_desk_category_id` != '2'";
                                                                    }
                                                                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 30))) {
                                                                        if (strpos($query, 'WHERE') == false) {
                                                                            $query .= " WHERE `students_has_help_desk`.`users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                                                                        } else {
                                                                            $query .= " AND `students_has_help_desk`.`users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                                                                        }
                                                                    }

                                                                    $query .= " ORDER BY `students_has_help_desk_id` DESC";

                                                                    $results = Database::search($query);
                                                                    $pageno = 1;
                                                                    $results_count = $results->num_rows;
                                                                    $result_per_page = 10;  // Consistent variable name

                                                                    if ($results_count > $result_per_page) {
                                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                    } else {
                                                                        $number_of_pages = 1;
                                                                    }

                                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                                    $inquiries = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                    if ($inquiries->num_rows > 0) {
                                                                        $status_classes = [
                                                                            "1"  => "table-btn-yellow",
                                                                            "2"  => "table-btn-primary",
                                                                            "3"  => "table-btn-danger",
                                                                            "4"  => "table-btn-orange",
                                                                            "5"  => "table-btn-success",
                                                                        ];
                                                                        while ($data = $inquiries->fetch_assoc()) {
                                                                    ?>
                                                                            <tr>
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                        <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                            <?php
                                                                                            if (!empty($data["profile_img"]) && file_exists(Project::getBasePortalUrl() . '/assets/imgs/students/' . $data["profile_img"])) {
                                                                                            ?>
                                                                                                <img src="<?= Project::getBasePortalUrl() ?>/assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                        </div>
                                                                                    </div>
                                                                                </td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["help_desk_category"]; ?></span></td>
                                                                                <td class="py-2 align-middle">
                                                                                    <?php
                                                                                    if (!empty($data['user_id'])):
                                                                                    ?>
                                                                                        <div class="d-flex align-items-center">
                                                                                            <?php
                                                                                            if (!empty($data["user_profile_img"]) && file_exists('./assets/imgs/users/' . $data["user_profile_img"])) {
                                                                                            ?>
                                                                                                <img src="./assets/imgs/users/<?php echo $data["user_profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                            <?php
                                                                                            } else {
                                                                                                $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                            ?>
                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                            <span class="text-14 main-text mx-2"><?php echo $data["user_title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                        </div>
                                                                                    <?php else: ?>
                                                                                        <span class="text-14 main-text">N/A</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?php echo $data["date_added"]; ?>
                                                                                </td>
                                                                                <td class="py-2 align-middle">
                                                                                    <?php
                                                                                    $sbutton_class = $status_classes[$data["help_desk_status_id"]];
                                                                                    ?>
                                                                                    <button class="table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["help_desk_status"]; ?></button>
                                                                                </td>

                                                                                <td class="py-3 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="student-inquiry?inquiry-id=<?php echo $data['students_has_help_desk_id']; ?>">
                                                                                                    <iconify-icon icon="akar-icons:eye-open" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    View</a></li>
                                                                                        </ul>
                                                                                    </div>
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
                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                    <div class="row p-4 align-items-center">
                                                                        <!-- Previous Button (col-1) -->
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleInquiriesPagination('previousPage')">
                                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                            <span class="text-14 main-text fw-semibold">Previous</span>
                                                                        </button>
                                                                        <!-- Page Numbers (col-10, centered) -->
                                                                        <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                            <ul class="pagination-list d-flex mb-0">
                                                                                <?php
                                                                                $shudderPages1 = false;
                                                                                $shudderPages2 = false;

                                                                                function renderPageButton($i, $isActive = false)
                                                                                {
                                                                                    $activeClass = $isActive ? 'active' : '';
                                                                                    $action = $isActive ? '' : "onclick=\"handleInquiriesPagination('goToPage', '{$i}')\"";
                                                                                    echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                                }

                                                                                for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                    if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                        renderPageButton($i, $pageno == $i);
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
                                                                        <!-- Next Button (col-1) -->
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleInquiriesPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php
}
?>