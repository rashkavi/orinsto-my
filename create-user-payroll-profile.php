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
                                                        INNER JOIN `status` ON `users`.`status_status_id`=`status`.`status_id`
                                                         WHERE `users`.`user_id`='" . $id . "'
                                                         AND `user_roles`.`user_role_id` != '20'");
                if ($user->num_rows == 0) {
                    header("Location: ./users");
                } else {
                    $udata = $user->fetch_assoc();

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
                        <title>Create User Payroll Profile - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/custom-accordian.css?= Project::getVersion() ?>">
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
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Payroll Profile</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Create Payroll Profile</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button type="button" class="custom-btn w-100" onclick="createPayrollProfile(this,<?= $id ?>)">
                                                                Save Payroll Profile
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row gx-4 gy-3">

                                                        <div class="col-12">
                                                            <span class="sub-topic">Payroll Profile Setup</span>
                                                            <p class="text-14 sub-text mb-0 mt-1">
                                                                Configure employee salary, statutory contributions, recurring allowances and deductions.
                                                            </p>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text required">Employment Type</label>
                                                            <select id="employment-type" class="form-select custom-select mt-2">
                                                                <option value="EMPLOYEE">Employee</option>
                                                                <option value="INTERN">Intern</option>
                                                                <option value="CONTRACT">Contract</option>
                                                            </select>
                                                            <span id="employment-type-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">Employee (EPF/ ETF) number</label>
                                                            <input type="text" id="epf-no" class="form-control custom-input mt-2" min="0">
                                                            <span id="epf-no-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text required">Basic Salary</label>
                                                            <input type="number" id="basic-salary" class="form-control custom-input mt-2" min="0">
                                                            <span id="basic-salary-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 mt-5">
                                                            <span class="sub-topic">Statutory Contribution Setup</span>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">EPF Employee %</label>
                                                            <input type="number" id="epf-employee-rate" class="form-control custom-input mt-2" value="8">
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">EPF Employer %</label>
                                                            <input type="number" id="epf-employer-rate" class="form-control custom-input mt-2" value="12">
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">ETF %</label>
                                                            <input type="number" id="etf-rate" class="form-control custom-input mt-2" value="3">
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">APIT Applicable</label>
                                                            <select id="apit-applicable" class="form-select custom-select mt-2">
                                                                <option value="0">No</option>
                                                                <option value="1">Yes</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-12 mt-5">
                                                            <span class="sub-topic">Bank Details</span>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">Payment Method</label>
                                                            <select id="payment-method" class="form-select custom-select mt-2">
                                                                <option value="BANK">Bank</option>
                                                                <option value="CASH">Cash</option>
                                                                <option value="CHEQUE">Cheque</option>
                                                            </select>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">Bank Name</label>
                                                            <input type="text" id="bank-name" class="form-control custom-input mt-2">
                                                            <span id="bank-name-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">Account Number</label>
                                                            <input type="text" id="bank-account-no" class="form-control custom-input mt-2">
                                                            <span id="bank-account-no-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text">Bank Branch</label>
                                                            <input type="text" id="bank-branch" class="form-control custom-input mt-2">
                                                            <span id="bank-branch-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <!-- Salary pay date: used by Payments → Staff Payments to order salaries by pay date
                                                             and highlight the ones due today -->
                                                        <div class="col-12 mt-5">
                                                            <span class="sub-topic">Salary Payment Date</span>
                                                            <p class="text-14 sub-text mb-0 mt-1">
                                                                The day this employee's salary is paid each month. If the day doesn't exist in a month (e.g. 31 in September), the last day of that month is used.
                                                            </p>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="salary-pay-day">Pay Day of Month</label>
                                                            <select id="salary-pay-day" class="form-select custom-select mt-2">
                                                                <option value="">- Select -</option>
                                                                <?php for ($d = 1; $d <= 31; $d++) { ?>
                                                                    <option value="<?= $d ?>"><?= $d === 31 ? '31 / Last day' : $d ?></option>
                                                                <?php } ?>
                                                            </select>
                                                            <span id="salary-pay-day-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="salary-pay-timing">Paid In</label>
                                                            <select id="salary-pay-timing" class="form-select custom-select mt-2">
                                                                <option value="SAME_MONTH">Same month (e.g. 25 Sep for September salary)</option>
                                                                <option value="NEXT_MONTH">Following month (e.g. 5 Oct for September salary)</option>
                                                            </select>
                                                            <span id="salary-pay-timing-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <span class="sub-topic">Recurring Earnings & Deductions</span>
                                                                <p class="text-14 sub-text mb-0 mt-1">
                                                                    Add monthly salary components such as allowances, deductions, advances and benefits.
                                                                </p>
                                                            </div>

                                                            <button type="button"
                                                                class="custom-btn px-4"
                                                                onclick="addPayrollComponent()">
                                                                Add Component
                                                            </button>
                                                        </div>

                                                        <div class="col-12 mt-3">
                                                            <div class="accordion" id="payrollComponentAccordion">

                                                                <div class="accordion-item br-0 payroll-component-card" data-component-index="1">

                                                                    <h2 class="accordion-header payroll-component-header" id="payroll-component-heading-1">

                                                                        <button class="accordion-button br-0 payroll-component-toggle"
                                                                            type="button"
                                                                            data-bs-toggle="collapse"
                                                                            data-bs-target="#payroll-component-collapse-1"
                                                                            aria-expanded="true"
                                                                            aria-controls="payroll-component-collapse-1">

                                                                            <div class="component-header-content">
                                                                                <span class="text-14 fw-semibold main-text component-card-title">
                                                                                    Component 1
                                                                                </span>

                                                                                <span class="text-12 sub-text component-card-summary">
                                                                                    No component configured yet
                                                                                </span>
                                                                            </div>
                                                                        </button>

                                                                        <button type="button"
                                                                            class="subject-header-delete-btn d-none remove-payroll-component-btn"
                                                                            onclick="removePayrollComponent(this)"
                                                                            title="Remove Component">

                                                                            <iconify-icon icon="solar:trash-bin-trash-bold"
                                                                                width="16"
                                                                                height="16"></iconify-icon>
                                                                        </button>

                                                                    </h2>

                                                                    <div id="payroll-component-collapse-1"
                                                                        class="accordion-collapse collapse show"
                                                                        aria-labelledby="payroll-component-heading-1"
                                                                        data-bs-parent="#payrollComponentAccordion">

                                                                        <div class="accordion-body component-theme">
                                                                            <div class="row gx-3 gy-3">

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required">Component Name</label>
                                                                                    <select class="form-select custom-select mt-2 component-name-select">
                                                                                        <option value="">- Select -</option>
                                                                                        <?php
                                                                                        $profile_components = Database::search("SELECT * FROM `payroll_components`");
                                                                                        while ($comp = $profile_components->fetch_assoc()) {
                                                                                        ?>
                                                                                            <option value="<?= $comp['payroll_component_id'] ?>"
                                                                                                data-component-name="<?= $comp['payroll_component'] ?>"><?= $comp['payroll_component'] ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <!-- Add more options as needed -->
                                                                                    </select>
                                                                                    <span class="component-name-feedback text-dark-red text-12"></span>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required">Component Type</label>
                                                                                    <select class="form-select custom-select mt-2 component-type"
                                                                                        onchange="updatePayrollComponentSummary(this)">
                                                                                        <option value="0">- Select -</option>
                                                                                        <option value="EARNING">Earning</option>
                                                                                        <option value="DEDUCTION">Deduction</option>
                                                                                    </select>
                                                                                    <span class="component-type-feedback text-dark-red text-12"></span>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required">Amount</label>
                                                                                    <input type="number"
                                                                                        class="form-control custom-input mt-2 component-amount"
                                                                                        min="0"
                                                                                        oninput="updatePayrollComponentSummary(this)">
                                                                                    <span class="component-amount-feedback text-dark-red text-12"></span>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text">EPF Applicable</label>
                                                                                    <select class="form-select custom-select mt-2 component-epf">
                                                                                        <option value="0">No</option>
                                                                                        <option value="1">Yes</option>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text">Taxable</label>
                                                                                    <select class="form-select custom-select mt-2 component-taxable">
                                                                                        <option value="0">No</option>
                                                                                        <option value="1">Yes</option>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text">Recurring</label>
                                                                                    <select class="form-select custom-select mt-2 component-recurring">
                                                                                        <option value="1">Monthly Recurring</option>
                                                                                        <option value="0">One Time</option>
                                                                                    </select>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column component-deduction-base-container" style="display: none;">
                                                                                    <label class="text-14 main-text">Calculate On</label>
                                                                                    <select class="form-select custom-select mt-2 component-deduction-base">
                                                                                        <option value="GROSS">Gross Salary</option>
                                                                                        <option value="NET">Net Salary</option>
                                                                                    </select>
                                                                                    <p class="text-12 sub-text mt-1 mb-0">Applies only to deductions</p>
                                                                                </div>
                                                                            </div>
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

                        <script>
                            const PAYROLL_COMPONENTS = <?= json_encode($payroll_components_json) ?>;
                        </script>
                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                        <script>

                        </script>
                    </body>

                    </html>
<?php
                }
            }
        }
    }
}
?>