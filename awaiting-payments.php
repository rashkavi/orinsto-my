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

        $haveAnyPermission = false;
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
            header("Location: ./dashboard");
        } else {

            $query = "SELECT
    `cp`.`course_payments_id`,
    `ps`.`payment_status`,
    `ps`.`payment_status_id`,
    `cp`.`due_date`,
    `cp`.`amount`,
    `cp`.`discount`,
    `cp`.`discount_type_discount_type_id`,
    `shi`.`student_id`,
    `ld`.`first_name`,
    `ld`.`last_name`,
    `t`.`title`,
    `s`.`profile_img`,
    `c`.`code`,
    `c`.`decimal_places`,
    `pt`.`payment_type`,
    IFNULL(pp.panelty_amount, 0)              AS panelty_amount,
    IFNULL(cpp.paid_amount, 0)                AS paid_amount,
    IFNULL(cpp.total_discount, 0)             AS total_discount,
    IFNULL(cpp.total_sub_panelty_amount, 0)   AS total_sub_panelty_amount,

    -- Pre-compute balance as a SELECT column so HAVING can reference it cleanly
    (
        CASE 
            WHEN `cp`.`discount_type_discount_type_id` = 3 
            THEN `cp`.`amount` - (`cp`.`amount` / 100 * `cp`.`discount`)
            ELSE `cp`.`amount` - `cp`.`discount`
        END
        - IFNULL(cpp.total_discount, 0)
        + IFNULL(pp.panelty_amount, 0)
        + IFNULL(cpp.total_sub_panelty_amount, 0)
        - IFNULL(cpp.paid_amount, 0)
    ) AS balance

    FROM `course_payments` AS `cp`
INNER JOIN `payment_status` AS `ps` 
    ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
LEFT JOIN `students_has_intakes` AS `shi`
    ON `shi`.`students_has_intakes_id` = COALESCE(
        (
            SELECT `pshi_inner`.`students_has_intakes_students_has_intakes_id`
            FROM `payment_plan_has_students_has_intakes` `pshi_inner`
            WHERE `pshi_inner`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`
            LIMIT 1
        ),
        `cp`.`students_has_intakes_students_has_intakes_id`
    )
LEFT JOIN `payment_plan_has_students_has_intakes` AS `pshi` 
    ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
LEFT JOIN `payment_category` AS `pc`
    ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
LEFT JOIN `currencies` AS `c`
    ON `pc`.`currencies_currency_id` = `c`.`currency_id`
INNER JOIN `payment_type` AS `pt`
    ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`
INNER JOIN `students` AS `s` 
    ON `shi`.`students_student_id` = `s`.`student_id`
INNER JOIN `login_details` AS `ld`
    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
INNER JOIN `title` AS `t` 
    ON `s`.`title_title_id` = `t`.`title_id`
LEFT JOIN (
    SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
    FROM panelty_payments
    GROUP BY course_payments_course_payments_id
) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
LEFT JOIN (
    SELECT
        course_payments_course_payments_id,
        SUM(paid_amount)      AS paid_amount,
        SUM(discount_amount)  AS total_discount,
        SUM(panelty_amount)   AS total_sub_panelty_amount
    FROM course_payment_has_payments
    WHERE payment_status_payment_status_id IN (2,3)
    GROUP BY course_payments_course_payments_id
) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
WHERE `cp`.`payment_status_payment_status_id` = 1
";

            $query .= " GROUP BY `cp`.`course_payments_id`";
            $query .= " HAVING balance > 0";
            $query .= " ORDER BY `cp`.`due_date` ASC";

            $results = Database::search($query);
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Payments - Admin Dashboard | <?= Project::getBaseName() ?> </title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <script src="https://unpkg.com/html5-qrcode"></script>
                <script src="./assets/js/translations.js?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("payments");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("payments");
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
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-12 col-lg-6">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Awaiting Payments</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments" data-i18n="payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Awaiting Payments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-12 component-theme">
                                            <div class="row p-4 m-0 g-0 align-content-center">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">

                                                                <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows; ?> Awaiting Payments</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleAwaitingPaymentPagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="col-12 collapse collapse-horizontal" id="studentsFilter">
                                                            <div class="row justify-content-end">
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <select name="" id="faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                                        <option value="0" selected>- Select Faculty -</option>
                                                                        <?php

                                                                        $faculties = Database::search("SELECT * FROM `faculties`");
                                                                        while ($data = $faculties->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="faculty-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <select name="" id="programme-selector" disabled class="form-select custom-select mt-2" onchange="handleAwaitingPaymentPagination('filter'),loadIntakes();">
                                                                        <option value="0" selected>- Select Programme -</option>
                                                                    </select>
                                                                    <span id="programme-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                    <select name="" id="intake-selector" disabled class="form-select custom-select mt-2" onchange="handleAwaitingPaymentPagination('filter'),loadLevels();">
                                                                        <option value="0" selected>- Select Intake -</option>
                                                                    </select>
                                                                    <span id="intake-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="payment-category-selector" onchange="handleAwaitingPaymentPagination('filter')">
                                                                        <option selected value="0">- Payment Category -</option>
                                                                        <?php
                                                                        $paymentCategories = Database::search("SELECT * FROM `payment_category`");
                                                                        while ($paymentCategory = $paymentCategories->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentCategory['payment_category_id'] ?>"><?= $paymentCategory['payment_category'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="payment-plan-selector" onchange="handleAwaitingPaymentPagination('filter'),loadPaymentTypes()">
                                                                        <option selected value="0">- Payment Plans -</option>
                                                                        <?php
                                                                        $paymentPlans = Database::search("SELECT * FROM `payment_plan`");
                                                                        while ($paymentPlan = $paymentPlans->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymentPlan['payment_plan_id'] ?>"><?= $paymentPlan['payment_plan'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                                <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                    <select class="form-select custom-select" aria-label="Default select example" id="payment-type-selector" onchange="handleAwaitingPaymentPagination('filter')">
                                                                        <option selected value="0">- Payment Type -</option>
                                                                        <?php
                                                                        $paymentType = Database::search("SELECT * FROM `payment_type`");
                                                                        while ($paymenttype = $paymentType->fetch_assoc()) {
                                                                        ?>
                                                                            <option value="<?= $paymenttype['payment_type_id'] ?>"><?= $paymenttype['payment_type'] ?></option>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 d-none d-lg-block"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleAwaitingPaymentPagination('filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="payments-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="payments-table">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleAwaitingPaymentPagination('sortByName')">
                                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </th>

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleAwaitingPaymentPagination('sortById')">
                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                            <span class="text-14 main-text fw-medium me-2">Registration Number</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>

                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">
                                                                        <span class="text-14 main-text fw-medium me-2">Payment Type</span>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleAwaitingPaymentPagination('sortByDate')">
                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                            <span class="text-14 main-text fw-medium me-2">Due Date</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center" onclick="handleAwaitingPaymentPagination('sortByAmount')">
                                                                        <div class="d-flex justify-content-between align-items-center cursor-pointer">
                                                                            <span class="text-14 main-text fw-medium me-2">Amount</span>
                                                                            <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Paid Amount</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Balance Amount</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>


                                                                    <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>

                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php


                                                                $pageno = 1;
                                                                $results_count = $results->num_rows;
                                                                $result_per_page = 10;

                                                                $number_of_pages = ($results_count > $result_per_page) ? ceil($results_count / $result_per_page) : 1;
                                                                $page_results = ($pageno - 1) * $result_per_page;

                                                                $users = Database::search($query . " LIMIT " . $result_per_page . " OFFSET " . $page_results);

                                                                if ($results_count > 0) {
                                                                    while ($data = $users->fetch_assoc()) {
                                                                        $discountAmount = $data["discount"];
                                                                        if ($data["discount_type_discount_type_id"] == 3) {
                                                                            $discountAmount = $data["amount"] / 100 * $data["discount"];
                                                                        }
                                                                        $payableAmount = $data["amount"] - $discountAmount - $data["total_discount"] + $data["panelty_amount"] + $data["total_sub_panelty_amount"];
                                                                        $balance = $payableAmount - $data["paid_amount"];


                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check">
                                                                                    <?php
                                                                                    if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                                    ?>
                                                                                        <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                    <?php
                                                                                    } else {
                                                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                                    ?>
                                                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                    <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                                </div>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["payment_type"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["due_date"] != '' ? date("Y-m-d", strtotime($data["due_date"])) : 'N/A'; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"> <?= $data['code'] . " " . number_format($payableAmount, 00); ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($data["paid_amount"], 00) ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data['code'] . " " . number_format($balance, 00); ?></span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $status_classes = [
                                                                                    "0"     => "table-btn-purple",
                                                                                    "1"  => "table-btn-yellow",

                                                                                    "9"  => "table-btn-success",
                                                                                ];
                                                                                $sbutton_class = $status_classes[$data["payment_status_id"]];
                                                                                ?>
                                                                                <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["payment_status"]; ?></button>
                                                                            </td>

                                                                            <td class="py-3 align-middle">
                                                                                <div class="dropdown">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                        <li>
                                                                                            <a class="dropdown-item text-14 main-text d-flex align-items-center mb-1"
                                                                                                href="add-receipt-to-student-payment?payment-id=<?php echo $data['course_payments_id']; ?>&department=finance">
                                                                                                <iconify-icon icon="bi:receipt" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Add Reciept
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
                                                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container4">
                                                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                <div class="row p-4 align-items-center">
                                                                    <!-- Previous Button (col-1) -->
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleAwaitingPaymentPagination('previousPage')">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                    </button>
                                                                    <!-- Page Numbers (col-10, centered) -->
                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                        <ul class="pagination-list d-flex mb-0">
                                                                            <?php
                                                                            $shudderPages1 = false;
                                                                            $shudderPages2 = false;

                                                                            function renderPaymentPageButton($i, $isActive = false)
                                                                            {
                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                $action = $isActive ? '' : "onclick=\"handleAwaitingPaymentPagination('goToPage', '{$i}')\"";
                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                            }

                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                    renderPaymentPageButton($i, $pageno == $i);
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
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleAwaitingPaymentPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                        <span class="text-14 main-text fw-semibold">Next</span>
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
                                    <div class="flex-grow-1"></div>
                                    <footer>
                                        <?php include './includes/footer.php' ?>
                                    </footer>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal fade" id="qrScannerModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content bg-dark border-0 rounded-4 overflow-hidden">

                            <!-- Header -->
                            <div class="modal-header border-0 text-white">
                                <h6 class="modal-title">Scan QR Code</h6>
                                <button type="button" class="btn-close btn-close-white"
                                    data-bs-dismiss="modal"></button>
                            </div>

                            <!-- FULL SCANNER AREA -->
                            <div class="modal-body p-0 position-relative">

                                <div id="qr-reader" class="scanner-full"></div>

                                <!-- Overlay Text -->
                                <div class="scanner-overlay-text">
                                    Align QR code inside the frame
                                </div>

                            </div>

                        </div>
                    </div>
                </div>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}

?>