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
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./users");
        } else {
            $id = $_GET['student-id'];
            $leadId = isset($_GET['lead-id']) ? $_GET['lead-id'] : 0;
            if ($leadId == 0) {
                $query = "SELECT
                            `faculty`, `faculty_id`, `programme`, `programme_id`,
                            `intake`, `intake_id`, `students_has_intakes`.`student_id`,
                            `first_name`, `last_name`, `title`, `students_has_intakes_id`
                        FROM `students_has_intakes`
                        INNER JOIN `students`   ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        INNER JOIN `title`      ON `students`.`title_title_id` = `title`.`title_id`
                        INNER JOIN `intakes`    ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                        INNER JOIN `faculties`  ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                        WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'";
            } else {
                $query = "SELECT
                        `faculty`, `faculty_id`, `programme`, `programme_id`,
                        `intake`, `intake_id`, `students_has_intakes`.`student_id`,
                        `first_name`, `last_name`, `title`, `students_has_intakes_id`, `leads_lead_id`
                    FROM `students_has_intakes`
                    INNER JOIN `students`   ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                    INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                    INNER JOIN `title`      ON `students`.`title_title_id` = `title`.`title_id`
                    LEFT JOIN `intakes`     ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                    LEFT JOIN `programmes`  ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                    LEFT JOIN `faculties`   ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                    WHERE `students_has_intakes`.`students_has_intakes_id` = '" . $id . "'
                    AND `students_has_intakes`.`leads_lead_id` = '" . $leadId . "'";
            }

            $studentHasIntake = Database::search($query);

            if ($studentHasIntake->num_rows == 0) {
                header("Location: ./faculties");
            } else {
                $studentHasIntakeData = $studentHasIntake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                $hasPermission = false;

                if ((SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_payments', 12, $studentHasIntakeData['intake_id']))) {
                    $hasPermission = true;
                }
                if (!empty($leadId) || SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                    $hasPermission = true;
                }

                if ($hasPermission) {
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Add Payment Plan - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

                        <style>
                            /* ── Only minimal styles not covered by style.css or Bootstrap ── */

                            /* Toggle option card */
                            .grp-toggle-card {
                                flex: 1;
                                display: flex;
                                align-items: center;
                                gap: 12px;
                                padding: 12px 16px;
                                border: 1.5px solid var(--line-color);
                                background: var(--custom-white);
                                cursor: pointer;
                                transition: border-color 0.18s, background 0.18s;
                                user-select: none;
                            }

                            .grp-toggle-card:hover {
                                border-color: var(--sub-topic);
                                background: var(--light-theme);
                            }

                            .grp-toggle-card.active {
                                border-color: var(--dark-theme);
                                background: var(--light-theme);
                            }

                            .grp-toggle-card .grp-icon {
                                width: 38px;
                                height: 38px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                background: var(--line-color);
                                flex-shrink: 0;
                                transition: background 0.18s;
                            }

                            .grp-toggle-card.active .grp-icon {
                                background: var(--dark-theme);
                            }

                            .grp-toggle-card .grp-radio {
                                margin-left: auto;
                                width: 18px;
                                height: 18px;
                                border-radius: 50%;
                                border: 2px solid var(--sub-text);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                flex-shrink: 0;
                                transition: all 0.18s;
                            }

                            .grp-toggle-card.active .grp-radio {
                                background: var(--dark-theme);
                                border-color: var(--dark-theme);
                            }

                            /* Intake plan selectable rows */
                            .iplan-row {
                                display: flex;
                                align-items: flex-start;
                                gap: 12px;
                                padding: 12px 14px;
                                border: 1.5px solid var(--line-color);
                                background: var(--custom-white);
                                cursor: pointer;
                                transition: border-color 0.15s, background 0.15s;
                                margin-bottom: 8px;
                            }

                            .iplan-row:last-child {
                                margin-bottom: 0;
                            }

                            .iplan-row:hover {
                                border-color: var(--sub-topic);
                                background: var(--light-theme);
                            }

                            .iplan-row.selected {
                                border-color: var(--dark-theme);
                                background: var(--light-theme);
                            }

                            .iplan-chk {
                                width: 18px;
                                height: 18px;
                                border: 2px solid var(--sub-text);
                                background: var(--custom-white);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                flex-shrink: 0;
                                margin-top: 2px;
                                transition: all 0.15s;
                            }

                            .iplan-row.selected .iplan-chk {
                                background: var(--dark-theme);
                                border-color: var(--dark-theme);
                            }

                            /* Section label */
                            .section-overline {
                                font-size: 11px;
                                font-weight: 700;
                                color: var(--sub-text);
                                text-transform: uppercase;
                                letter-spacing: 0.07em;
                                margin-bottom: 10px;
                            }

                            /* Save bar */
                            .iplan-save-bar {
                                display: flex;
                                align-items: center;
                                justify-content: space-between;
                                gap: 12px;
                                padding: 10px 14px;
                                background: var(--light-theme);
                                border: 1px solid var(--line-color);
                                margin-top: 1rem;
                            }
                        </style>
                    </head>

                    <body class="overflow-hidden">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">

                                <!-- Side Menu -->
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    $leadId == 0 ? load_side_menu::load("course-overview") : load_side_menu::load("inquiries");
                                    ?>
                                </div>

                                <!-- App Wrapper -->
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">

                                        <!-- Header -->
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            $leadId == 0 ? selectItem::setSelected("course-overview") : selectItem::setSelected("inquiries");
                                            ?>
                                        </div>

                                        <!-- Skeleton -->
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php";
                                            ?>
                                        </div>

                                        <!-- Real Content -->
                                        <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">

                                                <!-- ── Page Header / Breadcrumb ── -->
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12">
                                                            <span class="main-topic lh-lg">Add Payment Plan</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <?php if ($leadId == 0) { ?>
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='programmes?faculty-id=<?= $studentHasIntakeData['faculty_id'] ?>'><?= $studentHasIntakeData['faculty'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intakes?programme-id=<?= $studentHasIntakeData['programme_id'] ?>'><?= $studentHasIntakeData['programme'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='intake-details?intake-id=<?= $studentHasIntakeData['intake_id'] ?>'><?= $studentHasIntakeData['intake'] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="intake-student-payments?student-id=<?= $id ?>"><?= $studentHasIntakeData['title'] . ' ' . $studentHasIntakeData['first_name'] . ' ' . $studentHasIntakeData['last_name'] ?> - Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Add Payment Plan</a></li>
                                                                    </ol>
                                                                <?php } else { ?>
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./inquiries.php">All Leads</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./lead-view.php?lead-id=<?= $studentHasIntakeData['leads_lead_id'] ?>">Lead View</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./lead-registered-student-details?lead-id=<?= $studentHasIntakeData['leads_lead_id'] ?>">Lead Registered Student</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./lead-registered-student-payments?student-id=<?= $id ?>">Lead Registered Student Payments</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">Add Payment Plan</a></li>
                                                                    </ol>
                                                                <?php } ?>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- ── End Breadcrumb ── -->

                                                <!-- ── Main Content Card ── -->
                                                <div class="col-12 p-4 component-theme">

                                                    <!-- ══ PAYMENT GROUP TYPE TOGGLE ══ -->
                                                    <p class="sub-topic">Select payment group type</p>
                                                    <div class="d-flex gap-3 mb-4 flex-column flex-md-row">

                                                        <!-- Option A: Intake Payment Group -->
                                                        <div class="grp-toggle-card active" id="btn-intake-group" onclick="switchPaymentGroup('intake')" role="button" tabindex="0">
                                                            <div class="grp-icon">
                                                                <iconify-icon icon="mdi:bank-outline" width="20" height="20" style="color:#fff;" aria-hidden="true"></iconify-icon>
                                                            </div>
                                                            <div class="d-flex flex-column">
                                                                <span class="text-14 main-text fw-semibold" id="grp-label-intake">Intake Payment Group</span>
                                                                <span class="text-12 sub-text mt-1">Select from pre-configured intake plans</span>
                                                            </div>
                                                            <div class="grp-radio ms-auto" id="radio-intake">
                                                                <iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;" aria-hidden="true"></iconify-icon>
                                                            </div>
                                                        </div>

                                                        <!-- Option B: Custom Payment Group -->
                                                        <div class="grp-toggle-card" id="btn-custom-group" onclick="switchPaymentGroup('custom')" role="button" tabindex="0">
                                                            <div class="grp-icon">
                                                                <iconify-icon icon="mdi:tune-variant" width="20" height="20" style="color:var(--sub-text);" id="icon-custom" aria-hidden="true"></iconify-icon>
                                                            </div>
                                                            <div class="d-flex flex-column">
                                                                <span class="text-14 main-text fw-semibold">Custom Payment Group</span>
                                                                <span class="text-12 sub-text mt-1">Manually configure a payment plan</span>
                                                            </div>
                                                            <div class="grp-radio ms-auto" id="radio-custom"></div>
                                                        </div>

                                                    </div>

                                                    <hr class="my-3" style="border-color:var(--line-color);">

                                                    <div id="section-intake-group">
                                                        <p class="sub-topic">Available intake payment plans</p>

                                                        <div id="iplan-list-container">
                                                            <?php
                                                            $plans = Database::search("SELECT * FROM `intake_has_payment_plans`
                                                        INNER JOIN `payment_plan` ON `intake_has_payment_plans`.`payment_plan_payment_plan_id` = `payment_plan_id`
                                                        INNER JOIN `payment_category` ON `intake_has_payment_plans`.`payment_category_payment_category_id` = `payment_category_id` 
                                                        INNER JOIN `currencies` ON `payment_category`.`currencies_currency_id` = `currencies`.`currency_id`
                                                        WHERE `intakes_intake_id`='" . $studentHasIntakeData['intake_id'] . "' ORDER BY `date_added` ASC");

                                                            if ($plans->num_rows > 0) {
                                                            ?>
                                                                <div class="col-12 py-3 g-0 component-theme mb-10">
                                                                    <div class="row p-0 m-0 g-0 align-content-center" id="accordionParent">
                                                                        <?php
                                                                        while ($data = $plans->fetch_assoc()) {
                                                                        ?>
                                                                            <div class="col-12 bl-solid py-3 m-0 g-0 mt-3">
                                                                                <div class="row p-0 g-0 m-0">
                                                                                    <div class="col-12 position-relative">

                                                                                        <?php
                                                                                        if ($data['is_default_plan'] == 1) {
                                                                                        ?>
                                                                                            <span class="default-ribbon">Default</span>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <!-- Default Badge -->
                                                                                        <div class="row payment-plan-content d-flex pe-4 py-0 align-items-center">

                                                                                            <div class="col-auto">
                                                                                                <input type="checkbox"
                                                                                                    class="form-check-input hw-20 intake-plan-checkbox"
                                                                                                    name="selected_intake_plan"
                                                                                                    value="<?= $data['intake_has_payment_plan_id'] ?>"
                                                                                                    data-plan-id="<?= $data['intake_has_payment_plan_id'] ?>"
                                                                                                    onchange="selectIntakePaymentPlan(this)">
                                                                                            </div>

                                                                                            <div class="col"
                                                                                                data-bs-toggle="collapse"
                                                                                                role="button"
                                                                                                data-bs-target="#intake-plan-collapse-<?= $data['intake_has_payment_plan_id'] ?>">

                                                                                                <p class="text-16 dark-color fw-bold m-0">
                                                                                                    <?= $data['payment_plan_name'] ?> - <?= $data['payment_category'] ?>
                                                                                                </p>

                                                                                                <span class="text-14 sub-text mt-1">Payment Plan : </span>
                                                                                                <span class="text-14 sub-text mt-1 fw-medium">
                                                                                                    <?= $data['payment_plan'] ?> | <?= $data['code'] ?> <?= number_format($data['amount']) ?>
                                                                                                </span>
                                                                                            </div>

                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="overflow-x-auto p-0 mt-3 g-0 collapse" id="intake-plan-collapse-<?= $data['intake_has_payment_plan_id'] ?>" data-bs-target="#accordionParent" aria-expanded="false" aria-controls="accordionParent">
                                                                                        <div class="p-4 g-0">
                                                                                            <?php
                                                                                            $planHasPayments = Database::search("SELECT * FROM `intake_payments_plan_has_payments`
                                                                                        INNER JOIN `payment_type` ON `intake_payments_plan_has_payments`.`payment_type_payment_type_id` = `payment_type`.`payment_type_id`
                                                                                        WHERE `intake_has_payment_plan_id`='" . $data['intake_has_payment_plan_id'] . "'");
                                                                                            if ($planHasPayments->num_rows > 0) {
                                                                                                $planId = $data['intake_has_payment_plan_id'];
                                                                                            ?>
                                                                                                <div class="accordion" id="fees-type-intake-plan">
                                                                                                    <?php
                                                                                                    for ($x = 0; $x < $planHasPayments->num_rows; $x++) {
                                                                                                        $data = $planHasPayments->fetch_assoc();
                                                                                                        $itemId = "plan{$planId}_item{$data['intake_payments_plan_has_payment_id']}";
                                                                                                    ?>
                                                                                                        <div class="accordion-item br-0" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                                            <h2 class="accordion-header br-0" id="heading-<?= $itemId ?>">
                                                                                                                <button class="accordion-button br-0 <?= $x != 0 ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $itemId ?>" aria-expanded="<?= $x == 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= $itemId ?>">
                                                                                                                    <?= $data['payment_type'] ?>
                                                                                                                </button>
                                                                                                            </h2>
                                                                                                            <div id="collapse-<?= $itemId ?>" class="accordion-collapse collapse <?= $x == 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= $itemId ?>" data-bs-parent="#fees-type-intake-plan">
                                                                                                                <div class="accordion-body">

                                                                                                                    <div class="col-12 component-theme">
                                                                                                                        <div class="row p-0 gy-3 gx-3 align-content-center <?= $data['payment_type_payment_type_id'] == 18 ? 'mark-paid-box' : '' ?>">

                                                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                <label for="" class="text-14 main-text required">Amount</label>
                                                                                                                                <input type="number" class="form-control custom-input mt-2" id="amount" value="<?= $data['amount'] ?>">
                                                                                                                                <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                                                                            </div>
                                                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                <label class="text-14 main-text required" for="">Discount Type</label>
                                                                                                                                <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="changenstallmentDiscountType(this)">
                                                                                                                                    <?php
                                                                                                                                    $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                                                                                    while ($discountType = $discountTypes->fetch_assoc()) {
                                                                                                                                    ?>
                                                                                                                                        <option value="<?php echo $discountType['discount_type_id'] ?>" <?= $data['discount_type_discount_type_id'] == $discountType['discount_type_id'] ? 'selected' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>"><?php echo $discountType['discount_type'] ?></option>
                                                                                                                                    <?php
                                                                                                                                    }
                                                                                                                                    ?>
                                                                                                                                </select>
                                                                                                                                <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                                                                            </div>
                                                                                                                            <div class="col-12 col-lg-4 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? 'd-none' : '' ?>">
                                                                                                                                <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                                                                <input type="number" class="form-control custom-input mt-2" id="discount" min="0" value="<?= $data['discount'] ?>">
                                                                                                                                <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                                                            </div>
                                                                                                                            <div class="col-12 d-flex flex-column <?= $data['discount_type_discount_type_id'] == 1 ? 'd-none' : '' ?>">
                                                                                                                                <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                                                                <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                                                                                <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                                                                            </div>
                                                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                                                                <input type="date" class="form-control custom-input mt-2" id="due-date" min="0" value="<?= $data['due_date'] ?>">
                                                                                                                                <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                                                                            </div>
                                                                                                                            <div class="col-12 d-flex align-items-center">
                                                                                                                                <input type="checkbox" disabled class="form-check-input hw-20 m-0 p-0" id="enable-panelty" <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 ? 'checked' : '' ?> data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="enablePanelty(this)">
                                                                                                                                <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                                                            </div>



                                                                                                                            <div class="col-12 col-lg-4 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] == 1 ? 'd-none' : '' ?>" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" id="panelty-option-area">
                                                                                                                                <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                                                                <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" onchange="paneltyOptionChange(this)">
                                                                                                                                    <option selected value="0">- Select -</option>
                                                                                                                                    <?php
                                                                                                                                    $options = Database::search("SELECT * FROM `panelty_payment_option` WHERE `panelty_payment_option_id` != 1");
                                                                                                                                    while ($option = $options->fetch_assoc()) {
                                                                                                                                    ?>
                                                                                                                                        <option value="<?php echo $option['panelty_payment_option_id'] ?>" <?= $data['panelty_payment_option_panelty_payment_option_id'] == $option['panelty_payment_option_id'] ? 'selected' : '' ?>><?php echo $option['panelty_payment_option'] ?></option>
                                                                                                                                    <?php
                                                                                                                                    }
                                                                                                                                    ?>
                                                                                                                                </select>
                                                                                                                                <span id="panelty-option-feedback" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>" class="text-dark-red text-12"></span>
                                                                                                                            </div>

                                                                                                                            <div class="col-12 <?= $data['panelty_payment_option_panelty_payment_option_id'] != 1 && $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? '' : 'd-none' ?>" id="panelty-flat-area" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                                                                    <?php
                                                                                                                                    $amountType = 0;
                                                                                                                                    $amount = 0;
                                                                                                                                    $panelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` = '" . $data['intake_payments_plan_has_payment_id'] . "'");
                                                                                                                                    if ($panelties->num_rows > 0) {
                                                                                                                                        $paneltiesData = $panelties->fetch_assoc();
                                                                                                                                        $amountType = $paneltiesData['panelty_payment_type_panelty_payment_type_id'];
                                                                                                                                        $amount = $paneltiesData['amount'];
                                                                                                                                    }
                                                                                                                                    ?>
                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                        <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                                        <select class="form-select custom-select mt-2" id="flat-type">
                                                                                                                                            <option value="0">- Select -</option>
                                                                                                                                            <?php
                                                                                                                                            $types = Database::search("SELECT * FROM `panelty_payments_type`");

                                                                                                                                            while ($type = $types->fetch_assoc()) {
                                                                                                                                            ?>
                                                                                                                                                <option value="<?php echo $type['panelty_payments_type_id']; ?>" <?= $amountType == $type['panelty_payments_type_id'] ? 'selected' : '' ?>><?php echo $type['panelty_payments_type']; ?></option>
                                                                                                                                            <?php
                                                                                                                                            }
                                                                                                                                            ?>
                                                                                                                                        </select>
                                                                                                                                        <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                                                                    </div>
                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                        <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                                                                        <input type="number" class="form-control custom-input mt-2" id="flat-amount" value="<?= $amount ?>">
                                                                                                                                        <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                                                                    </div>
                                                                                                                                </div>
                                                                                                                            </div>


                                                                                                                            <div class="col-12 d-flex flex-column <?= $data['panelty_payment_option_panelty_payment_option_id'] != 4 ? 'd-none' : '' ?>" id="panelty-custom-area" data-installment-id="<?= $data['intake_payments_plan_has_payment_id'] ?>">
                                                                                                                                <div class="row p-0 m-0" id="custom-slots">

                                                                                                                                    <?php
                                                                                                                                    $panelties = Database::search("SELECT * FROM `intake_has_payments_has_penalties` WHERE `intake_payments_plan_has_payment_id` = '" . $data['intake_payments_plan_has_payment_id'] . "'");
                                                                                                                                    if ($panelties->num_rows > 0) {
                                                                                                                                        for ($y = 0; $y < $panelties->num_rows; $y++) {
                                                                                                                                            $panelty = $panelties->fetch_assoc();
                                                                                                                                    ?>
                                                                                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                                                                    <div class="d-flex justify-content-between">
                                                                                                                                                        <span class="text-14 sub-topic">Custom Date Slot - <?= $y + 1 ?></span>
                                                                                                                                                    </div>

                                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                                        <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                                                        <select class="form-select custom-select mt-2 panelty-type">
                                                                                                                                                            <option value="0">- Select -</option>
                                                                                                                                                            <option value="1" <?= $panelty['panelty_payment_type_panelty_payment_type_id'] == 1 ? 'selected' : '' ?>>Fixed</option>
                                                                                                                                                            <option value="2" <?= $panelty['panelty_payment_type_panelty_payment_type_id'] == 2 ? 'selected' : '' ?>>Percentage</option>
                                                                                                                                                        </select>
                                                                                                                                                    </div>

                                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-amount" value="<?= $panelty['amount'] ?>">
                                                                                                                                                    </div>

                                                                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                                        <label class="text-14 main-text required">Days</label>
                                                                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-days" value="<?= $panelty['days'] ?>">
                                                                                                                                                    </div>
                                                                                                                                                </div>
                                                                                                                                            </div>
                                                                                                                                        <?php
                                                                                                                                        }
                                                                                                                                    } else {
                                                                                                                                        ?>
                                                                                                                                        <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                                                            <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                                                                <div class="d-flex justify-content-between">
                                                                                                                                                    <span class="text-14 sub-topic">Custom Date Slot - 1</span>
                                                                                                                                                </div>

                                                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                                    <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                                                                    <select class="form-select custom-select mt-2 panelty-type">
                                                                                                                                                        <option value="0">- Select -</option>
                                                                                                                                                        <option value="1">Fixed</option>
                                                                                                                                                        <option value="2">Percentage</option>
                                                                                                                                                    </select>
                                                                                                                                                </div>

                                                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                                    <label class="text-14 main-text required">Amount</label>
                                                                                                                                                    <input type="number" class="form-control custom-input mt-2 panelty-amount">
                                                                                                                                                </div>

                                                                                                                                                <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                                                                    <label class="text-14 main-text required">Days</label>
                                                                                                                                                    <input type="number" class="form-control custom-input mt-2 panelty-days">
                                                                                                                                                </div>
                                                                                                                                            </div>
                                                                                                                                        </div>
                                                                                                                                    <?php
                                                                                                                                    }
                                                                                                                                    ?>
                                                                                                                                </div>

                                                                                                                            </div>


                                                                                                                            <?php
                                                                                                                            if ($data['payment_type_payment_type_id'] == 18) {
                                                                                                                            ?>
                                                                                                                                <div class="row mt-3 d-none mark-paid-fields">
                                                                                                                                    <div class="col-12 col-md-4">
                                                                                                                                        <label class="text-14 main-text required">Paid Amount</label>
                                                                                                                                        <input type="number" class="form-control custom-input mt-2 paid-amount"
                                                                                                                                            value="<?= $data['amount'] ?>">
                                                                                                                                        <span id="paid-amount-feedback" class="text-dark-red text-12"></span>
                                                                                                                                    </div>
                                                                                                                                    <div class="col-12 col-md-4">
                                                                                                                                        <label class="text-14 main-text required">Discount Amount</label>
                                                                                                                                        <input type="number" class="form-control custom-input mt-2 discount"
                                                                                                                                            value="0">
                                                                                                                                        <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                                                                    </div>

                                                                                                                                    <div class="col-12 col-md-4">
                                                                                                                                        <label class="text-14 main-text required">Paid Date</label>
                                                                                                                                        <input type="date" class="form-control custom-input mt-2 paid-date"
                                                                                                                                            value="<?= date('Y-m-d') ?>">
                                                                                                                                        <span id="paid-date-feedback" class="text-dark-red text-12"></span>
                                                                                                                                    </div>

                                                                                                                                    <div class="col-12 col-md-4">
                                                                                                                                        <label class="text-14 main-text required">Payment Method</label>
                                                                                                                                        <select class="form-select custom-select mt-2 paid-method">
                                                                                                                                            <?php
                                                                                                                                            $methods = Database::search("SELECT * FROM `payment_method`");
                                                                                                                                            while ($method = $methods->fetch_assoc()) {
                                                                                                                                            ?>
                                                                                                                                                <option value="<?= $method['payment_method_id'] ?>" <?= $method['payment_method_id'] == 1 ? 'selected' : '' ?>>
                                                                                                                                                    <?= $method['payment_method'] ?>
                                                                                                                                                </option>
                                                                                                                                            <?php } ?>
                                                                                                                                        </select>
                                                                                                                                        <span id="method-feedback" class="text-dark-red text-12"></span>
                                                                                                                                    </div>

                                                                                                                                    <div class="col-12 mt-3">
                                                                                                                                        <label class="text-14 main-text">Payment Note</label>
                                                                                                                                        <textarea class="form-control custom-input mt-2 paid-note"
                                                                                                                                            rows="3"></textarea>
                                                                                                                                    </div>
                                                                                                                                </div>
                                                                                                                            <?php
                                                                                                                            }
                                                                                                                            ?>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    <?php
                                                                                                    }
                                                                                                    ?>
                                                                                                </div>
                                                                                            <?php
                                                                                            }
                                                                                            ?>
                                                                                        </div>

                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </div>
                                                                </div>
                                                            <?php
                                                            } else {
                                                            ?>
                                                                <div class="col-12 px-4 py-3 component-theme">
                                                                    <div class="row align-items-center justify-content-center">
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
                                                            }
                                                            ?>
                                                        </div>
                                                        <?php
                                                        if ($plans->num_rows > 0) {
                                                        ?>
                                                            <div class="col-12 d-flex flex-column mt-4">
                                                                <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                        <button class="custom-btn w-100"
                                                                            onclick="addStudentToIntakePaymentPlan(this, <?= (int)$studentHasIntakeData['students_has_intakes_id'] ?>, <?= (int)$studentHasIntakeData['intake_id'] ?>);">
                                                                            Save Payment Plan
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>


                                                    </div>
                                                    <!-- ══ END SECTION A ══ -->

                                                    <!-- ══════════════════════════════════════
                                                                                    SECTION B — CUSTOM PAYMENT GROUP
                                                                                ══════════════════════════════════════ -->
                                                    <div id="section-custom-group" class="d-none">
                                                        <p class="sub-topic">Configure custom payment plan</p>
                                                        <div class="row gx-4 gy-3">

                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                <label class="text-14 main-text required">Payment Category</label>
                                                                <select id="payment-category" class="form-select custom-select mt-2">
                                                                    <option selected value="0">- Select -</option>
                                                                    <?php
                                                                    $paymentCategory = Database::search("SELECT * FROM `payment_category`");
                                                                    while ($paymentCategoryData = $paymentCategory->fetch_assoc()) { ?>
                                                                        <option value="<?= $paymentCategoryData['payment_category_id'] ?>"><?= $paymentCategoryData['payment_category'] ?></option>
                                                                    <?php } ?>
                                                                </select>
                                                                <span id="payment-category-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                <label class="text-14 main-text required">Payment Plan</label>
                                                                <select id="payment-plan" class="form-select custom-select mt-2">
                                                                    <option selected value="0">- Select -</option>
                                                                    <?php
                                                                    $paymentsPlan = Database::search("SELECT * FROM `payment_plan`");
                                                                    while ($paymentPlan = $paymentsPlan->fetch_assoc()) { ?>
                                                                        <option value="<?= $paymentPlan['payment_plan_id'] ?>"><?= $paymentPlan['payment_plan'] ?></option>
                                                                    <?php } ?>
                                                                </select>
                                                                <span id="payment-plan-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                <label class="text-14 main-text required">Course Amount</label>
                                                                <input type="number" class="form-control custom-input mt-2" id="payment-amount" min="0">
                                                                <span id="payment-amount-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                <label class="text-14 main-text required">Discount Type</label>
                                                                <select id="discount-type" class="form-select custom-select mt-2" onchange="changeDiscountType(this)">
                                                                    <option selected value="0">- Select -</option>
                                                                    <?php
                                                                    $discountTypes = Database::search("SELECT * FROM `discount_type`");
                                                                    while ($discountType = $discountTypes->fetch_assoc()) { ?>
                                                                        <option value="<?= $discountType['discount_type_id'] ?>"><?= $discountType['discount_type'] ?></option>
                                                                    <?php } ?>
                                                                </select>
                                                                <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 col-md-4 d-flex flex-column d-none" id="discount-area">
                                                                <label class="text-14 main-text required" id="discount-label">Discount</label>
                                                                <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 d-flex flex-column d-none" id="discount-note-area">
                                                                <label class="text-14 main-text">Discount Note</label>
                                                                <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 d-flex flex-column mt-4">
                                                                <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                    <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                        <button class="custom-btn w-100"
                                                                            onclick="addStudentToPaymentPlan(this, <?= (int)$studentHasIntakeData['students_has_intakes_id'] ?>, <?= $leadId == 0 ? 1 : 2 ?>);">
                                                                            Save Payment Plan
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                    <!-- ══ END SECTION B ══ -->

                                                </div>
                                                <!-- ── End Main Content Card ── -->

                                            </div>
                                            <div class="flex-grow-1"></div>
                                            <footer><?php include './includes/footer.php' ?></footer>
                                        </div>
                                        <!-- End Real Content -->

                                    </div>
                                </div>
                            </div>
                        </div>

                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                        <script>
                            /* ════════════════════════════════════════════
       PAYMENT GROUP TOGGLE  — pure vanilla JS
    ════════════════════════════════════════════ */
                            var selectedIntakePlans = [];
                            var intakePlansLoaded = false;

                            function switchPaymentGroup(type) {
                                var intakeBtn = document.getElementById('btn-intake-group');
                                var customBtn = document.getElementById('btn-custom-group');
                                var intakeSec = document.getElementById('section-intake-group');
                                var customSec = document.getElementById('section-custom-group');
                                var radioIntake = document.getElementById('radio-intake');
                                var radioCustom = document.getElementById('radio-custom');
                                var iconCustom = document.getElementById('icon-custom');

                                if (type === 'intake') {
                                    intakeBtn.classList.add('active');
                                    customBtn.classList.remove('active');
                                    intakeSec.classList.remove('d-none');
                                    customSec.classList.add('d-none');
                                    if (iconCustom) iconCustom.style.color = 'var(--sub-text)';
                                    radioIntake.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
                                    radioCustom.innerHTML = '';
                                    if (!intakePlansLoaded) loadIntakePaymentPlans();
                                } else {
                                    customBtn.classList.add('active');
                                    intakeBtn.classList.remove('active');
                                    customSec.classList.remove('d-none');
                                    intakeSec.classList.add('d-none');
                                    if (iconCustom) iconCustom.style.color = '#fff';
                                    radioCustom.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
                                    radioIntake.innerHTML = '';
                                }
                            }
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