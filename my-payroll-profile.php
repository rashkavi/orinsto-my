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
        $id = $sessionUserObject['user_id'];
        $user = Database::search("SELECT * FROM `users` 
                                                        INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id`=`user_roles`.`user_role_id`
                                                        INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                                                        INNER JOIN `status` ON `users`.`status_status_id`=`status`.`status_id` 
                                                        WHERE `users`.`user_id`='" . $id . "'
                                                        AND `users`.`user_roles_user_role_id` != '20'");
        if ($user->num_rows == 0) {
            header("Location: ./users");
        } else {
            $udata = $user->fetch_assoc();

?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>My Payroll Profile - Admin Dashboard | <?= Project::getBaseName() ?></title>

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

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "./includes/side-menu.php";
                            load_side_menu::load("my-payroll-profile");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("my-payroll-profile");
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
                                                        <span class="main-topic lh-lg">My Payroll Profile</span>
                                                        <span class="text-14 sub-text lh-base mt-1" data-i18n="">Manage salary structure, allowances, deductions, and payroll settings assigned to this employee.</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <?php
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
                                                                                    </div>
                                                </div>
                                            </div>
                                        <?php
                                        } else {
                                            $profile = $profileRs->fetch_assoc();

                                            $componentsRs = Database::search("
                                                            SELECT *
                                                            FROM payroll_profile_components
                                                            WHERE payroll_profiles_payroll_profile_id = '" . $profile['payroll_profile_id'] . "'
                                                            AND status_status_id = '1'
                                                            ORDER BY component_type ASC, payroll_profile_component_id ASC
                                                        ");

                                            $totalEarnings = 0;
                                            $totalDeductions = 0;

                                            $components = [];

                                            while ($component = $componentsRs->fetch_assoc()) {
                                                $components[] = $component;

                                                if ($component['component_type'] == 'EARNING') {
                                                    $totalEarnings += (float)$component['amount'];
                                                } else {
                                                    $totalDeductions += (float)$component['amount'];
                                                }
                                            }

                                            $basicSalary = (float)$profile['basic_salary'];
                                            $grossSalary = $basicSalary + $totalEarnings;
                                            $netEstimate = $grossSalary - $totalDeductions;

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

                                                    <div class="col-12 col-md-6 col-xl-3">
                                                        <div class="bl-solid p-3 h-100 bg-light-blue">
                                                            <span class="text-12 text-theme text-uppercase fw-semibold">Estimate Gross Salary</span>
                                                            <h5 class="main-text fw-bold mb-0 mt-2">
                                                                <?= number_format($netEstimate, 2) ?>
                                                            </h5>
                                                        </div>
                                                    </div>

                                                </div>

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

                                                <div class="row g-3 mb-4">

                                                    <div class="col-12 col-lg-6">
                                                        <div class="bl-solid p-3 h-100">
                                                            <span class="sub-topic text-16">Payment Details</span>

                                                            <div class="row mt-3 gy-2">
                                                                <div class="col-5 text-14 sub-text">Payment Method</div>
                                                                <div class="col-7 text-14 main-text fw-semibold"><?= $profile['payment_method'] ?></div>

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

                                                                <div class="col-6 text-14 sub-text">Recurring Deductions</div>
                                                                <div class="col-6 text-14 text-dark-red fw-semibold text-end">
                                                                    <?= number_format($totalDeductions, 2) ?>
                                                                </div>

                                                                <hr class="my-2">

                                                                <div class="col-6 text-16 main-text fw-bold">Estimated Net Salary</div>
                                                                <div class="col-6 text-16 main-text fw-bold text-end">
                                                                    <?= number_format($netEstimate, 2) ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>

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
                                                                </tr>
                                                            </thead>

                                                            <tbody>
                                                                <?php if (empty($components)) { ?>
                                                                    <tr>
                                                                        <td colspan="6" class="text-center sub-text text-14 py-4">
                                                                            No payroll components added.
                                                                        </td>
                                                                    </tr>
                                                                <?php } ?>

                                                                <?php foreach ($components as $component) { ?>
                                                                    <tr>
                                                                        <td class="text-14 main-text fw-semibold">
                                                                            <?= htmlspecialchars($component['component_name']) ?>
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
?>