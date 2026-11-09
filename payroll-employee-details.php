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

        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payroll-run-employee-id']) && !empty($_GET['payroll-run-employee-id']))) {
            header("Location: ./users");
        } elseif (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['department']) && !empty($_GET['department']))) {
            header("Location: ./dashboard");
            exit();
        } else {
            $id = $_GET['payroll-run-employee-id'];
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

            $isValidEmployee = Database::search("
            SELECT 
                pre.*,
                pr.payroll_month,
                pr.payroll_year,
                pr.payroll_status,
                pre.employee_payroll_status,
                u.user_fname,
                u.user_lname,
                u.user_email,
                t.title
            FROM payroll_run_employees pre
            INNER JOIN payroll_runs pr 
                ON pre.payroll_runs_payroll_run_id = pr.payroll_run_id
            INNER JOIN users u 
                ON pre.users_user_id = u.user_id
            INNER JOIN title t 
                ON u.title_title_id = t.title_id
            WHERE pre.payroll_run_employee_id = '$id'
            LIMIT 1
        ");
            if ($isValidEmployee->num_rows == 0) {
                header("Location: ./payroll-runs");
            } else {
                $employee = $isValidEmployee->fetch_assoc();

                $components = Database::search("
                SELECT *
                FROM payroll_run_employee_components
                INNER JOIN payroll_components ON payroll_run_employee_components.payroll_components_payroll_component_id=payroll_components.payroll_component_id
                WHERE payroll_run_employees_payroll_run_employee_id = '$id'
                ORDER BY component_type ASC, payroll_run_employee_component_id ASC
            ");

                $profile_components = Database::search("SELECT `payroll_component_id`, `payroll_component` FROM `payroll_components`");
                $payroll_components_json = [];
                while ($comp = $profile_components->fetch_assoc()) {
                    $payroll_components_json[] = $comp;
                }
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
                    <link rel="stylesheet" href="./assets/css/custom-accordian.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/custom-accordian.css?v=<?= Project::getVersion() ?>">
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    <style>
                        /**
 * Payroll Deduction Base Styles
 * Styling for deduction calculation base UI components
 */

                        /* Deduction Base Container */
                        .component-deduction-base-container {
                            transition: all 0.3s ease;
                        }

                        /* Component Card Enhancements */
                        .payroll-employee-component-card {
                            transition: all 0.3s ease;
                            border-left: 4px solid transparent;
                        }

                        .payroll-employee-component-card[data-component-type="EARNING"] {
                            border-left-color: #28a745;
                        }

                        .payroll-employee-component-card[data-component-type="DEDUCTION"] {
                            border-left-color: #dc3545;
                        }

                        .payroll-employee-component-card[data-component-type="DEDUCTION"][data-deduction-base="GROSS"] {
                            border-left-color: #fd7e14;
                        }

                        .payroll-employee-component-card[data-component-type="DEDUCTION"][data-deduction-base="NET"] {
                            border-left-color: #0d6efd;
                        }

                        /* Component Card Summary */
                        .component-card-summary {
                            display: block;
                            font-size: 12px;
                            color: #6c757d;
                            margin-top: 4px;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                        }

                        .component-card-summary::before {
                            content: "→ ";
                            margin-right: 4px;
                            color: #999;
                        }

                        /* Salary Snapshot Cards */
                        .bl-solid {
                            border-left: 4px solid;
                            border-radius: 4px;
                        }

                        .bg-light-green {
                            background-color: #f1f8f4;
                        }

                        .text-dark-green {
                            color: #155724;
                        }

                        .bg-light-orange {
                            background-color: #fff8f0;
                        }

                        .text-dark-orange {
                            color: #ff8c00;
                        }

                        .bg-light-red {
                            background-color: #fef5f5;
                        }

                        .text-dark-red {
                            color: #dc3545;
                        }

                        .bg-light-blue {
                            background-color: #f0f7ff;
                        }

                        .text-theme {
                            color: #1e3a5f;
                        }

                        .bg-light-purple {
                            background-color: #f3f0ff;
                        }

                        .text-dark-purple {
                            color: #663399;
                        }

                        /* Salary Card Styling */
                        .bl-solid.p-3 {
                            padding: 1.5rem;
                        }

                        .bl-solid h5 {
                            font-size: 18px;
                            font-weight: 600;
                            margin-bottom: 4px;
                        }

                        .bl-solid span.text-12 {
                            font-size: 12px;
                            font-weight: 600;
                            text-transform: uppercase;
                            letter-spacing: 0.5px;
                        }

                        .bl-solid span.text-11 {
                            font-size: 11px;
                            font-weight: 500;
                            opacity: 0.8;
                        }

                        /* Component Type Badge */
                        .component-type-badge {
                            display: inline-block;
                            padding: 2px 8px;
                            border-radius: 12px;
                            font-size: 11px;
                            font-weight: 600;
                            text-transform: uppercase;
                            margin-left: 8px;
                        }

                        .component-type-badge.earning {
                            background-color: #d4edda;
                            color: #155724;
                        }

                        .component-type-badge.deduction {
                            background-color: #f8d7da;
                            color: #721c24;
                        }

                        /* Deduction Base Badge */
                        .deduction-base-badge {
                            display: inline-block;
                            padding: 2px 8px;
                            border-radius: 12px;
                            font-size: 10px;
                            font-weight: 600;
                            text-transform: uppercase;
                            margin-left: 4px;
                        }

                        .deduction-base-badge.gross {
                            background-color: #fff3cd;
                            color: #856404;
                        }

                        .deduction-base-badge.net {
                            background-color: #cfe2ff;
                            color: #084298;
                        }

                        /* Component Header Content */
                        .component-header-content {
                            display: flex;
                            flex-direction: column;
                            gap: 4px;
                            width: 100%;
                            text-align: left;
                        }

                        .component-card-title {
                            font-size: 14px;
                            font-weight: 600;
                        }

                        /* Payroll Accordion Styles */
                        .payroll-component-header {
                            margin-bottom: 0;
                        }

                        .payroll-component-toggle {
                            padding: 1rem 1.5rem;
                        }

                        .payroll-component-toggle:not(.collapsed) {
                            background-color: #f8f9fa;
                            border-color: #dee2e6;
                        }

                        .payroll-component-toggle.collapsed {
                            opacity: 0.85;
                        }

                        .payroll-component-toggle.collapsed:hover {
                            opacity: 1;
                        }

                        /* Component Form Styling */
                    
                        .component-deduction-base-container .text-11 {
                            font-size: 11px;
                            color: #6c757d;
                        }

                        /* Feedback/Error Styling */
                        .component-name-feedback,
                        .component-type-feedback,
                        .component-amount-feedback,
                        .component-epf-feedback,
                        .component-taxable-feedback,
                        .component-deduction-base-feedback {
                            display: block;
                            margin-top: 4px;
                            font-size: 12px;
                            color: #dc3545;
                            min-height: 16px;
                        }

                        .text-dark-red {
                            color: #dc3545;
                        }

                        .text-12 {
                            font-size: 12px;
                        }

                        /* Delete Button Styling */
                        .custom-btn-danger {
                            background-color: #dc3545;
                            color: white;
                            border: 1px solid #dc3545;
                            transition: all 0.3s ease;
                        }

                        .custom-btn-danger:hover {
                            background-color: #c82333;
                            border-color: #bd2130;
                        }

                        /* Responsive Adjustments */
                        @media (max-width: 768px) {
                            .bl-solid {
                                margin-bottom: 1rem;
                            }

                            .bl-solid h5 {
                                font-size: 16px;
                            }

                            .component-card-summary {
                                white-space: normal;
                                margin-top: 6px;
                            }
                        }

                        /* Animation for Component Addition */
                        @keyframes slideInComponent {
                            from {
                                opacity: 0;
                                transform: translateY(-10px);
                            }

                            to {
                                opacity: 1;
                                transform: translateY(0);
                            }
                        }

                        .payroll-employee-component-card {
                            animation: slideInComponent 0.3s ease;
                        }

                        /* Loading State */
                        .payroll-calculating {
                            opacity: 0.6;
                            pointer-events: none;
                        }

                        /* Tooltip Styling */
                        .component-tooltip {
                            font-size: 11px;
                            color: #6c757d;
                            font-weight: 400;
                            margin-top: 4px;
                            display: block;
                        }

                        /* Notification Styling */
                        .alert {
                            border-radius: 4px;
                            margin-bottom: 1rem;
                        }

                        .alert-success {
                            background-color: #d4edda;
                            border-color: #c3e6cb;
                            color: #155724;
                        }

                        .alert-danger {
                            background-color: #f8d7da;
                            border-color: #f5c6cb;
                            color: #721c24;
                        }

                        /* Print Styling */
                        @media print {
                            .payroll-employee-component-card {
                                border-left: 2px solid #333;
                                page-break-inside: avoid;
                            }

                            .payroll-component-toggle {
                                cursor: default;
                            }

                            .component-card-summary {
                                font-size: 11px;
                            }
                        }

                        /* Accessibility */
                        .component-deduction-base-container .form-select:focus {
                            border-color: #0d6efd;
                            outline: 0;
                            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
                        }

                        /* Dark Mode Support (Optional) */
                        @media (prefers-color-scheme: dark) {
                            .bg-light-green {
                                background-color: #0d3d1d;
                            }

                            .text-dark-green {
                                color: #4ade80;
                            }

                            .bg-light-orange {
                                background-color: #3d2710;
                            }

                            .text-dark-orange {
                                color: #fed7aa;
                            }

                            .bg-light-red {
                                background-color: #3d0a10;
                            }

                            .text-dark-red {
                                color: #fca5a5;
                            }

                            .bg-light-blue {
                                background-color: #0f2438;
                            }

                            .bg-light-purple {
                                background-color: #2d1b4e;
                            }

                            .text-dark-purple {
                                color: #d8b4fe;
                            }
                        }
                    </style>
                </head>

                <body onload="initializePayrollEmployeeDetails(<?= $id ?>)">
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
                                                    <div class="col-12 col-md-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg" data-i18n=""> <?= $employee['title'] . '. ' . $employee['user_fname'] . ' ' . $employee['user_lname'] ?></span>
                                                            <span class="text-14 sub-text lh-base mt-1" data-i18n="users-desc">Effortlessly manage payroll, calculate deductions accurately, and maintain employee records</span>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if ($department == 'hr') {
                                                        if ($employee['employee_payroll_status'] == "DRAFT") {
                                                    ?>
                                                            <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button type="button" class="custom-btn w-100" onclick="updatePayrollEmployeeDetails(this, <?= $id ?>)">
                                                                    Save Changes
                                                                </button>
                                                            </div>
                                                    <?php
                                                        }
                                                    }
                                                    ?>

                                                </div>
                                            </div>

                                            <div class="col-12 p-4 component-theme">
                                                <div class="row gx-4 gy-3">

                                                    <div class="col-12">
                                                        <span class="sub-topic">Salary Snapshot</span>
                                                        <p class="text-14 sub-text mb-0 mt-1">
                                                            Review and update this employee's monthly payroll calculation with intelligent deduction base handling.
                                                        </p>
                                                    </div>

                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                        <label class="text-14 main-text required">Basic Salary</label>
                                                        <input type="number"
                                                            id="basic-salary"
                                                            class="form-control custom-input mt-2"
                                                            value="<?= $employee['basic_salary'] ?>"
                                                            min="0"
                                                            step="0.01"
                                                            oninput="calculatePayrollPreview()">
                                                        <span id="basic-salary-feedback" class="text-dark-red text-12"></span>
                                                    </div>

                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                        <label class="text-14 main-text not-required">Employee EPF</label>
                                                        <input type="number"
                                                            id="employee-epf"
                                                            class="form-control custom-input mt-2"
                                                            value="<?= $employee['employee_epf'] ?>"
                                                            readonly>
                                                    </div>

                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                        <label class="text-14 main-text not-required">Employer EPF</label>
                                                        <input type="number"
                                                            id="employer-epf"
                                                            class="form-control custom-input mt-2"
                                                            value="<?= $employee['employer_epf'] ?>"
                                                            readonly>
                                                    </div>

                                                    <div class="col-12 col-md-3 d-flex flex-column">
                                                        <label class="text-14 main-text not-required">ETF</label>
                                                        <input type="number"
                                                            id="etf"
                                                            class="form-control custom-input mt-2"
                                                            value="<?= $employee['etf'] ?>"
                                                            readonly>
                                                    </div>

                                                    <!-- Deduction Base Calculation Details -->
                                                    <div class="col-12 mt-4">
                                                        <div class="row g-3">
                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3 bg-light-green">
                                                                    <span class="text-12 text-dark-green fw-semibold">Gross Salary</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-gross">
                                                                        <?= number_format($employee['gross_salary'], 2) ?>
                                                                    </h5>
                                                                    <span class="text-11 sub-text mt-1">Base (Basic + Earnings)</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3 bg-light-orange">
                                                                    <span class="text-12 text-dark-orange fw-semibold">Gross-Based Deductions</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-gross-deductions">
                                                                        0.00
                                                                    </h5>
                                                                    <span class="text-11 sub-text mt-1">Calculated on Gross</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3 bg-light-blue">
                                                                    <span class="text-12 text-theme fw-semibold">Net-Based Deductions</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-net-deductions">
                                                                        0.00
                                                                    </h5>
                                                                    <span class="text-11 sub-text mt-1">Calculated on Net</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3 bg-light-red">
                                                                    <span class="text-12 text-dark-red fw-semibold">Total Deductions</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-deductions">
                                                                        <?= number_format($employee['total_deductions'], 2) ?>
                                                                    </h5>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3 bg-light-purple">
                                                                    <span class="text-12 text-dark-purple fw-semibold">Net Salary</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-net">
                                                                        <?= number_format($employee['net_salary'], 2) ?>
                                                                    </h5>
                                                                    <span class="text-11 sub-text mt-1">Final Amount</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3">
                                                                    <span class="text-12 sub-text fw-semibold">Tax Amount</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-tax">
                                                                        0.00
                                                                    </h5>
                                                                    <span class="text-11 sub-text mt-1">Calculated</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3">
                                                                    <span class="text-12 sub-text fw-semibold">EPF Amount</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0" id="preview-epf">
                                                                        0.00
                                                                    </h5>
                                                                    <span class="text-11 sub-text mt-1">Employee</span>
                                                                </div>
                                                            </div>

                                                            <div class="col-12 col-md-3">
                                                                <div class="bl-solid p-3">
                                                                    <span class="text-12 sub-text fw-semibold">Status</span>
                                                                    <h5 class="main-text fw-bold mt-2 mb-0">
                                                                        <?= $employee['employee_payroll_status'] ?>
                                                                    </h5>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <span class="sub-topic">Monthly Components</span>
                                                            <p class="text-14 sub-text mb-0 mt-1">
                                                                Manage earning and deduction components with proper salary base calculations.
                                                            </p>
                                                        </div>
                                                        <?php
                                                        if ($department == 'hr' && $employee['employee_payroll_status'] == "DRAFT") {
                                                        ?>
                                                            <button type="button"
                                                                class="custom-btn px-4"
                                                                onclick="addPayrollEmployeeComponent()">
                                                                Add Component
                                                            </button>
                                                        <?php
                                                        }
                                                        ?>

                                                    </div>

                                                    <div class="col-12 mt-3">
                                                        <div class="accordion" id="payrollEmployeeComponentAccordion">

                                                            <?php
                                                            $componentIndex = 1;

                                                            if ($components->num_rows > 0) {
                                                                while ($component = $components->fetch_assoc()) {
                                                            ?>
                                                                    <div class="accordion-item br-0 payroll-employee-component-card"
                                                                        data-component-index="<?= $componentIndex ?>"
                                                                        data-component-id="<?= $component['payroll_run_employee_component_id'] ?>"
                                                                        data-component-type="<?= $component['component_type'] ?>"
                                                                        data-deduction-base="<?= $component['deduction_calculation_base'] ?? 'GROSS' ?>">

                                                                        <h2 class="accordion-header payroll-component-header"
                                                                            id="payroll-employee-component-heading-<?= $componentIndex ?>">

                                                                            <button class="accordion-button br-0 payroll-component-toggle <?= $componentIndex != 1 ? 'collapsed' : '' ?>"
                                                                                type="button"
                                                                                data-bs-toggle="collapse"
                                                                                data-bs-target="#payroll-employee-component-collapse-<?= $componentIndex ?>"
                                                                                aria-expanded="<?= $componentIndex == 1 ? 'true' : 'false' ?>"
                                                                                aria-controls="payroll-employee-component-collapse-<?= $componentIndex ?>">

                                                                                <div class="component-header-content">
                                                                                    <span class="text-14 fw-semibold main-text component-card-title">
                                                                                        <?= htmlspecialchars($component['payroll_component']) ?>
                                                                                    </span>

                                                                                    <span class="text-12 sub-text component-card-summary">
                                                                                        <?= $component['component_type'] ?> • <?= number_format($component['amount'], 2) ?> • Base: <?= $component['deduction_calculation_base'] ?? 'GROSS' ?>
                                                                                    </span>
                                                                                </div>
                                                                            </button>

                                                                        </h2>

                                                                        <div id="payroll-employee-component-collapse-<?= $componentIndex ?>"
                                                                            class="accordion-collapse collapse <?= $componentIndex == 1 ? 'show' : '' ?>"
                                                                            aria-labelledby="payroll-employee-component-heading-<?= $componentIndex ?>"
                                                                            data-bs-parent="#payrollEmployeeComponentAccordion">

                                                                            <div class="accordion-body component-theme">
                                                                                <div class="row gx-3 gy-3">

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text required">Component</label>

                                                                                        <select class="form-select custom-select mt-2 component-name-select" onchange="updatePayrollEmployeeComponentSummary(this); calculatePayrollPreview();">
                                                                                            <option value="">- Select -</option>
                                                                                            <?php
                                                                                            $profile_components = Database::search("SELECT * FROM `payroll_components`");
                                                                                            while ($comp = $profile_components->fetch_assoc()) {
                                                                                            ?>
                                                                                                <option value="<?= $comp['payroll_component_id'] ?>"
                                                                                                    data-component-name="<?= $comp['payroll_component'] ?>"
                                                                                                    <?= $component['payroll_components_payroll_component_id'] == $comp['payroll_component_id'] ? 'selected' : '' ?>><?= $comp['payroll_component'] ?></option>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </select>

                                                                                        <span class="component-name-feedback text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text required">Component Type</label>
                                                                                        <select class="form-select custom-select mt-2 component-type"
                                                                                            onchange="updatePayrollEmployeeComponentSummary(this); calculatePayrollPreview();">
                                                                                            <option value="0">- Select -</option>
                                                                                            <option value="EARNING" <?= $component['component_type'] == 'EARNING' ? 'selected' : '' ?>>Earning</option>
                                                                                            <option value="DEDUCTION" <?= $component['component_type'] == 'DEDUCTION' ? 'selected' : '' ?>>Deduction</option>
                                                                                        </select>
                                                                                        <span class="component-type-feedback text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                        <input type="number"
                                                                                            class="form-control custom-input mt-2 component-amount"
                                                                                            value="<?= $component['amount'] ?>"
                                                                                            min="0"
                                                                                            step="0.01"
                                                                                            oninput="updatePayrollEmployeeComponentSummary(this); calculatePayrollPreview();">
                                                                                        <span class="component-amount-feedback text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text">EPF Applicable</label>
                                                                                        <select class="form-select custom-select mt-2 component-epf"
                                                                                            onchange="calculatePayrollPreview()">
                                                                                            <option value="0" <?= $component['is_epf_applicable'] == 0 ? 'selected' : '' ?>>No</option>
                                                                                            <option value="1" <?= $component['is_epf_applicable'] == 1 ? 'selected' : '' ?>>Yes</option>
                                                                                        </select>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text">Taxable</label>
                                                                                        <select class="form-select custom-select mt-2 component-taxable" onchange="calculatePayrollPreview()">
                                                                                            <option value="0" <?= $component['is_taxable'] == 0 ? 'selected' : '' ?>>No</option>
                                                                                            <option value="1" <?= $component['is_taxable'] == 1 ? 'selected' : '' ?>>Yes</option>
                                                                                        </select>
                                                                                    </div>

                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="text-14 main-text">Deduction Base <span class="text-11 sub-text">(Deductions Only)</span></label>
                                                                                        <select class="form-select custom-select mt-2 component-deduction-base" onchange="calculatePayrollPreview()">
                                                                                            <option value="GROSS" <?= ($component['deduction_calculation_base'] ?? 'GROSS') == 'GROSS' ? 'selected' : '' ?>>Gross Salary</option>
                                                                                            <option value="NET" <?= ($component['deduction_calculation_base'] ?? 'GROSS') == 'NET' ? 'selected' : '' ?>>Net Salary</option>
                                                                                        </select>
                                                                                        <span class="text-11 sub-text mt-1 mb-0">Applies to deductions only</span>
                                                                                    </div>

                                                                                    <div class="col-12 d-flex flex-column">
                                                                                        <label class="text-14 main-text">Note</label>
                                                                                        <textarea
                                                                                            class="form-control custom-input mt-2 component-note"
                                                                                            rows="2"
                                                                                            placeholder="Add any notes about this component"><?= htmlspecialchars($component['note'] ?? '') ?></textarea>
                                                                                    </div>

                                                                                    <?php
                                                                                    if ($department == 'hr' && $employee['employee_payroll_status'] == "DRAFT") {
                                                                                    ?>
                                                                                        <div class="col-12 mt-3 d-flex gap-2">
                                                                                            <button type="button"
                                                                                                class="custom-btn custom-btn-danger px-4"
                                                                                                onclick="deletePayrollEmployeeComponent(this)">
                                                                                                Delete Component
                                                                                            </button>
                                                                                        </div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php
                                                                    $componentIndex++;
                                                                }
                                                            }
                                                            ?>

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

                    <script>
                        const PAYROLL_COMPONENTS = <?= json_encode($payroll_components_json) ?>;
                        const EMPLOYEE_ID = <?= $id ?>;
                        const DEPARTMENT = '<?= $department ?>';
                    </script>
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