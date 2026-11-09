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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lecturer-id']) && !empty($_GET['lecturer-id']))) {
            header("Location: ./users");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('users', 1) || UserPermissions::hasPermission('users', 10))) {
                header("Location: ./users");
            } else {
                require_once "./includes/connection.php";
                $id = $_GET['lecturer-id'];
                $user = Database::search("SELECT * FROM `users` 
                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                        INNER JOIN `status` ON `users`.`status_status_id`=`status`.`status_id` 
                                                        WHERE `users`.`user_id`='" . $id . "' AND `user_role_id` = '20'");
                if ($user->num_rows == 0) {
                    header("Location: ./users");
                } else {
                    $udata = $user->fetch_assoc();

                    $query = "SELECT 
                    *, COUNT(`lecturers`.`lecturer_contracts_lecturer_contract_id`) AS contract_count FROM `lecturer_contracts` 
                    INNER JOIN `payment_cycle` ON `lecturer_contracts`.`payment_cycle_payment_cycle_id`=`payment_cycle`.`payment_cycle_id`
                    INNER JOIN `lecturers` ON `lecturer_contracts`.`lecturer_contract_id`=`lecturers`.`lecturer_contracts_lecturer_contract_id`
                    INNER JOIN `users` ON `lecturer_contracts`.`users_user_id`=`users`.`user_id`
                    INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                    WHERE `lecturer_contracts`.`lecturers_user_id`='" . $id . "'
                    GROUP BY `lecturer_contracts`.`lecturer_contract_id` ORDER BY `lecturer_contracts`.`lecturer_contract_id` DESC";

                    $results = Database::search($query);
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Lecturer Contarcts - Admin Dashboard | <?= Project::getBaseName() ?></title>

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

                    <body class="overflow-hidden" onload="initializeViewUser();">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("lecturers");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("lecturers");
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
                                                                <span class="main-topic lh-lg"><?= $udata['title'] . "." . $udata['user_fname'] . " " . $udata['user_lname']; ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./lecturers">Lecturers</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Contarcts</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" onclick="window.location.href = './create-contract?lecturer-id=<?= $id ?>'">
                                                                Create Contarct
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 mb-4">
                                                            <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <div class="d-flex custom-div align-items-center justify-content-center">

                                                                        <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $results->num_rows; ?> <?= $results->num_rows == 1 ? "Contract" : "Contracts"; ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                                    <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                        <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                                        <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleUsersPagination('filter')">
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

                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                                    <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                                    <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleUsersPagination('filter')">
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
                                                                    <div class="table-responsive" id="users-table-container">
                                                                        <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                            <thead>
                                                                                <tr class="br-t-5 align-content-center">
                                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all">
                                                                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                                                                <span class="text-14 main-text fw-medium mx-2">Contarct Title</span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Subjects</th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Start Date</th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">End Date</th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Payment Cycle</th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Date Added</th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Added By</th>
                                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                                                                    <?php

                                                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4) || UserPermissions::hasPermission('users', 10)) {
                                                                                    ?>
                                                                                        <!-- <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th> -->
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody id="user-table-data">
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
                                                                                $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                                if ($users->num_rows > 0) {
                                                                                    while ($data = $users->fetch_assoc()) {
                                                                                ?>
                                                                                        <tr>
                                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer">
                                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["contract_title"]; ?></span>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["contract_count"]; ?> <?= $data["contract_count"] == 1 ? "subject" : "subjects";  ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["contract_start_date"]; ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["contract_end_date"]; ?></span></td>
                                                                                            <td class="py-2 align-middle">
                                                                                                <button class="table-btn w-100" style="
                                                                                                    background: var(--role-<?= $data["payment_cycle_id"] ?>-bg);
                                                                                                    color: var(--role-<?= $data["payment_cycle_id"] ?>-text);
                                                                                                "> <?php echo $data["payment_cycle"]; ?></button>
                                                                                            </td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= date("Y-m-d H:iA", strtotime($data['date_added'])); ?></span></td>
                                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['title'] . "." . $data['user_fname'] . " " . $data['user_lname']; ?></span></td>

                                                                                            <td class="py-3 align-middle">
                                                                                                <div class="dropdown">
                                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="view-lecturer-contract?contract-id=<?php echo $data['lecturer_contract_id']; ?>">
                                                                                                                <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                View</a></li>
                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-lecturer-contract?contract-id=<?php echo $data['lecturer_contract_id']; ?>">
                                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                Edit</a></li>
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersPagination('previousPage')">
                                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
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
                                                                                                $action = $isActive ? '' : "onclick=\"handleUsersPagination('goToPage', '{$i}')\"";
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
                                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                                        <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
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
}
?>