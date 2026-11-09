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

        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payroll-run-id']) && !empty($_GET['payroll-run-id']))) {
            header("Location: ./users");
        } elseif (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['department']) && !empty($_GET['department']))) {
            header("Location: ./dashboard");
            exit();
        } else {
            $id = $_GET['payroll-run-id'];
            $department = $_GET['department'];
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if ($department == 'hr' && !(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('users'))) {
                header("Location: ./dashboard");
                exit();
            }

            if ($department == 'finance' && !(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                header("Location: ./dashboard");
                exit();
            }


            $isValidPayrollRun = Database::search("SELECT * FROM `payroll_runs` WHERE `payroll_run_id`='" . $id . "'");
            if ($isValidPayrollRun->num_rows == 0) {
                header("Location: ./payroll-runs");
            } else {
                $payrollRunData = $isValidPayrollRun->fetch_assoc();

                $query = "
                SELECT 
                    pre.*,
                    u.user_id,
                u.user_fname,
                u.user_lname,
                u.user_email,
                u.profile_img,
                t.title
                    FROM payroll_run_employees pre
                    INNER JOIN users u ON pre.users_user_id = u.user_id
                    INNER JOIN title t ON u.title_title_id = t.title_id
                    WHERE pre.payroll_runs_payroll_run_id = '" . $id . "'
                    ORDER BY u.user_fname ASC
                ";

                $results = Database::search($query);

                $monthYear = date("F", mktime(0, 0, 0, $payrollRunData["payroll_month"], 1)) . " " . $payrollRunData["payroll_year"];

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Payroll Runs Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                                            <span class="main-topic lh-lg" data-i18n=""><?= $monthYear ?></span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./payroll-runs">Payroll Runs</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payroll Run Employees Paysheets</a></li>
                                                                </ol>
                                                            </nav>
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
                                                                            <tr>
                                                                                <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Employee</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Basic</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Gross</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">EPF</th>
                                                                                <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">ETF</th>
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
                                                                            $employees = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");


                                                                            if ($employees->num_rows > 0) {

                                                                                while ($emp = $employees->fetch_assoc()) {
                                                                                    $userName = $emp["user_fname"] . " " . $emp["user_lname"] . "(" . $emp["user_id"] . ")";
                                                                                    $statusClass = [
                                                                                        "DRAFT" => "table-btn-yellow",
                                                                                        "CALCULATED" => "table-btn-primary",
                                                                                        "VERIFIED" => "table-btn-pink",
                                                                                        "APPROVED" => "table-btn-success",
                                                                                        "PAID" => "table-btn-green-blue",
                                                                                        "LOCKED" => "table-btn-purple",
                                                                                        "CANCELLED" => "table-btn-danger",
                                                                                    ][$emp["employee_payroll_status"]] ?? "table-btn-primary";
                                                                            ?>
                                                                                    <tr>
                                                                                        <td class="px-4 py-3 align-middle">
                                                                                            <div class="d-flex align-items-center">
                                                                                                <div class="hwr-40 bg-light-theme dark-theme d-flex align-items-center justify-content-center fw-medium">
                                                                                                    <?= strtoupper(substr($emp["user_fname"], 0, 1) . substr($emp["user_lname"], 0, 1)) ?>
                                                                                                </div>

                                                                                                <div class="ms-2">
                                                                                                    <div class="text-14 main-text fw-semibold">
                                                                                                        <?= $emp["title"] . ". " . $emp["user_fname"] . " " . $emp["user_lname"] ?>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </td>

                                                                                        <td class="py-3 align-middle text-end"><?= number_format($emp["basic_salary"], 2) ?></td>
                                                                                        <td class="py-3 align-middle text-end"><?= number_format($emp["gross_salary"], 2) ?></td>
                                                                                        <td class="py-3 align-middle text-end"><?= number_format($emp["employee_epf"], 2) ?></td>
                                                                                        <td class="py-3 align-middle text-end"><?= number_format($emp["etf"], 2) ?></td>
                                                                                        <td class="py-3 align-middle text-end text-dark-red fw-semibold"><?= number_format($emp["total_deductions"], 2) ?></td>
                                                                                        <td class="py-3 align-middle text-end text-dark-green fw-semibold"><?= number_format($emp["net_salary"], 2) ?></td>

                                                                                        <td class="py-3 align-middle">
                                                                                            <button class="table-btn <?= $statusClass ?> w-100">
                                                                                                <?= $emp["employee_payroll_status"] ?>
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
                                                                                                            href="payroll-employee-details?payroll-run-employee-id=<?= $emp['payroll_run_employee_id'] ?>&department=<?= $department ?>">
                                                                                                            <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                            View
                                                                                                        </a>
                                                                                                    </li>
                                                                                                    <?php
                                                                                                    if ($department == 'hr') {
                                                                                                        if ($payrollRunData["payroll_status"] == "CALCULATED" && $emp["employee_payroll_status"] == "DRAFT") {
                                                                                                    ?>
                                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                            <li>
                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                                                                                    href="payroll-employee-details?payroll-run-employee-id=<?= $emp['payroll_run_employee_id'] ?>&department=<?= $department ?>">
                                                                                                                    <iconify-icon icon="line-md:edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                    Edit
                                                                                                                </a>
                                                                                                            </li>
                                                                                                            <?php
                                                                                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 27)) {
                                                                                                            ?>
                                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                                <li>
                                                                                                                    <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                        onclick="openPayrollStatusModal(<?= $emp['payroll_run_employee_id'] ?>,'<?= $emp['employee_payroll_status'] ?>')">

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

                                                                                                            ?>
                                                                                                        <?php
                                                                                                        }
                                                                                                        if ($emp["employee_payroll_status"] == "VERIFIED" && (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission("users", 28))) {
                                                                                                        ?>
                                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                            <li>
                                                                                                                <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                    onclick="openPayrollStatusModal(<?= $emp['payroll_run_employee_id'] ?>,'<?= $emp['employee_payroll_status'] ?>')">

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

                                                                                                    if ($department == "finance" && $emp["employee_payroll_status"] == "APPROVED") {
                                                                                                        ?>
                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                        <li>
                                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center cursor-pointer"
                                                                                                                onclick="openPayrollStatusModal(<?= $emp['payroll_run_employee_id'] ?>,'<?= $emp['employee_payroll_status'] ?>')">

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

                                                                                                    if ($emp["employee_payroll_status"] == "PAID") {
                                                                                                    ?>
                                                                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                        <li>
                                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center"
                                                                                                                href="#" onclick="genaratePayrollEmployeePaysheet(<?= $emp['payroll_run_employee_id'] ?>,'<?= $userName ?>')">
                                                                                                                <iconify-icon icon="line-md:download" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                                Download Pay Sheet
                                                                                                            </a>
                                                                                                        </li>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
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
                                                    <option value="APPROVED">Approve</option>
                                                    <option value="PAID">Mark as Paid</option>
                                                    <option value="HOLD">Hold</option>
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