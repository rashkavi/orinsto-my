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

        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['department']) && !empty($_GET['department']))) {
            header("Location: ./dashboard");
            exit();
        } else {
            $department = $_GET['department'];
            if (!($department == 'hr' || $department == 'finance')) {
                header("Location: ./dashboard");
                exit();
            } else {

                if ($department == 'hr' && !(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('users'))) {
                    header("Location: ./dashboard");
                    exit();
                }

                if ($department == 'finance' && !(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                    header("Location: ./dashboard");
                    exit();
                }

                $query = "
        SELECT 
            payroll_runs.*,
    
            COUNT(pre.payroll_run_employee_id) AS total_employees,
    
            SUM(
                CASE 
                    WHEN pre.employee_payroll_status = 'DRAFT' 
                    THEN 1 
                    ELSE 0 
                END
            ) AS draft_count,
            SUM(
                CASE 
                    WHEN pre.employee_payroll_status = 'VERIFIED' 
                    THEN 1 
                    ELSE 0 
                END
            ) AS verify_count,
              SUM(
                CASE 
                    WHEN pre.employee_payroll_status = 'APPROVED' 
                    THEN 1 
                    ELSE 0 
                END
            ) AS approve_count
    
        FROM payroll_runs
    
        LEFT JOIN payroll_run_employees pre 
            ON payroll_runs.payroll_run_id = pre.payroll_runs_payroll_run_id
    
        GROUP BY payroll_runs.payroll_run_id
    
        ORDER BY payroll_runs.payroll_year DESC, payroll_runs.payroll_month DESC
    ";

                $results = Database::search($query);
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Payroll Runs - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                $department == 'hr' ? load_side_menu::load("payroll-runs") : load_side_menu::load("finance-payroll-runs");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        $department == 'hr' ? selectItem::setSelected("payroll-runs") : selectItem::setSelected("finance-payroll-runs");
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
                                                            <span class="main-topic lh-lg" data-i18n="">Payroll Runs</span>
                                                            <span class="text-14 sub-text lh-base mt-1" data-i18n="users-desc">Effortlessly manage users, access profiles, and maintain oversight</span>
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

                                                                    <span class="text-14 dark-theme fw-medium p-0 m-0"><?= $results->num_rows ?> Runs</span>
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
                                                                        <div class="row gy-3">

                                                                        </div>
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
                                                                <div class="table-responsive overflow-visible" id="users-table-container">
                                                                    <table class="table bl-solid m-0 p-0 g-0">
                                                                        <thead>
                                                                            <tr class="br-t-5 align-content-center">
                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Payroll Month</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Period</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Employees</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Gross Salary</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Deductions</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Net Salary</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                            </tr>
                                                                        </thead>

                                                                        <tbody>
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
                                                                            $runs = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");


                                                                            if ($runs->num_rows > 0) {
                                                                                while ($run = $runs->fetch_assoc()) {
                                                                                    $statusClass = [
                                                                                        "DRAFT" => "table-btn-yellow",
                                                                                        "CALCULATED" => "table-btn-primary",
                                                                                        "VERIFIED" => "table-btn-pink",
                                                                                        "APPROVED" => "table-btn-success",
                                                                                        "PAID" => "table-btn-green-blue",
                                                                                        "LOCKED" => "table-btn-purple",
                                                                                        "CANCELLED" => "table-btn-danger",
                                                                                    ][$run["payroll_status"]] ?? "table-btn-primary";

                                                                                    $monthYear = date("F", mktime(0, 0, 0, $run["payroll_month"], 1)) . " " . $run["payroll_year"];
                                                                            ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3 align-middle">
                                                                                            <span class="text-14 main-text fw-semibold">
                                                                                                <?= $monthYear ?>
                                                                                            </span>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle">
                                                                                            <span class="text-14 main-text">
                                                                                                <?= date("Y-m-d", strtotime($run["payroll_period_start"])) ?> -
                                                                                                <?= date("Y-m-d", strtotime($run["payroll_period_end"])) ?>
                                                                                            </span>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle">
                                                                                            <span class="text-14 main-text"><?= $run["total_employees"] ?></span>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle text-end">
                                                                                            <span class="text-14 main-text fw-semibold"><?= number_format($run["total_gross_salary"], 2) ?></span>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle text-end">
                                                                                            <span class="text-14 text-dark-red fw-semibold"><?= number_format($run["total_deductions"], 2) ?></span>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle text-end">
                                                                                            <span class="text-14 text-dark-green fw-semibold"><?= number_format($run["total_net_salary"], 2) ?></span>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle">
                                                                                            <button class="table-btn <?= $statusClass ?> w-100">
                                                                                                <?= $run["payroll_status"] ?>
                                                                                            </button>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle">
                                                                                            <div class="dropdown">
                                                                                                <iconify-icon icon="charm:menu-kebab"
                                                                                                    width="20"
                                                                                                    height="20"
                                                                                                    class="sub-text cursor-pointer"
                                                                                                    data-bs-toggle="dropdown"></iconify-icon>

                                                                                                <ul class="dropdown-menu border-0 shadow br-5">
                                                                                                    <li>
                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                                                                            href="payroll-run-details?payroll-run-id=<?= $run['payroll_run_id'] ?>&department=<?= $department ?>">
                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            View
                                                                                                        </a>
                                                                                                    </li>

                                                                                                    <?php
                                                                                                    if ($department == 'hr') {
                                                                                                        if ($run["payroll_status"] == "CALCULATED" && (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 27))) {
                                                                                                            if ($run['draft_count'] > 0) {
                                                                                                    ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollRunsMessageModal(<?= $run['payroll_run_id'] ?>,'<?= $department ?>')">

                                                                                                                        <iconify-icon
                                                                                                                            icon="material-symbols:sync-rounded"
                                                                                                                            width="20"
                                                                                                                            height="20"
                                                                                                                            class="sub-text me-3">
                                                                                                                        </iconify-icon>

                                                                                                                        Update Status
                                                                                                                    </a>
                                                                                                                </li>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollRunsStatusModal(<?= $run['payroll_run_id'] ?>,'<?= $run['payroll_status'] ?>')">

                                                                                                                        <iconify-icon
                                                                                                                            icon="material-symbols:sync-rounded"
                                                                                                                            width="20"
                                                                                                                            height="20"
                                                                                                                            class="sub-text me-3">
                                                                                                                        </iconify-icon>

                                                                                                                        Update Status
                                                                                                                    </a>
                                                                                                                </li>
                                                                                                            <?php
                                                                                                            }
                                                                                                        }

                                                                                                        if ($run["payroll_status"] == "VERIFIED" && (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 28))) {
                                                                                                            if ($run['verify_count'] > 0) {
                                                                                                            ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollRunsMessageModal(<?= $run['payroll_run_id'] ?>,'<?= $department ?>')">

                                                                                                                        <iconify-icon
                                                                                                                            icon="material-symbols:sync-rounded"
                                                                                                                            width="20"
                                                                                                                            height="20"
                                                                                                                            class="sub-text me-3">
                                                                                                                        </iconify-icon>

                                                                                                                        Update Status
                                                                                                                    </a>
                                                                                                                </li>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollRunsStatusModal(<?= $run['payroll_run_id'] ?>,'<?= $run['payroll_status'] ?>')">

                                                                                                                        <iconify-icon
                                                                                                                            icon="material-symbols:sync-rounded"
                                                                                                                            width="20"
                                                                                                                            height="20"
                                                                                                                            class="sub-text me-3">
                                                                                                                        </iconify-icon>

                                                                                                                        Update Status
                                                                                                                    </a>
                                                                                                                </li>
                                                                                                            <?php
                                                                                                            }
                                                                                                        }
                                                                                                    }

                                                                                                    if ($department == 'finance') {
                                                                                                        if ($run["payroll_status"] == "VERIFIED") {
                                                                                                            if ($run['approve_count'] > 0) {
                                                                                                            ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollRunsMessageModal(<?= $run['payroll_run_id'] ?>,'<?= $department ?>')">

                                                                                                                        <iconify-icon
                                                                                                                            icon="material-symbols:sync-rounded"
                                                                                                                            width="20"
                                                                                                                            height="20"
                                                                                                                            class="sub-text me-3">
                                                                                                                        </iconify-icon>

                                                                                                                        Update Status
                                                                                                                    </a>
                                                                                                                </li>
                                                                                                            <?php
                                                                                                            } else {
                                                                                                            ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollRunsStatusModal(<?= $run['payroll_run_id'] ?>,'<?= $run['payroll_status'] ?>')">
                                                                                                                        <iconify-icon
                                                                                                                            icon="material-symbols:sync-rounded"
                                                                                                                            width="20"
                                                                                                                            height="20"
                                                                                                                            class="sub-text me-3">
                                                                                                                        </iconify-icon>
                                                                                                                        Update Status
                                                                                                                    </a>
                                                                                                                </li>
                                                                                                    <?php
                                                                                                            }
                                                                                                        }
                                                                                                    }
                                                                                                    ?>
                                                                                                    <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                    <li>
                                                                                                        <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                                                                            href="#" onclick="genaratePayrollRunsReport(<?= $run['payroll_run_id'] ?>,'<?= $monthYear ?>')">
                                                                                                            <iconify-icon icon="line-md:download" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            Download Report
                                                                                                        </a>
                                                                                                    </li>
                                                                                                </ul>
                                                                                            </div>
                                                                                        </td>
                                                                                    </tr>
                                                                                <?php
                                                                                }
                                                                            } else {
                                                                                ?>
                                                                                <tr>
                                                                                    <td colspan="8" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                                                                    </td>
                                                                                </tr>
                                                                            <?php } ?>
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

                    <div class="modal fade" id="updatePayrollStatusModal" tabindex="-1" role="dialog" aria-labelledby="updatePayrollStatusModalTitle" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="exampleModalLongTitle">Update Payroll Status</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="col-12 p-0 g-0">
                                        <div class="row gx-4 gy-3">
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text required" for="">Payroll Status</label>
                                                <select class="form-select custom-select mt-2" id="status-selector">
                                                    <option value="VERIFIED">Verify</option>
                                                    <option value="APPROVED" class="d-none">Approve</option>
                                                    <option value="PAID">Mark as Paid</option>
                                                    <option value="LOCKED">Locked</option>
                                                    <option value="CANCELLED">Cancell</option>
                                                </select>
                                                <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                            </div>
                                            <div class="col-12 d-flex flex-column">
                                                <label class="text-14 main-text" for="">Note</label>
                                                <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                                <span id="note-feedback" class="text-dark-red text-12"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="col-12 col-md-4 custom-btn" id="update-payroll-status">Update</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="payrollRunMessageModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow br-12">
                                <div class="modal-body p-4 text-center">

                                    <!-- Action Blocked Warning Icon -->
                                    <div class="mb-3">
                                        <iconify-icon icon="solar:danger-triangle-bold"
                                            width="54"
                                            height="54"
                                            style="color:#dc3545;"></iconify-icon>
                                    </div>

                                    <!-- Main Heading -->
                                    <h5 class="main-text fw-bold mb-2">
                                        Action Blocked: Pending Paysheets
                                    </h5>

                                    <!-- Restriction Message -->
                                    <p class="sub-text text-14 mb-4">
                                        The monthly payroll status update **cannot be completed** at this stage.
                                        There are still pending approval paysheets remaining. Please complete or approve all outstanding paysheets before attempting to update the overall cycle status.
                                    </p>

                                    <!-- Action Buttons -->
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <button type="button"
                                                class="btn btn-light h-48 w-100 br-0"
                                                data-bs-dismiss="modal">
                                                Close
                                            </button>
                                        </div>

                                        <div class="col-6">
                                            <!-- Redirects or focuses user directly to the problem area -->
                                            <a href="" id="pending-paysheets-link"
                                                class="custom-btn w-100 d-flex align-items-center justify-content-center text-decoration-none h-48">
                                                View Paysheets
                                            </a>
                                        </div>
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
?>