<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/classes/StaffPayments.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['user-id']) && !empty($_GET['user-id']))) {
            header("Location: ./users");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 1) || UserPermissions::hasPermission('users', 10))) {
                header("Location: ./users");
            } else {
                require_once "./includes/connection.php";
                $id = $_GET['user-id'];
                $user = Database::search("SELECT * FROM `users` 
                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                        INNER JOIN `status` ON `users`.`status_status_id`=`status`.`status_id` WHERE `users`.`user_id`='" . $id . "'");
                if ($user->num_rows == 0) {
                    header("Location: ./users");
                } else {
                    $udata = $user->fetch_assoc();

                    $profileRs = Database::search("
                    SELECT 
                        pp.*,
                        u.user_fname,
                        u.user_lname,
                        t.title
                    FROM payroll_profiles pp
                    INNER JOIN users u ON pp.users_user_id = u.user_id
                    INNER JOIN title t ON u.title_title_id = t.title_id
                    WHERE pp.users_user_id = '$id'
                    AND pp.status_status_id = '1'
                    LIMIT 1
                ");
                

?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Edit User - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                                    load_side_menu::load("users");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("Users");
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
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./users">Users</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">User Payroll Profile</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                        <?php
                                                        if ($profileRs->num_rows > 0) {
                                                        ?>
                                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" type="button" onclick="window.location.href='edit-user-payroll-profile?user-id=<?= $udata['user_id'] ?>';">
                                                                    Edit Payroll Profile
                                                                </button>
                                                            </div>
                                                        <?php
                                                        } else {
                                                        ?>
                                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" type="button" onclick="window.location.href='create-user-payroll-profile?user-id=<?= $udata['user_id'] ?>';">
                                                                    Create Payroll Profile
                                                                </button>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>


                                                    </div>
                                                </div>

                                                <?php

                                                if ($profileRs->num_rows == 0) {
                                                ?>
                                                    <div class="component-theme p-4">
                                                        <div class="text-center py-5">
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
                                                } else {
                                                    $profile = $profileRs->fetch_assoc();

                                                    $componentsRs = Database::search("
                                                            SELECT *
                                                            FROM payroll_profile_components
                                                            INNER JOIN payroll_components ON payroll_profile_components.payroll_components_payroll_component_id = payroll_components.payroll_component_id
                                                            WHERE payroll_profiles_payroll_profile_id = '" . $profile['payroll_profile_id'] . "'
                                                            AND status_status_id = '1'
                                                            ORDER BY component_type ASC, payroll_profile_component_id ASC
                                                        ");

                                                    // ========================================
                                                    // CORRECTED SALARY CALCULATION LOGIC
                                                    // ========================================
                                                    $basicSalary = (float)$profile['basic_salary'];
                                                    $totalEarnings = 0;
                                                    $totalDeductionsGross = 0;  // Deductions based on GROSS salary
                                                    $totalDeductionsNet = 0;    // Deductions based on NET salary
                                                    $components = [];

                                                    while ($component = $componentsRs->fetch_assoc()) {
                                                        $components[] = $component;

                                                        if ($component['component_type'] == 'EARNING') {
                                                            $totalEarnings += (float)$component['amount'];
                                                        } else {
                                                            // Separate deductions by their calculation base
                                                            $calculationBase = isset($component['deduction_calculation_base']) ? $component['deduction_calculation_base'] : 'GROSS';
                                                            
                                                            if ($calculationBase == 'GROSS') {
                                                                $totalDeductionsGross += (float)$component['amount'];
                                                            } else {
                                                                $totalDeductionsNet += (float)$component['amount'];
                                                            }
                                                        }
                                                    }

                                                    // Step 1: Calculate Gross Salary
                                                    $grossSalary = $basicSalary + $totalEarnings;

                                                    // Step 2: Apply GROSS-based deductions first
                                                    $salaryAfterGrossDeductions = $grossSalary - $totalDeductionsGross;
                                                    
                                                    // Step 3: Apply NET-based deductions to the remaining amount
                                                    $netEstimate = $salaryAfterGrossDeductions - $totalDeductionsNet;

                                                    // For display purposes
                                                    $totalDeductions = $totalDeductionsGross + $totalDeductionsNet;

                                                ?>

                                                    <div class="col-12 p-4 component-theme">

                                                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                                                            <div>
                                                                <span class="sub-topic">Payroll Profile</span>
                                                                <p class="text-14 sub-text mb-0 mt-1">
                                                                    Salary setup, statutory rates, bank details and recurring payroll components.
                                                                </p>
                                                            </div>

                                                            <span class="table-btn table-btn-success text-12 px-3 py-2">
                                                                Active Profile
                                                            </span>
                                                        </div>

                                                        <!-- ========================================
                                                             4 KEY BOXES SECTION
                                                             ======================================== -->
                                                        <div class="row g-3 mb-4">

                                                            <div class="col-12 col-md-6 col-xl-3">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="text-12 sub-text text-uppercase">Employee</span>
                                                                    <h6 class="main-text fw-semibold mb-0 mt-2">
                                                                        <?= $profile['title'] . ' ' . $profile['user_fname'] . ' ' . $profile['user_lname'] ?>
                                                                    </h6>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-6 col-xl-3">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="text-12 sub-text text-uppercase">Employment Type</span>
                                                                    <h6 class="main-text fw-semibold mb-0 mt-2">
                                                                        <?= $profile['employment_type'] ?>
                                                                    </h6>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-6 col-xl-3">
                                                                <div class="bl-solid p-3 h-100 bg-light-green">
                                                                    <span class="text-12 text-dark-green text-uppercase fw-semibold">Basic Salary</span>
                                                                    <h5 class="main-text fw-bold mb-0 mt-2">
                                                                        <?= number_format($basicSalary, 2) ?>
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <!-- CORRECTED: Changed from GROSS SALARY to NET SALARY (Take-Home) -->
                                                            <div class="col-12 col-md-6 col-xl-3">
                                                                <div class="bl-solid p-3 h-100 bg-light-blue">
                                                                    <span class="text-12 text-theme text-uppercase fw-semibold">Net Salary</span>
                                                                    <h5 class="main-text fw-bold mb-0 mt-2">
                                                                        <?= number_format($netEstimate, 2) ?>
                                                                    </h5>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Statutory Rates Section -->
                                                        <div class="row g-3 mb-4">
                                                            <div class="col-12 col-md-4">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="text-12 sub-text text-uppercase">EPF Employee</span>
                                                                    <h5 class="main-text fw-bold mb-0 mt-2">
                                                                        <?= number_format($profile['epf_employee_rate'], 2) ?>%
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-4">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="text-12 sub-text text-uppercase">EPF Employer</span>
                                                                    <h5 class="main-text fw-bold mb-0 mt-2">
                                                                        <?= number_format($profile['epf_employer_rate'], 2) ?>%
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-4">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="text-12 sub-text text-uppercase">ETF</span>
                                                                    <h5 class="main-text fw-bold mb-0 mt-2">
                                                                        <?= number_format($profile['etf_rate'], 2) ?>%
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                        </div>

                                                        <!-- Payment Details & Salary Summary -->
                                                        <div class="row g-3 mb-4">

                                                            <div class="col-12 col-lg-6">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="sub-topic text-16">Payment Details</span>

                                                                    <div class="row mt-3 gy-2">
                                                                        <div class="col-5 text-14 sub-text">Employee (EPF/ ETF) number</div>
                                                                        <div class="col-7 text-14 main-text fw-semibold"><?= $profile['epf_no'] ?></div>

                                                                        <div class="col-5 text-14 sub-text">Payment Method</div>
                                                                        <div class="col-7 text-14 main-text fw-semibold"><?= $profile['payment_method'] ?></div>

                                                                        <!-- Salary pay date (used by Payments → Staff Payments to order + highlight due salaries) -->
                                                                        <div class="col-5 text-14 sub-text">Salary Pay Date</div>
                                                                        <div class="col-7 text-14 main-text fw-semibold d-flex align-items-center flex-wrap gap-2">
                                                                            <span id="pay-day-label" class="<?= empty($profile['salary_pay_day']) ? 'text-dark-orange' : '' ?>">
                                                                                <?= htmlspecialchars(StaffPayments::payDayLabel($profile['salary_pay_day'] ?? null, $profile['salary_pay_timing'] ?? 'NEXT_MONTH')) ?>
                                                                            </span>
                                                                            <?php if (StaffPayments::canEditPayDay($sessionUserRole)) { ?>
                                                                                <button type="button" class="table-btn table-btn-dark-theme py-0 px-2 text-12" data-bs-toggle="modal" data-bs-target="#payDayModal">
                                                                                    <?= empty($profile['salary_pay_day']) ? 'Set' : 'Change' ?>
                                                                                </button>
                                                                            <?php } ?></div>

                                                                        <div class="col-5 text-14 sub-text">Bank Name</div>
                                                                        <div class="col-7 text-14 main-text fw-semibold">
                                                                            <?= !empty($profile['bank_name']) ? $profile['bank_name'] : '-' ?>
                                                                        </div>

                                                                        <div class="col-5 text-14 sub-text">Account No</div>
                                                                        <div class="col-7 text-14 main-text fw-semibold">
                                                                            <?= !empty($profile['bank_account_no']) ? $profile['bank_account_no'] : '-' ?>
                                                                        </div>

                                                                        <div class="col-5 text-14 sub-text">Branch</div>
                                                                        <div class="col-7 text-14 main-text fw-semibold">
                                                                            <?= !empty($profile['bank_branch']) ? $profile['bank_branch'] : '-' ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- CORRECTED SALARY SUMMARY WITH PROPER BREAKDOWN -->
                                                            <div class="col-12 col-lg-6">
                                                                <div class="bl-solid p-3 h-100">
                                                                    <span class="sub-topic text-16">Salary Summary</span>

                                                                    <div class="row mt-3 gy-2">
                                                                        <div class="col-6 text-14 sub-text">Basic Salary</div>
                                                                        <div class="col-6 text-14 main-text fw-semibold text-end">
                                                                            <?= number_format($basicSalary, 2) ?>
                                                                        </div>

                                                                        <div class="col-6 text-14 sub-text">Recurring Earnings</div>
                                                                        <div class="col-6 text-14 text-dark-green fw-semibold text-end">
                                                                            <?= number_format($totalEarnings, 2) ?>
                                                                        </div>

                                                                        <div class="col-6 text-14 sub-text">Gross Salary</div>
                                                                        <div class="col-6 text-14 main-text fw-semibold text-end">
                                                                            <?= number_format($grossSalary, 2) ?>
                                                                        </div>

                                                                        <div class="col-6 text-14 sub-text">Deductions (GROSS-based)</div>
                                                                        <div class="col-6 text-14 text-dark-red fw-semibold text-end">
                                                                            (<?= number_format($totalDeductionsGross, 2) ?>)
                                                                        </div>

                                                                        <div class="col-6 text-14 sub-text">Deductions (NET-based)</div>
                                                                        <div class="col-6 text-14 text-dark-red fw-semibold text-end">
                                                                            (<?= number_format($totalDeductionsNet, 2) ?>)
                                                                        </div>

                                                                        <hr class="my-2">

                                                                        <div class="col-6 text-16 main-text fw-bold">Net Salary (Take-Home)</div>
                                                                        <div class="col-6 text-16 main-text fw-bold text-end">
                                                                            <?= number_format($netEstimate, 2) ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </div>

                                                        <!-- Payroll Components Table -->
                                                        <div class="col-12 mt-4">
                                                            <span class="sub-topic">Payroll Components</span>
                                                            <p class="text-14 sub-text mb-3 mt-1">
                                                                Recurring earnings and deductions configured for this payroll profile.
                                                            </p>

                                                            <div class="table-responsive">
                                                                <table class="table align-middle">
                                                                    <thead class="bg-table-header">
                                                                        <tr>
                                                                            <th class="text-14 main-text">Component</th>
                                                                            <th class="text-14 main-text">Type</th>
                                                                            <th class="text-14 main-text text-end">Amount</th>
                                                                            <th class="text-14 main-text text-center">EPF</th>
                                                                            <th class="text-14 main-text text-center">Taxable</th>
                                                                            <th class="text-14 main-text text-center">Recurring</th>
                                                                            <th class="text-14 main-text text-center">Calculate On</th>
                                                                        </tr>
                                                                    </thead>

                                                                    <tbody>
                                                                        <?php if (empty($components)) { ?>
                                                                            <tr>
                                                                                <td colspan="7" class="text-center sub-text text-14 py-4">
                                                                                    No payroll components added.
                                                                                </td>
                                                                            </tr>
                                                                        <?php } ?>

                                                                        <?php foreach ($components as $component) { ?>
                                                                            <tr>
                                                                                <td class="text-14 main-text fw-semibold">
                                                                                    <?= htmlspecialchars($component['payroll_component']) ?>
                                                                                </td>

                                                                                <td>
                                                                                    <?php if ($component['component_type'] == 'EARNING') { ?>
                                                                                        <span class="table-btn table-btn-success text-12 px-3 py-1">Earning</span>
                                                                                    <?php } else { ?>
                                                                                        <span class="table-btn table-btn-danger text-12 px-3 py-1">Deduction</span>
                                                                                    <?php } ?>
                                                                                </td>

                                                                                <td class="text-14 main-text fw-semibold text-end">
                                                                                    <?= number_format((float)$component['amount'], 2) ?>
                                                                                </td>

                                                                                <td class="text-center">
                                                                                    <?= $component['is_epf_applicable'] ? 'Yes' : 'No' ?>
                                                                                </td>

                                                                                <td class="text-center">
                                                                                    <?= $component['is_taxable'] ? 'Yes' : 'No' ?>
                                                                                </td>

                                                                                <td class="text-center">
                                                                                    <?= $component['is_recurring'] ? 'Monthly' : 'One Time' ?>
                                                                                </td>

                                                                                <td class="text-center">
                                                                                    <?php 
                                                                        // Show deduction calculation base only for deductions
                                                                        if ($component['component_type'] == 'DEDUCTION') {
                                                                            $calculationBase = isset($component['deduction_calculation_base']) ? $component['deduction_calculation_base'] : 'GROSS';
                                                                            
                                                                            if ($calculationBase == 'GROSS') {
                                                                                echo '<span class="table-btn table-btn-info text-12 px-2 py-1">Gross</span>';
                                                                            } else {
                                                                                echo '<span class="table-btn table-btn-warning text-12 px-2 py-1">Net</span>';
                                                                            }
                                                                        } else {
                                                                            echo '<span class="text-12 text-muted">-</span>';
                                                                        }
                                                                    ?>
                                                                                </td>
                                                                            </tr>
                                                                        <?php } ?>
                                                                    </tbody>
                                                                </table>
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

                        <?php if (isset($profile) && StaffPayments::canEditPayDay($sessionUserRole)) {
                            $curDay = (int)($profile['salary_pay_day'] ?? 0);
                            $curTiming = $profile['salary_pay_timing'] ?? 'NEXT_MONTH';
                        ?>
                            <!-- Salary pay date modal -->
                            <div class="modal fade" id="payDayModal" tabindex="-1" aria-labelledby="payDayModalTitle" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content p-2">
                                        <div class="modal-header">
                                            <h5 class="modal-title main-text" id="payDayModalTitle">Salary Pay Date</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-14 sub-text">The day this staff member's salary is paid each month. Finance sees it on <b>Payments → Staff Payments</b>, where salaries are ordered by this date and highlighted on the day they are due.</p>
                                            <div class="row g-3">
                                                <div class="col-12 col-md-5 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="pay-day">Day of Month</label>
                                                    <select class="form-select custom-select mt-2" id="pay-day">
                                                        <option value="">- Select -</option>
                                                        <?php for ($d = 1; $d <= 31; $d++) { ?>
                                                            <option value="<?= $d ?>" <?= $curDay === $d ? 'selected' : '' ?>><?= $d === 31 ? '31 / Last day' : $d ?></option>
                                                        <?php } ?>
                                                    </select>
                                                </div>
                                                <div class="col-12 col-md-7 d-flex flex-column">
                                                    <label class="text-14 main-text required" for="pay-timing">Paid In</label>
                                                    <select class="form-select custom-select mt-2" id="pay-timing">
                                                        <option value="SAME_MONTH" <?= $curTiming === 'SAME_MONTH' ? 'selected' : '' ?>>Same month (e.g. 25 Sep for September)</option>
                                                        <option value="NEXT_MONTH" <?= $curTiming === 'NEXT_MONTH' ? 'selected' : '' ?>>Following month (e.g. 5 Oct for September)</option>
                                                    </select>
                                                </div>
                                                <div class="col-12">
                                                    <span class="text-12 sub-text">If the day doesn't exist in a month (e.g. 30 in February), the last day of that month is used.</span>
                                                </div>
                                            </div>
                                            <span class="text-12 text-dark-red d-block mt-2" id="pay-day-error"></span>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                                            <button type="button" class="custom-btn px-4" id="pay-day-save">Save</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', () => {
                                    const btn = document.getElementById('pay-day-save');
                                    const err = document.getElementById('pay-day-error');
                                    btn.addEventListener('click', () => {
                                        const day = document.getElementById('pay-day').value;
                                        const timing = document.getElementById('pay-timing').value;
                                        err.textContent = '';
                                        if (!day) { err.textContent = 'Select the day of the month.'; return; }
                                        btn.disabled = true;
                                        fetch('api/staffPayments.php', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ action: 'set_pay_day', payroll_profile_id: <?= (int)$profile['payroll_profile_id'] ?>, pay_day: day, pay_timing: timing })
                                        }).then(r => r.json()).then(d => {
                                            if (d.error) { err.textContent = d.error; return; }
                                            const label = document.getElementById('pay-day-label');
                                            label.textContent = d.label;
                                            label.classList.remove('text-dark-orange');
                                            if (typeof showToast === 'function') showToast('success', d.done);
                                            bootstrap.Modal.getOrCreateInstance(document.getElementById('payDayModal')).hide();
                                        }).catch(() => { err.textContent = 'Something went wrong. Please try again.'; })
                                          .finally(() => { btn.disabled = false; });
                                    });
                                });
                            </script>
                        <?php } ?>

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