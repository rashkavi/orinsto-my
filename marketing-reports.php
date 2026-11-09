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
        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType("leads"))) {
            header("Location: ./users");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Marketing Reports - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
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
                            load_side_menu::load("marketing-reports");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("marketing-reports");
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
                                                <div class="col-12 col-md-8 col-lg-10">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="">Marketing Reports</span>
                                                        <span class="text-14 sub-text lh-base mt-1">
                                                            Analyze marketing performance, track campaign results, and gain actionable insights
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12">
                                            <div class="row g-3">
                                                <div class="col-xl-4 col-lg-6 col-md-6">
                                                    <div class="card h-100 border-0">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex align-items-start gap-3 mb-3">
                                                                <div class="bg-primary-subtle text-primary rounded-4 p-3 d-flex">
                                                                    <iconify-icon icon="solar:user-plus-bold-duotone" width="26"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <h6 class="main-text fw-semibold mb-1">Daily Admissions Report</h6>
                                                                    <p class="sub-text text-13 mb-0">
                                                                        Select a date and download admissions, registered students, programmes and intake summary.
                                                                    </p>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-12 col-md-6 mb-3">
                                                                    <label class="form-label text-13 sub-text mb-1">
                                                                        From Date
                                                                    </label>

                                                                    <input type="date"
                                                                        class="form-control custom-input"
                                                                        id="dailyAdmissionsReportFromDate"
                                                                        value="<?php echo date('Y-m-01'); ?>">
                                                                </div>

                                                                <div class="col-12 col-md-6 mb-3">
                                                                    <label class="form-label text-13 sub-text mb-1">
                                                                        To Date
                                                                    </label>

                                                                    <input type="date"
                                                                        class="form-control custom-input"
                                                                        id="dailyAdmissionsReportToDate"
                                                                        value="<?php echo date('Y-m-d'); ?>">
                                                                </div>
                                                            </div>

                                                            <button type="button" class="custom-btn w-100" onclick="generateDailyAdmissionsReport(this)">
                                                                <iconify-icon icon="line-md:download-loop"></iconify-icon>
                                                                Download Report
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- <div class="col-xl-4 col-lg-6 col-md-6">
                                                    <div class="card h-100 border-0">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex align-items-start gap-3 mb-3">
                                                                <div class="bg-success-subtle text-success rounded-4 p-3 d-flex">
                                                                    <iconify-icon icon="solar:phone-calling-rounded-bold-duotone" width="26"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <h6 class="main-text fw-semibold mb-1">Daily Calls Report - Admissions</h6>
                                                                    <p class="sub-text text-13 mb-0">
                                                                        Daily admission calls, counsellor activity, call status and follow-ups.
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label text-13 sub-text mb-1">Report Date</label>
                                                                <input type="date"
                                                                    class="form-control custom-input"
                                                                    id="dailyCallsReportDate"
                                                                    value="<?php echo date('Y-m-d'); ?>">
                                                            </div>
                                                            <button type="button" class="custom-btn w-100">
                                                                <iconify-icon icon="line-md:download-loop"></iconify-icon>
                                                                Download Report
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-xl-4 col-lg-6 col-md-6">
                                                    <div class="card h-100 border-0">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex align-items-start gap-3 mb-3">
                                                                <div class="bg-warning-subtle text-warning rounded-4 p-3 d-flex">
                                                                    <iconify-icon icon="solar:chat-round-like-bold-duotone" width="26"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <h6 class="main-text fw-semibold mb-1">Daily Feedback Report</h6>
                                                                    <p class="sub-text text-13 mb-0">
                                                                        Today’s feedback summary with response type and counsellor notes.
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label text-13 sub-text mb-1">Report Date</label>
                                                                <input type="date"
                                                                    class="form-control custom-input"
                                                                    id="dailyFeedbackReportDate"
                                                                    value="<?php echo date('Y-m-d'); ?>">
                                                            </div>

                                                            <button type="button" class="custom-btn w-100">
                                                                <iconify-icon icon="line-md:download-loop"></iconify-icon>
                                                                Download Report
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div> -->

                                                <div class="col-xl-4 col-lg-6 col-md-6">
                                                    <div class="card h-100 border-0">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex align-items-start gap-3 mb-3">
                                                                <div class="bg-info-subtle text-info rounded-4 p-3 d-flex">
                                                                    <iconify-icon icon="solar:document-text-bold-duotone" width="26"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <h6 class="main-text fw-semibold mb-1">Daily Inquiries Summary Report</h6>
                                                                    <p class="sub-text text-13 mb-0">
                                                                        Total inquiries, lead sources, programme interest and daily performance.
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <div class="col-12 col-md-6 mb-3">
                                                                    <label class="form-label text-13 sub-text mb-1">
                                                                        From Date
                                                                    </label>

                                                                    <input type="date"
                                                                        class="form-control custom-input"
                                                                        id="dailyInquirySummaryReportFromDate"
                                                                        value="<?php echo date('Y-m-01'); ?>">
                                                                </div>

                                                                <div class="col-12 col-md-6 mb-3">
                                                                    <label class="form-label text-13 sub-text mb-1">
                                                                        To Date
                                                                    </label>

                                                                    <input type="date"
                                                                        class="form-control custom-input"
                                                                        id="dailyInquirySummaryReportToDate"
                                                                        value="<?php echo date('Y-m-d'); ?>">
                                                                </div>
                                                            </div>

                                                            <button type="button" class="custom-btn w-100" onclick="generateDailyInquirySummaryReport(this);">
                                                                <iconify-icon icon="line-md:download-loop"></iconify-icon>
                                                                Download Report
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- <div class="col-xl-4 col-lg-6 col-md-6">
                                                    <div class="card h-100 border-0">
                                                        <div class="card-body p-3">
                                                            <div class="d-flex align-items-start gap-3 mb-3">
                                                                <div class="bg-secondary-subtle text-secondary rounded-4 p-3 d-flex">
                                                                    <iconify-icon icon="solar:calendar-mark-bold-duotone" width="26"></iconify-icon>
                                                                </div>
                                                                <div>
                                                                    <h6 class="main-text fw-semibold mb-1">Weekly Feedback Report</h6>
                                                                    <p class="sub-text text-13 mb-0">
                                                                        Weekly feedback analysis, counsellor performance and follow-up results.
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label text-13 sub-text mb-1">Report Date</label>
                                                                <input type="date"
                                                                    class="form-control custom-input"
                                                                    id="dailyCallsReportDate"
                                                                    value="<?php echo date('Y-m-d'); ?>">
                                                            </div>

                                                            <button type="button" class="custom-btn w-100">
                                                                <iconify-icon icon="line-md:download-loop"></iconify-icon>
                                                                Download Report
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div> -->

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
?>