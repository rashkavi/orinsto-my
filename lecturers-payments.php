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

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('users', 5) || UserPermissions::hasPermission('users', 6))) {
            header("Location: ./dashboard");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Lecturers Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

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
                                    include "./includes/components/skeleton-courses.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Lecturers Payments</span>
                                                        <span class="text-14 sub-text lh-base mt-1">Manage student payment records, fees, and transaction history efficiently.</span>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Payments History</span>
                                                        <span class="text-14 main-text">Manage lecturers payments with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 15)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-payments-history" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="solar:bill-list-broken" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Payments History</span>
                                                        <span class="text-14 main-text">Manage lecturers payments with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-payments-history" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> -->

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="mingcute:schedule-line" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Scheduled Lecturers Payments</span>
                                                        <span class="text-14 main-text">Schedule and manage lecturer payments with clarity and control.</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-scheduled-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <!-- <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Payouts Request</span>
                                                        <span class="text-14 main-text">Manage lecturers payments with ease and flexibility</span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 15)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-payouts-request" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div> -->

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Lecture Payments</span>
                                                        <?php
                                                        $where = " tt.time_table_status_time_table_status_id = 1
                                                                               AND tt.date < CURDATE()
                                                                               AND l.`lecture_payment _type_lecture_payment _type_id` != 4
                                                                               AND lpl.lecturer_payments_has_lecture_id IS NULL";

                                                        // NOTE: lecturer_payments / earning_status join below mirrors the
                                                        // pattern used elsewhere in the system. Verify these table/column
                                                        // names match your actual schema for lecturer_payment_lectures'
                                                        // parent payment record and adjust if they differ.
                                                        $query = "
                                                                        SELECT tt.lecturers_lecturers_id, l.lecturer_contracts_lecturer_contract_id,
                                                                               l.lecture_rate, l.minimum_payable_hours,
                                                                               l.`lecture_payment _type_lecture_payment _type_id` AS payment_type_id,
                                                                               lpt.`lecture_payment _type` AS payment_type_name,
                                                                               u.title_title_id, u.user_fname, u.user_lname,
                                                                               s.subject_id, s.subject,
                                                                               tt.date, tt.start_time, tt.end_time, tt.duration,
                                                                               tt.time_table_id,
                                                                               GROUP_CONCAT(tt.time_table_id ORDER BY tt.time_table_id) AS time_table_ids,
                                                                               COUNT(*) AS batch_count,
                                                                               MAX(lp.earning_status_earning_status_id) AS earning_status_id,
                                                                               MAX(es.earning_status) AS earning_status_name,
                                                                               MAX(lp.amount) AS confirmed_amount
                                                                        FROM time_table tt
                                                                        INNER JOIN lecturers l ON l.lecturers_id = tt.lecturers_lecturers_id
                                                                        INNER JOIN users u ON u.user_id = l.users_user_id
                                                                        INNER JOIN subjects s ON s.subject_id = tt.subjects_subject_id
                                                                        INNER JOIN `lecture_payment _type` lpt ON lpt.`lecture_payment _type_id` = l.`lecture_payment _type_lecture_payment _type_id`
                                                                        LEFT JOIN lecturer_payments_has_lectures lpl ON lpl.time_table_time_table_id = tt.time_table_id
                                                                        LEFT JOIN lecturer_payments lp ON lp.lecturer_payments_id = lpl.lecturer_payments_lecturer_payments_id
                                                                        LEFT JOIN earning_status es ON es.earning_status_id = lp.earning_status_earning_status_id
                                                                        WHERE $where
                                                                        GROUP BY tt.lecturers_lecturers_id, tt.date, tt.start_time, tt.end_time
                                                                        ORDER BY tt.date DESC, tt.start_time DESC
                                                                    ";
                                                        $pendingApprovallecturePayemnts = Database::search($query);
                                                        ?>
                                                        <span class="text-14 main-text">Pending approval lecturers lecture payments - <?php echo $pendingApprovallecturePayemnts->num_rows; ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 15)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-lecture-payments.php" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Assignment Payments</span>
                                                        <?php
                                                        $pendingApprovalAssignemntPayemnts = Database::search("SELECT COUNT(*) as 'count' FROM `assignment_payments` WHERE `earning_status_earning_status_id`='1'")->fetch_assoc();
                                                        ?>
                                                        <span class="text-14 main-text">Pending approval assignments payments - <?php echo $pendingApprovalAssignemntPayemnts['count'] ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 15)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-assignments-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
                                                        }
                                                        ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 px-4 py-3 component-theme mb-10">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 col-md-10 d-flex align-items-center p-0 m-0 g-0 align-content-center">
                                                    <div class="p-4 bg-light-theme d-flex align-items-center justify-content-center"> <iconify-icon icon="fluent-emoji-high-contrast:teacher" width="32" height="32" class="dark-theme"></iconify-icon></div>
                                                    <div class="d-flex flex-column px-0 m-0 g-0 justify-content-center px-3">
                                                        <span class="text-16 text-dark-color fw-bold lh-lg">Exams Payments</span>
                                                        <?php
                                                        $pendingApprovalExamsPayemnts = Database::search("SELECT COUNT(*) as 'count' FROM `exam_payments` WHERE `earning_status_earning_status_id`='1'")->fetch_assoc();
                                                        ?>
                                                        <span class="text-14 main-text">Pending approval exams payments - <?php echo $pendingApprovalExamsPayemnts['count'] ?></span>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-2 p-0 mt-3 m-md-0 m-lg-0">
                                                    <div class="row gx-3 h-100 align-content-center justify-content-end">
                                                        <?php

                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::hasPermission('payments', 1) || UserPermissions::hasPermission('payments', 15)) {
                                                        ?>
                                                            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <a href="lecturers-payments" class="text-decoration-none cursor-pointer col-12 align-content-center">
                                                                    <button class="custom-btn w-100">View</button>
                                                                </a>
                                                            </div>
                                                        <?php
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

                <?php
                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 2)) {
                ?>
                    <!-- Payment Add Model => Start -->
                    <div class="modal fade br-0" id="addPayment" aria-hidden="true" aria-labelledby="addPaymentLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Add Payment Category</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-0 component-theme">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 d-flex flex-column">
                                                <label for="" class="text-14 main-text required">Payment Category Name</label>
                                                <input type="text" class="form-control custom-input mt-2" id="new-category-name">
                                                <span class="text-danger" id="new-category-name-feedback"></span>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end">
                                                <button class="custom-btn col-12 col-lg-4" onclick="addPaymentCategory();">Add New</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Payment Add Model => End -->
                <?php
                }

                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('payments', 10)) {
                ?>
                    <!-- Payment Edit Modal => Start -->
                    <div class="modal fade br-0" id="editPayment" aria-hidden="true" aria-labelledby="editPaymentLabel" tabindex="-1">
                        <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                            <div class="modal-content p-4">
                                <div class="modal-header p-0 border-0">
                                    <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Edit Payment Category</h1>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <div class="col-12 p-0 component-theme">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 d-flex flex-column">
                                                <label for="" class="text-14 main-text required">Payment Category Name</label>
                                                <input type="text" class="form-control custom-input mt-2" id="edit-category-name">
                                                <span class="text-danger" id="edit-category-name-feedback"></span>
                                            </div>
                                            <div class="col-12 d-flex justify-content-end">
                                                <button class="custom-btn col-12 col-lg-4" id="save-edit-category-btn">Save Changes</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Payment Edit Modal => End -->
                <?php
                }
                ?>

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