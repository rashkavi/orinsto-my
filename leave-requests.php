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

        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 25))) {
            header("Location: ./dashboard");
            exit();
        }


        $query = "
        SELECT 
            lr.leave_request_id,
            lr.from_date,
            lr.to_date,
            lr.total_days,
            lr.reason,
            lr.requested_at,
        
            lrs.leave_request_status,
            lrs.leave_request_status_id,
        
            lt.leave_type,
        
            ldt.leave_day_type,
        
            u.user_id,
            u.user_fname,
            u.user_lname,
            u.profile_img,
        
            t.title
        
        FROM leave_requests AS lr
        
        INNER JOIN leave_types AS lt
            ON lt.leave_type_id = lr.leave_types_leave_type_id
        
        INNER JOIN leave_request_status AS lrs
            ON lrs.leave_request_status_id = lr.leave_request_status_leave_request_status_id
        
        INNER JOIN leave_day_type AS ldt
            ON ldt.leave_day_type_id = lr.leave_day_type_leave_day_type_id
        
        INNER JOIN users AS u
            ON u.user_id = lr.users_user_id
        
        INNER JOIN title AS t
            ON t.title_id = u.title_title_id
        
        ORDER BY lr.leave_request_id DESC
        ";
        $results = Database::search($query);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Leave Requests - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">

            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body>
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("leave-requests");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("leave-requests");
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
                                            <div class="col-12 col-md-10">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="">Leave Requests</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="">Effortlessly manage users, access profiles, and maintain oversight</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-2 mt-3 mt-lg-0">
                                                <div class="row align-items-center justify-content-end gx-3 h-100">
                                                    <div class="col-12 mt-3 mt-lg-0 m-md-0 text-end">
                                                        <button class="custom-btn w-100" type="button"
                                                            onclick="window.location.href='add-leave'">
                                                            Add Leave
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <span class="text-14 dark-theme fw-medium p-0 m-0"><?php echo $results->num_rows ?> Requests</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="user-search-text" onkeyup="handleLeaveRequestPagination('filter')">
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
                                                                <div class="row gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="type-selector" onchange="handleLeaveRequestPagination('filter')">
                                                                            <option selected value="0">- Filter Leave Type -</option>
                                                                            <?php
                                                                            $leave_type = Database::search("SELECT * FROM `leave_types`");
                                                                            while ($data = $leave_type->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["leave_type_id"]; ?>"><?php echo $data["leave_type"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="day-type-selector" onchange="handleLeaveRequestPagination('filter')">
                                                                            <option selected value="0">- Filter Leave Day Type -</option>
                                                                            <?php
                                                                            $leave_type = Database::search("SELECT * FROM `leave_day_type`");
                                                                            while ($data = $leave_type->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["leave_day_type_id"]; ?>"><?php echo $data["leave_day_type"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" aria-label="Default select example" id="status-selector" onchange="handleLeaveRequestPagination('filter')">
                                                                            <option selected value="0">- Filter Leave Status -</option>
                                                                            <?php
                                                                            $leave_type = Database::search("SELECT * FROM `leave_request_status`");
                                                                            while ($data = $leave_type->fetch_assoc()) {
                                                                            ?>
                                                                                <option value="<?php echo $data["leave_request_status_id"]; ?>"><?php echo $data["leave_request_status"]; ?></option>
                                                                            <?php
                                                                            }
                                                                            ?>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="show-result-selector" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleLeaveRequestPagination('filter')">
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
                                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                <div class="d-flex justify-content-between align-items-center w-100" onclick="handleLeaveRequestPagination('sortByName')">
                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                    <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                                </div>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Leave Type</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Day Type</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Date Range</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Days</th>
                                                                        <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleLeaveRequestPagination('sortByDate')">
                                                                            <div class="d-flex justify-content-between align-items-center w-100">
                                                                                <span class="text-14 main-text fw-medium">Requested At</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                                                        <?php

                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4) || UserPermissions::hasPermission('users', 10)) {
                                                                        ?>
                                                                            <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
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
                                                                        $statusStyles = [
                                                                            1 => "table-btn-yellow",   // Pending
                                                                            2 => "table-btn-success",   // Approved
                                                                            3 => "table-btn-danger",    // Rejected
                                                                            4 => "table-btn-gray",       // Cancelled
                                                                            5 => "table-btn-pink"       // Cancelled
                                                                        ];
                                                                        while ($data = $users->fetch_assoc()) {

                                                                            $statusClass = $statusStyles[$data["leave_request_status_id"]] ?? "table-btn-gray";
                                                                    ?>
                                                                            <tr>

                                                                                <!-- Employee -->
                                                                                <td class="px-4 py-3 pe-0 align-middle">
                                                                                    <div class="d-flex align-items-center">

                                                                                        <?php
                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                        ?>
                                                                                            <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40">
                                                                                        <?php
                                                                                        } else {
                                                                                            $fn = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                            $ln = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                                                                <?php echo $fn . $ln; ?>
                                                                                            </div>
                                                                                        <?php } ?>

                                                                                        <span class="text-14 main-text mx-2">
                                                                                            <?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?>
                                                                                        </span>
                                                                                    </div>
                                                                                </td>

                                                                                <!-- Leave Type -->
                                                                                <td class="py-3">
                                                                                    <span class="text-14 main-text"><?php echo $data["leave_type"]; ?></span>
                                                                                </td>

                                                                                <!-- Day Type -->
                                                                                <td class="py-3">
                                                                                    <span class="text-14 sub-text"><?php echo $data["leave_day_type"]; ?></span>
                                                                                </td>

                                                                                <!-- Date Range -->
                                                                                <td class="py-3">
                                                                                    <span class="text-14 main-text">
                                                                                        <?php echo $data["from_date"]; ?> → <?php echo $data["to_date"]; ?>
                                                                                    </span>
                                                                                </td>

                                                                                <!-- Days -->
                                                                                <td class="py-3 text-start">
                                                                                    <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-0">
                                                                                        <?php echo $data["total_days"]; ?>
                                                                                    </span>
                                                                                </td>

                                                                                <!-- Requested -->
                                                                                <td class="py-3">
                                                                                    <span class="text-14 main-text">
                                                                                        <?php echo date("Y-m-d h:i A", strtotime($data["requested_at"])); ?>
                                                                                    </span>
                                                                                </td>

                                                                                <!-- Status -->
                                                                                <td class="py-3 text-center">
                                                                                    <button class="table-btn <?php echo $statusClass; ?> w-100">
                                                                                        <?php echo $data["leave_request_status"]; ?>
                                                                                    </button>
                                                                                </td>

                                                                                <!-- Actions -->
                                                                                <td class="py-3 align-middle text-center">
                                                                                    <div class="d-flex justify-content-center align-items-center">
                                                                                        <div class="dropdown">
                                                                                            <iconify-icon
                                                                                                icon="charm:menu-kebab"
                                                                                                width="20"
                                                                                                height="20"
                                                                                                class="cursor-pointer"
                                                                                                data-bs-toggle="dropdown">
                                                                                            </iconify-icon>

                                                                                            <ul class="dropdown-menu shadow border-0 br-5">
                                                                                                <li>
                                                                                                    <a class="dropdown-item d-flex align-items-center"
                                                                                                        href="leave-request-view?leave-id=<?php echo $data['leave_request_id']; ?>">
                                                                                                        <iconify-icon icon="mdi:eye-outline" width="20" height="20" class="me-2"></iconify-icon>
                                                                                                        View
                                                                                                    </a>
                                                                                                </li>
                                                                                            </ul>
                                                                                        </div>
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLeaveRequestPagination('previousPage')">
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
                                                                                    $action = $isActive ? '' : "onclick=\"handleLeaveRequestPagination('goToPage', '{$i}')\"";
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLeaveRequestPagination('nextPage',null,'<?= $number_of_pages ?>')">
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
        </body>

        </html>
<?php
    }
}
?>