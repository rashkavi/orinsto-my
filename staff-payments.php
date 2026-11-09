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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!StaffPayments::canView($sessionUserRole)) {
            header("Location: ./payments");
        } else {
            $canAddReceipt = StaffPayments::canAddReceipt($sessionUserRole);
            $runs = StaffPayments::eligibleRuns();
            $profileMethods = StaffPayments::profilePaymentMethods();
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Staff Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <style>
                    /* Row action menu floats above the table instead of being clipped */
                    .sp-dropdown .dropdown-menu {
                        z-index: 1090;
                        min-width: 200px;
                    }

                    .sp-diff {
                        font-size: 12px;
                    }

                    /* "DUE" / "LATE" ribbon on the left edge of a row (like the students list) */
                    #sp-table td.sp-name-cell {
                        position: relative;
                        padding-left: 2.25rem !important;
                    }

                    .sp-ribbon {
                        position: absolute;
                        left: 0;
                        top: 0;
                        bottom: 0;
                        width: 22px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        color: #fff;
                        font-size: 10px;
                        font-weight: 700;
                        letter-spacing: .08em;
                        writing-mode: vertical-rl;
                        transform: rotate(180deg);
                    }

                    .sp-ribbon.due {
                        background: var(--dark-red, #e5484d);
                    }

                    .sp-ribbon.late {
                        background: var(--dark-orange, #f08c00);
                    }

                    #sp-table tr.sp-row-due>td {
                        background: rgba(229, 72, 77, .05);
                    }

                    .sp-bank-line {
                        font-size: 12px;
                        line-height: 1.3;
                    }
                </style>
            </head>

            <body class="overflow-hidden" onload="initializeStaffPayments();">
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
                                                <div class="col-12">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Staff Payments</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="payments">Payments</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Staff Payments</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Summary cards (follow the filters) -->
                                        <div class="container-fluid p-0 mb-4">
                                            <div class="row g-3">
                                                <?php
                                                $cards = [
                                                    ['id' => 'due_today', 'title' => 'Due Today',   'icon' => 'mdi:calendar-alert',              'meta' => 'sp-card-due_today-meta'],
                                                    ['id' => 'overdue',   'title' => 'Overdue',     'icon' => 'mdi:clock-alert-outline',         'meta' => 'sp-card-overdue-meta'],
                                                    ['id' => 'pending',   'title' => 'Pending',     'icon' => 'mdi:clock-outline',               'meta' => 'sp-card-pending-meta'],
                                                    ['id' => 'paid',      'title' => 'Paid',        'icon' => 'mdi:receipt-text-check-outline',  'meta' => 'sp-card-paid-meta'],
                                                ];
                                                foreach ($cards as $c) { ?>
                                                    <div class="col-12 col-md-6 col-lg-3">
                                                        <div class="stats-mini-card">
                                                            <div class="stats-mini-top">
                                                                <div class="stats-mini-left">
                                                                    <div class="stats-mini-icon"><iconify-icon icon="<?= $c['icon'] ?>" width="16" height="16"></iconify-icon></div>
                                                                    <span class="stats-mini-title"><?= $c['title'] ?></span>
                                                                </div>
                                                            </div>
                                                            <div class="stats-mini-body">
                                                                <div class="stats-mini-content">
                                                                    <div class="stats-mini-value" id="sp-card-<?= $c['id'] ?>">-</div>
                                                                    <div class="stats-mini-meta"><span class="stats-mini-period" id="<?= $c['meta'] ?>">&nbsp;</span></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0 align-content-center">

                                                <!-- Toolbar: count · search · filter (same layout as students.php) -->
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <span class="text-14 dark-theme fw-medium"><span id="sp-count">0</span> Paysheets</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search staff name, email, ID, account no or reference..." id="sp-search">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100 position-relative" type="button" data-bs-toggle="collapse" data-bs-target="#staffPaymentsFilter" aria-expanded="false" aria-controls="staffPaymentsFilter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>

                                                        <div class="collapse" id="staffPaymentsFilter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row gy-3 gx-3 justify-content-end align-items-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select class="form-select custom-select" id="sp-status">
                                                                                <option value="">- All Status -</option>
                                                                                <option value="DUE_TODAY">Due Today</option>
                                                                                <option value="OVERDUE">Overdue</option>
                                                                                <option value="UPCOMING">Upcoming</option>
                                                                                <option value="NOT_SET">Pay Date Not Set</option>
                                                                                <option value="PENDING">All Unpaid</option>
                                                                                <option value="PAID">Paid</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select class="form-select custom-select" id="sp-run">
                                                                                <option value="0">- All Approved Runs -</option>
                                                                                <?php foreach ($runs as $r) { ?>
                                                                                    <option value="<?= (int)$r['payroll_run_id'] ?>">
                                                                                        <?= date('F', mktime(0, 0, 0, (int)$r['payroll_month'], 1)) . ' ' . (int)$r['payroll_year'] ?>
                                                                                    </option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select class="form-select custom-select" id="sp-method-filter">
                                                                                <option value="">- All Methods -</option>
                                                                                <?php foreach ($profileMethods as $m) { ?>
                                                                                    <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                                                                                <?php } ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="col-6 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <input type="text" class="form-control custom-input" id="sp-date-from"
                                                                                placeholder="From"
                                                                                onfocus="this.type='date'; this.showPicker && this.showPicker();"
                                                                                onblur="if(!this.value) this.type='text';">
                                                                        </div>
                                                                        <div class="col-6 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <input type="text" class="form-control custom-input" id="sp-date-to"
                                                                                placeholder="To"
                                                                                onfocus="this.type='date'; this.showPicker && this.showPicker();"
                                                                                onblur="if(!this.value) this.type='text';">
                                                                        </div>

                                                                        <div class="col-12">
                                                                            <span class="text-12 sub-text">Pay date = the scheduled salary date from the staff member's payroll profile, or the actual paid date once a receipt is submitted.</span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-6 d-none d-lg-block"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="sp-per-page" class="pe-2 sub-text">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" id="sp-per-page">
                                                                <option value="10" selected>10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Table -->
                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="sp-table">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Staff Member</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Payroll Month</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Pay Date</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Payment Method</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Net Salary</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Paid Amount</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Paid Date</th>
                                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                    <th class="py-3 bg-table-header"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="sp-body"></tbody>
                                                        </table>
                                                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper">
                                                            <nav class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                <div class="row p-4 align-items-center">
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" id="sp-prev">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                    </button>
                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                        <ul class="pagination-list d-flex mb-0" id="sp-pages"></ul>
                                                                    </div>
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" id="sp-next">
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

                <?php if ($canAddReceipt) { ?>
                    <!-- ========== Add Receipt (confirm payment) ========== -->
                    <div class="modal fade" id="spAddReceiptModal" tabindex="-1" aria-labelledby="spAddTitle" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                            <div class="modal-content p-2">
                                <div class="modal-header">
                                    <div>
                                        <h5 class="modal-title main-text" id="spAddTitle">Confirm Salary Payment</h5>
                                        <span class="text-12 sub-text" id="sp-add-sub"></span>
                                    </div>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" id="sp-add-id">
                                    <div class="row g-3 mb-3">
                                        <div class="col-6 col-md-3">
                                            <div class="p-3 bg-light-theme h-100"><span class="text-12 sub-text d-block">Gross</span><span class="text-16 main-text fw-semibold" id="sp-add-gross">-</span></div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="p-3 bg-light-theme h-100"><span class="text-12 sub-text d-block">Deductions</span><span class="text-16 text-dark-red fw-semibold" id="sp-add-ded">-</span></div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="p-3 bg-light-theme h-100"><span class="text-12 sub-text d-block">Net Salary (payable)</span><span class="text-16 text-dark-green fw-bold" id="sp-add-net">-</span></div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="p-3 bg-light-theme h-100"><span class="text-12 sub-text d-block">Scheduled Pay Date</span><span class="text-16 main-text fw-semibold" id="sp-add-due">-</span></div>
                                        </div>
                                    </div>
                                    <div class="p-3 bl-solid mb-3" id="sp-add-bank"></div>
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label class="text-14 main-text required" for="sp-amount">Paid Amount</label>
                                            <input type="number" class="form-control custom-input mt-2" id="sp-amount" min="0.01" step="0.01">
                                            <span class="sp-diff text-dark-orange mt-1 d-none" id="sp-amount-diff"></span>
                                            <span class="text-12 text-dark-red" data-sp-error="paid_amount"></span>
                                        </div>
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label class="text-14 main-text required" for="sp-date">Paid Date</label>
                                            <input type="date" class="form-control custom-input mt-2" id="sp-date">
                                            <span class="text-12 text-dark-red" data-sp-error="paid_date"></span>
                                        </div>
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label class="text-14 main-text required" for="sp-method">Payment Method</label>
                                            <select class="form-select custom-select mt-2" id="sp-method">
                                                <?php foreach (StaffPayments::PAYMENT_METHODS as $k => $v) { ?>
                                                    <option value="<?= $k ?>"><?= $v ?></option>
                                                <?php } ?>
                                            </select>
                                            <span class="text-12 sub-text mt-1" id="sp-method-hint"></span>
                                            <span class="text-12 text-dark-red" data-sp-error="payment_method"></span>
                                        </div>
                                        <div class="col-12 col-md-6 d-flex flex-column">
                                            <label class="text-14 main-text" for="sp-ref">Reference / Transaction No</label>
                                            <input type="text" class="form-control custom-input mt-2" id="sp-ref" maxlength="100" placeholder="e.g. bank transfer reference">
                                            <span class="text-12 text-dark-red" data-sp-error="reference_no"></span>
                                        </div>

                                        <!-- Receipt: system file chooser (assets/js/fileChooser.js) -->
                                        <div class="col-12 d-flex flex-column">
                                            <label class="text-14 main-text required">Payment Receipt</label>
                                            <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                <span>Drag &amp; Drop your files here</span>
                                                <span>OR</span>
                                                <button class="custom-btn px-4" id="file-selector" type="button">Browse Files</button>
                                                <input type="file" class="d-none" id="file-selector-input" accept="image/*,application/pdf">
                                                <div class="col-12 px-1 mt-4">
                                                    <div class="row" id="list-section"></div>
                                                </div>
                                            </div>
                                            <span class="text-12 sub-text mt-1">PDF, JPG, PNG or WEBP — max 5 MB</span>
                                            <span class="text-12 text-dark-red" data-sp-error="receipt"></span>
                                        </div>
                                        <div class="col-12 d-flex flex-column">
                                            <label class="text-14 main-text" for="sp-note" id="sp-note-label">Note</label>
                                            <textarea class="form-control custom-input mt-2" id="sp-note" rows="8" maxlength="500"></textarea>
                                            <span class="text-12 text-dark-red" data-sp-error="note"></span>
                                        </div>

                                        <div class="col-12">
                                            <div class="form-check p-3 bg-light-theme d-flex gap-2 align-items-start">
                                                <input class="form-check-input text-20 m-0 flex-shrink-0" type="checkbox" id="sp-confirm">
                                                <label class="form-check-label text-14 main-text" for="sp-confirm" id="sp-confirm-text"></label>
                                            </div>
                                            <span class="text-12 text-dark-red" data-sp-error="confirm"></span>
                                        </div>
                                    </div>
                                    <span class="text-12 text-dark-red d-block mt-2" id="sp-add-error"></span>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="custom-outline-btn px-4" data-bs-dismiss="modal">Cancel</button>
                                    <button type="button" class="custom-btn px-4" id="sp-add-save" onclick="spSubmitReceipt()" disabled>Confirm &amp; Submit Receipt</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>

                <!-- ========== View Receipt ========== -->
                <div class="modal fade" id="spViewReceiptModal" tabindex="-1" aria-labelledby="spViewTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content p-2">
                            <div class="modal-header">
                                <div>
                                    <h5 class="modal-title main-text" id="spViewTitle">Salary Payment Receipt</h5>
                                    <span class="text-12 sub-text" id="sp-view-sub"></span>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body" id="sp-view-body"></div>
                            <div class="modal-footer">
                                <a class="custom-outline-btn px-4 text-decoration-none" id="sp-view-download" href="#">Download</a>
                                <a class="custom-btn px-4 text-decoration-none" id="sp-view-open" href="#" target="_blank" rel="noopener">Open Receipt</a>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/staffPayments.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>