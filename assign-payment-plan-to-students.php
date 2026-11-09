<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/connection.php';
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            // header("Location: ./intakes");
        } else {
            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes` 
            INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id` 
            INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                // header("Location: ./course-overview");
            } else {

                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                $intakeData = $intake->fetch_assoc();
                $paymentPlan = Database::search("SELECT * FROM `intake_has_payment_plans` WHERE `intakes_intake_id`='" . $id . "'");
?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Add Payment Plan To Intake Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                                load_side_menu::load("course-overview");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("course-overview");
                                        ?>
                                    </div>
                                    <div class="container p-4 m-0" id="skeleton-list">
                                        <?php
                                        include './includes/components/skeleton-header.php';
                                        include './includes/components/skeleton-add-edit.php'; ?>
                                    </div>

                                    <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                        <div class="row p-4 m-0 g-0" id="accordionParent">
                                            <div class="col-12 px-4 py-3 component-theme mb-4">
                                                <div class="row p-0 m-0 g-0">
                                                    <div class="col-12 col-md-8 col-lg-10">
                                                        <div class="row">
                                                            <span class="main-topic lh-lg">Add Payments Plan To Intake Students</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties?id=<?php echo $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $id ?>"><?= $intakeData["intake"] ?></a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <?php
                                                    if ($paymentPlan->num_rows > 0) {
                                                    ?>
                                                        <div class="col-12 col-md-4 col-lg-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3 h-100 align-items-center">
                                                                <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="addStudentsToPaymentPlan(this,<?= $id ?>)">Save Payment Plan</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </div>
                                            </div>

                                            <?php

                                            if ($paymentPlan->num_rows == 0) {
                                            ?>
                                                <div class="col-12 px-4 py-3 component-theme">
                                                    <div class="row align-items-center justify-content-center">
                                                        <img src="./assets/imgs/common/setup.png" class="no-data-icon" alt="No data" />
                                                        <span class="sub-topic text-center d-block">
                                                            Payment Group Not Configured
                                                        </span>
                                                        <span class="text-center d-block mt-1">
                                                            This intake does not have any payment group assigned yet.
                                                            Please create or assign a payment group to proceed with managing student payments.
                                                        </span>
                                                        <div class="text-center">
                                                            <button class="custom-btn mt-3 px-5" onclick="window.location.href='add-intake-payment-plan?intake-id=<?= $id ?>'">Add Payment Group</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php
                                            } else {
                                            ?>
                                                <!DOCTYPE html>
                                                <html lang="en">

                                                <head>
                                                    <meta charset="UTF-8">
                                                    <title>Payment Guideline Card</title>
                                                    <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>
                                                </head>

                                                <body>

                                                    <!-- ===== INTAKE PAYMENT GUIDELINE CARD — DROP-IN COMPONENT ===== -->
                                                    <div class="payment-guide-card">

                                                        <!-- Header (always visible) -->
                                                        <div class="pg-header" onclick="togglePaymentGuide(this)" role="button" aria-expanded="false" aria-controls="pgBody" tabindex="0">
                                                            <div class="pg-header-left">
                                                                <div class="pg-icon-wrap">
                                                                    <iconify-icon icon="mdi:clipboard-list-outline" width="20" height="20" style="color:#ffffff;" aria-hidden="true"></iconify-icon>
                                                                </div>
                                                                <div class="pg-title-block">
                                                                    <p class="pg-title">Intake Payment Plan Management &mdash; Important Guidelines</p>
                                                                    <p class="pg-subtitle">Please review the following information carefully before proceeding</p>
                                                                </div>
                                                            </div>
                                                            <button class="pg-toggle-btn" tabindex="-1" aria-hidden="true">
                                                                <span class="pg-btn-text">View details</span>
                                                                <iconify-icon icon="mdi:chevron-down" width="16" height="16" class="pg-chevron"></iconify-icon>
                                                            </button>
                                                        </div>

                                                        <!-- Collapsible Body -->
                                                        <div class="pg-body" id="pgBody">
                                                            <div class="pg-divider"></div>
                                                            <div class="pg-items">

                                                                <!-- Item 01 -->
                                                                <div class="pg-item">
                                                                    <div class="pg-item-icon pg-blue">
                                                                        <iconify-icon icon="mdi:calendar-plus-outline" width="18" height="18" aria-hidden="true"></iconify-icon>
                                                                    </div>
                                                                    <div class="pg-item-content">
                                                                        <p class="pg-item-label">
                                                                            <span class="pg-step-badge">01</span>
                                                                            Intake payment plan configuration
                                                                        </p>
                                                                        <p class="pg-item-desc">
                                                                            Payment plans can be created directly for each intake, including payment amounts,
                                                                            installment structures, due dates, discounts, and penalty configurations.
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <!-- Item 02 -->
                                                                <div class="pg-item">
                                                                    <div class="pg-item-icon pg-purple">
                                                                        <iconify-icon icon="mdi:bookmark-outline" width="18" height="18" aria-hidden="true"></iconify-icon>
                                                                    </div>
                                                                    <div class="pg-item-content">
                                                                        <p class="pg-item-label">
                                                                            <span class="pg-step-badge">02</span>
                                                                            Payment plan category validation
                                                                        </p>
                                                                        <p class="pg-item-desc">
                                                                            When a plan is selected, the system identifies its payment category (e.g. Local Institute Payment)
                                                                            and loads only students not yet assigned under that category.
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <!-- Item 03 -->
                                                                <div class="pg-item">
                                                                    <div class="pg-item-icon pg-green">
                                                                        <iconify-icon icon="mdi:account-group-outline" width="18" height="18" aria-hidden="true"></iconify-icon>
                                                                    </div>
                                                                    <div class="pg-item-content">
                                                                        <p class="pg-item-label">
                                                                            <span class="pg-step-badge">03</span>
                                                                            Eligible student loading
                                                                        </p>
                                                                        <p class="pg-item-desc">
                                                                            Only eligible students are displayed after selecting a plan. Students already assigned
                                                                            to the same payment category are excluded to prevent duplicate assignments.
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <!-- Item 04 -->
                                                                <div class="pg-item">
                                                                    <div class="pg-item-icon pg-amber">
                                                                        <iconify-icon icon="mdi:cash-check" width="18" height="18" aria-hidden="true"></iconify-icon>
                                                                    </div>
                                                                    <div class="pg-item-content">
                                                                        <p class="pg-item-label">
                                                                            <span class="pg-step-badge">04</span>
                                                                            Assign payment plan to selected students
                                                                        </p>
                                                                        <p class="pg-item-desc">
                                                                            Once students are confirmed, all plan details are assigned to each student
                                                                            and individual payment schedules are generated automatically.
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <!-- Warning Note -->
                                                                <div class="pg-warn-note">
                                                                    <iconify-icon icon="mdi:alert-outline" width="18" height="18" style="color:#d97706;flex-shrink:0;margin-top:1px;" aria-hidden="true"></iconify-icon>
                                                                    <p>
                                                                        <strong>Important:</strong> Verify the selected payment plan and student list carefully
                                                                        before confirming. Once assigned, this will affect billing, reports, reminders, and cashier collections.
                                                                    </p>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- ===== END PAYMENT GUIDELINE CARD ===== -->
                                                  
                                                    <script>
                                                        function togglePaymentGuide(header) {
                                                            const body = document.getElementById('pgBody');
                                                            const chevron = header.querySelector('.pg-chevron');
                                                            const btnText = header.querySelector('.pg-btn-text');
                                                            const isOpen = body.classList.contains('open');

                                                            body.classList.toggle('open', !isOpen);
                                                            chevron.classList.toggle('rotated', !isOpen);
                                                            header.setAttribute('aria-expanded', String(!isOpen));
                                                            btnText.textContent = isOpen ? 'View details' : 'Hide details';
                                                        }

                                                        // Keyboard support
                                                        document.querySelector('.pg-header').addEventListener('keydown', function(e) {
                                                            if (e.key === 'Enter' || e.key === ' ') {
                                                                e.preventDefault();
                                                                togglePaymentGuide(this);
                                                            }
                                                        });
                                                    </script>

                                                </body>

                                                </html>
                                                <div class="col-12 p-4 component-theme mb-4">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 col-md-4 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Payment Plan</label>
                                                            <select id="payment-plan" class="form-select custom-select mt-2" onchange="handleSelectedIntakePaymentPlanStudents(<?= $id ?>)">
                                                                <option selected value="0">- Select -</option>
                                                                <?php

                                                                while ($paymentPlanData = $paymentPlan->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?= $paymentPlanData['intake_has_payment_plan_id'] ?>"><?= $paymentPlanData['payment_plan_name'] ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="payment-category-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-4 component-theme d-none" id="student-list-container">

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
                    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                </body>

                </html>
<?php


            }
        }
    }
} ?>