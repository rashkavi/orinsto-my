<?php

/**
 * Batch Activity Report — Admin
 * -----------------------------------------
 * Modeled on your lecture-feedback-report.php for page structure, and on
 * the dashboard card/pagination snippets you sent for the summary cards
 * and pagination markup — so this should match the rest of the app
 * pixel-for-pixel rather than introducing a new visual pattern.
 *
 * WHAT THIS PAGE SHOWS
 * - Summary cards (student counts, assignments, payments), same
 *   stats-mini-card / stats-mini-chart[data-chart] markup as your other
 *   dashboards — pulled from BatchDashboardSummary::getBatchDashboardSummary().
 * - "Monthly Engagement" bar chart (assignment submissions per month —
 *   see the FLAG below).
 * - TWO separate, independently paginated/searchable tables:
 *     - Active Students  (students_has_intakes.status_status_id = 1)
 *     - Deactivated Students (status_status_id = STATUS_OVERDUE, i.e. the
 *       exact set checkOverduePaymentsAndUpdateStudentStatus.php flags),
 *       with outstanding balance + the log reason/date.
 *   Both use the ellipsis-numbered pagination markup + handleXPagination()
 *   naming convention from the snippet you sent, not the simple 1..N loop
 *   I used last round.
 * - A "Generate PDF" button that opens api/generateBatchActivityReportPdf.php
 *   in a new tab for download.
 *
 * ASSUMPTIONS / FLAGS (confirm before shipping):
 * - Faculty -> Programme -> Intake cascade + intake scoping: same as last
 *   round, via api/getBatchActivityFilterOptions.php.
 * - "Monthly engagement" still mapped to the assignments-per-month series
 *   inside BatchDashboardSummary — swap the source if you meant something
 *   else (flagged again in api/getBatchActivitySummary.php).
 * - CARD SPARKLINES: your snippet renders `<div class="stats-mini-chart"
 *   data-chart='...'>` and presumably some existing site-wide script scans
 *   for `[data-chart]` on load to draw it. Since these cards are injected
 *   via AJAX (the intake is chosen client-side), I call
 *   `window.renderStatsMiniCharts()` after inserting them IF that function
 *   exists globally already — if your actual init function has a different
 *   name, swap it in `renderBarCards()` below (one line). If no such global
 *   function exists at all, I fall back to a small built-in Chart.js
 *   sparkline so the charts still render either way.
 * - PDF GENERATION: I don't have visibility into which PDF library this
 *   codebase already uses (only PhpSpreadsheet/Excel exports are in scope
 *   I've seen). api/generateBatchActivityReportPdf.php is written against
 *   **Dompdf** (`composer require dompdf/dompdf`) as the most common choice
 *   for this kind of HTML-styled export — swap the require/instantiation
 *   block at the top of that file if you're standardized on mPDF/TCPDF
 *   instead; the HTML template itself doesn't change.
 * - Active Students table: no spec was given for its columns beyond
 *   "active students separate table", so I've shown Student / Student ID /
 *   Outstanding Balance / Payment Status — tell me if you want different
 *   columns (e.g. attendance %, last login).
 */
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

        $facultiesQuery = "SELECT faculty_id, faculty FROM faculties ORDER BY faculty ASC";
        $allFaculties = Database::search($facultiesQuery)->fetch_all(MYSQLI_ASSOC);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Batch Activity Report - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <!-- Fallback sparkline renderer only — see FLAG above. Remove if
                 your site-wide [data-chart] initializer already loads Chart.js. -->
            <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            <style>
                .bar-summary-grid {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 16px;
                }

                @media (max-width: 1200px) {
                    .bar-summary-grid {
                        grid-template-columns: repeat(3, 1fr);
                    }
                }

                @media (max-width: 992px) {
                    .bar-summary-grid {
                        grid-template-columns: repeat(2, 1fr);
                    }
                }

                @media (max-width: 576px) {
                    .bar-summary-grid {
                        grid-template-columns: 1fr;
                    }
                }

                .bar-section-title {
                    font-size: 13px;
                    font-weight: 700;
                    color: var(--sub-text);
                    text-transform: uppercase;
                    letter-spacing: .04em;
                    margin: 8px 0 12px;
                }

                .bar-status-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    font-size: 11px;
                    font-weight: 700;
                    padding: 4px 10px;
                    border-radius: 999px;
                }

                .bar-status-pill.overdue {
                    background: var(--light-red);
                    color: var(--dark-red);
                }

                .bar-status-pill.active {
                    background: var(--light-green);
                    color: var(--dark-green);
                }

                .bar-balance-cell {
                    font-weight: 700;
                    color: var(--dark-red);
                }

                .bar-balance-cell.clear {
                    color: var(--dark-green);
                }

                .bar-reason-cell {
                    max-width: 280px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    display: inline-block;
                    vertical-align: middle;
                }

                #barEngagementChartWrap {
                    height: 260px;
                }
            </style>
        </head>

        <body class="overflow-hidden">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("batch-activity");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("batch-activity");
                                ?>
                            </div>

                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>

                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Batch Activity Report</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="batch-activity-report-desc">
                                                        Student counts, monthly engagement and batch payment health, per intake
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="col-auto">
                                                <button type="button" class="custom-btn px-4" id="bar-generate-pdf-btn" onclick="generateBatchActivityReportPdf()" disabled>
                                                    <iconify-icon icon="mdi:file-pdf-box" width="16"></iconify-icon>
                                                    Generate Batch Report PDF
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ============================ FILTER BAR ============================ -->
                                    <div class="col-12 mb-4 p-4 component-theme">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">Faculty</label>
                                                <select id="bar-faculty-selector" class="form-select custom-select" onchange="onBarFacultyChanged()">
                                                    <option value="0" selected>Select Faculty</option>
                                                    <?php foreach ($allFaculties as $fac) { ?>
                                                        <option value="<?= $fac["faculty_id"] ?>"><?= htmlspecialchars($fac["faculty"]) ?></option>
                                                    <?php } ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">Programme</label>
                                                <select id="bar-programme-selector" class="form-select custom-select" onchange="onBarProgrammeChanged()">
                                                    <option value="0" selected>Select Programme</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                <label class="text-14 main-text mb-2 d-block">Intake / Batch</label>
                                                <select id="bar-intake-selector" class="form-select custom-select" onchange="loadBatchActivityReport()">
                                                    <option value="0" selected>Select Intake</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-4 col-lg-3 d-flex flex-column justify-content-end">
                                                <button type="button" class="custom-btn w-100" onclick="loadBatchActivityReport()">
                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon>
                                                    Refresh
                                                </button>
                                            </div>
                                        </div>
                                        <span id="bar-filter-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                    </div>

                                    <!-- ==================== NO INTAKE SELECTED PLACEHOLDER ==================== -->
                                    <div class="col-12 mb-4 p-4 component-theme text-center py-5" id="bar-no-intake-placeholder">
                                        <iconify-icon icon="mdi:filter-variant" width="32" height="32" class="sub-text mb-2"></iconify-icon>
                                        <div class="text-14 sub-text">Select a Faculty, Programme and Intake above to load the batch activity report.</div>
                                    </div>

                                    <div id="bar-report-content" class="d-none w-100">

                                        <!-- ============================ SUMMARY CARDS ============================ -->
                                        <div class="col-12 mb-2">
                                            <span class="bar-section-title">Students & Assignments</span>
                                        </div>
                                        <div class="col-12 mb-4">
                                            <div class="bar-summary-grid" id="bar-student-cards"></div>
                                        </div>

                                        <div class="col-12 mb-2">
                                            <span class="bar-section-title">Batch Payments</span>
                                        </div>
                                        <div class="col-12 mb-4">
                                            <div class="bar-summary-grid" id="bar-payment-cards"></div>
                                        </div>

                                        <!-- ============================ ENGAGEMENT CHART ============================ -->
                                        <div class="col-12 mb-4 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0 mb-3 align-items-center">
                                                <div class="col">
                                                    <span class="text-14 main-text fw-medium d-block">Monthly Engagement</span>
                                                    <span class="text-12 sub-text">Assignment submissions per month, last 5 months</span>
                                                </div>
                                            </div>
                                            <div id="barEngagementChartWrap">
                                                <canvas id="bar-engagement-chart"></canvas>
                                            </div>
                                        </div>

                                        <!-- ============================ ACTIVE STUDENTS TABLE ============================ -->
                                        <div class="col-12 p-4 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0 mb-3 align-items-center">
                                                <div class="col">
                                                    <span class="text-14 main-text fw-medium d-block">Active Students</span>
                                                    <span class="text-12 sub-text">Currently active enrollments in this intake</span>
                                                </div>
                                            </div>
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <span class="text-14 dark-theme fw-medium" id="bar-active-record-count">0 Records</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-10 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search by student name or ID..." id="bar-active-search-text" onkeyup="barActiveTable.onSearchKeyup()">
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label class="pe-2 sub-text">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" id="bar-active-per-page-selector" onchange="barActiveTable.load(1)">
                                                            <option value="10">10</option>
                                                            <option value="25" selected>25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive-wrapper">
                                                <table class="table bl-solid m-0 p-0 g-0" id="bar-active-table">
                                                    <thead>
                                                        <tr class="br-t-5">
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 fw-medium">Student</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Student ID</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Status</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-end">Outstanding Balance</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Payment Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="bar-active-table-body">
                                                        <tr>
                                                            <td colspan="5" class="text-center py-4 text-14 sub-text">Loading report...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="bar-active-pagination-container">
                                                    <nav aria-label="Active students pagination" class="col-12 px-2 pe-2 m-0 container-fluid">
                                                        <div class="row p-4 align-items-center">
                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="barActiveTable.handlePagination('previousPage')">
                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                                            </button>
                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                <ul class="pagination-list d-flex mb-0" id="bar-active-pagination-list"></ul>
                                                            </div>
                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="barActiveTable.handlePagination('nextPage')">
                                                                <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </nav>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- ============================ DEACTIVATED STUDENTS TABLE ============================ -->
                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0 mb-3 align-items-center">
                                                <div class="col">
                                                    <span class="text-14 main-text fw-medium d-block">Deactivated Students — Payment Due</span>
                                                    <span class="text-12 sub-text">Students currently deactivated by the overdue-payment check, with their outstanding balance and reason</span>
                                                </div>
                                            </div>
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <span class="text-14 dark-theme fw-medium" id="bar-deactivated-record-count">0 Records</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-10 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search by student name or ID..." id="bar-deactivated-search-text" onkeyup="barDeactivatedTable.onSearchKeyup()">
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label class="pe-2 sub-text">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" id="bar-deactivated-per-page-selector" onchange="barDeactivatedTable.load(1)">
                                                            <option value="10">10</option>
                                                            <option value="25" selected>25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="table-responsive-wrapper">
                                                <table class="table bl-solid m-0 p-0 g-0" id="bar-deactivated-table">
                                                    <thead>
                                                        <tr class="br-t-5">
                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 fw-medium">Student</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Student ID</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Status</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-end">Outstanding Balance</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium">Reason</th>
                                                            <th scope="col" class="py-3 bg-table-header text-14 fw-medium text-center">Deactivated On</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="bar-deactivated-table-body">
                                                        <tr>
                                                            <td colspan="6" class="text-center py-4 text-14 sub-text">Loading report...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                                <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="bar-deactivated-pagination-container">
                                                    <nav aria-label="Deactivated students pagination" class="col-12 px-2 pe-2 m-0 container-fluid">
                                                        <div class="row p-4 align-items-center">
                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="barDeactivatedTable.handlePagination('previousPage')">
                                                                <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                                            </button>
                                                            <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                <ul class="pagination-list d-flex mb-0" id="bar-deactivated-pagination-list"></ul>
                                                            </div>
                                                            <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="barDeactivatedTable.handlePagination('nextPage')">
                                                                <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
                                                                <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                            </button>
                                                        </div>
                                                    </nav>
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

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

            <script>
                let barEngagementChartInstance = null;

                function initializeBatchActivityReport() {
                    document.getElementById('skeleton-list')?.classList.add('d-none');
                    document.getElementById('real-list')?.classList.remove('d-none');
                }

                // ---------------------------------------------------------------
                // Faculty -> Programme -> Intake cascade
                // ---------------------------------------------------------------
                function onBarFacultyChanged() {
                    const facultyId = document.getElementById('bar-faculty-selector').value;
                    const programmeSel = document.getElementById('bar-programme-selector');
                    const intakeSel = document.getElementById('bar-intake-selector');
                    programmeSel.innerHTML = '<option value="0" selected>Select Programme</option>';
                    intakeSel.innerHTML = '<option value="0" selected>Select Intake</option>';
                    showBarPlaceholder();
                    if (facultyId === '0') return;

                    fetch(`./api/getBatchActivityFilterOptions.php?level=programme&faculty-id=${facultyId}`)
                        .then(res => res.json())
                        .then(data => {
                            (data.programmes || []).forEach(p => {
                                const opt = document.createElement('option');
                                opt.value = p.programme_id;
                                opt.textContent = p.programme;
                                programmeSel.appendChild(opt);
                            });
                        })
                        .catch(() => setBarFeedback('Could not load programmes.'));
                }

                function onBarProgrammeChanged() {
                    const programmeId = document.getElementById('bar-programme-selector').value;
                    const intakeSel = document.getElementById('bar-intake-selector');
                    intakeSel.innerHTML = '<option value="0" selected>Select Intake</option>';
                    showBarPlaceholder();
                    if (programmeId === '0') return;

                    fetch(`./api/getBatchActivityFilterOptions.php?level=intake&programme-id=${programmeId}`)
                        .then(res => res.json())
                        .then(data => {
                            (data.intakes || []).forEach(i => {
                                const opt = document.createElement('option');
                                opt.value = i.intake_id;
                                opt.textContent = i.intake;
                                intakeSel.appendChild(opt);
                            });
                        })
                        .catch(() => setBarFeedback('Could not load intakes.'));
                }

                function showBarPlaceholder() {
                    document.getElementById('bar-no-intake-placeholder')?.classList.remove('d-none');
                    document.getElementById('bar-report-content')?.classList.add('d-none');
                    document.getElementById('bar-generate-pdf-btn').disabled = true;
                }

                function setBarFeedback(msg) {
                    const el = document.getElementById('bar-filter-feedback');
                    if (el) el.textContent = msg || '';
                }

                function loadBatchActivityReport() {
                    const intakeId = document.getElementById('bar-intake-selector').value;
                    setBarFeedback('');
                    if (!intakeId || intakeId === '0') {
                        showBarPlaceholder();
                        return;
                    }
                    document.getElementById('bar-no-intake-placeholder')?.classList.add('d-none');
                    document.getElementById('bar-report-content')?.classList.remove('d-none');
                    document.getElementById('bar-generate-pdf-btn').disabled = false;

                    loadBarSummary(intakeId);
                    barActiveTable.load(1);
                    barDeactivatedTable.load(1);
                }

                function generateBatchActivityReportPdf() {
                    const intakeId = document.getElementById('bar-intake-selector').value;
                    if (!intakeId || intakeId === '0') return;
                    window.open(`./api/generateBatchActivityReportPdf.php?intake-id=${intakeId}`, '_blank');
                }

                // ---------------------------------------------------------------
                // Summary cards + engagement chart
                // ---------------------------------------------------------------
                function loadBarSummary(intakeId) {
                    fetch(`./api/getBatchActivitySummary.php?intake-id=${intakeId}`)
                        .then(res => res.json())
                        .then(data => {
                            if (data.status !== 'success') {
                                setBarFeedback(data.message || 'Could not load summary.');
                                return;
                            }
                            renderBarCards('bar-student-cards', data.cards.slice(0, 8));
                            renderBarCards('bar-payment-cards', data.cards.slice(8));
                            renderBarEngagementChart(data.engagement_chart_labels, data.engagement_chart_values);
                        })
                        .catch(() => setBarFeedback('Could not load batch summary.'));
                }

                // Matches the stats-mini-card / stats-mini-chart[data-chart]
                // markup from the existing dashboard exactly. See the FLAG in
                // the header comment re: which script actually renders
                // [data-chart] site-wide.
                function renderBarCards(containerId, cards) {
                    const wrap = document.getElementById(containerId);
                    if (!wrap) return;
                    wrap.innerHTML = cards.map(card => `
                        <div class="col-12">
                            <div class="stats-mini-card">
                                <div class="stats-mini-top">
                                    <div class="stats-mini-left">
                                        <div class="stats-mini-icon">
                                            <iconify-icon icon="${card.icon}" width="16" height="16"></iconify-icon>
                                        </div>
                                        <span class="stats-mini-title">${card.title}</span>
                                    </div>
                                    <button type="button" class="stats-mini-menu">
                                        <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                    </button>
                                </div>
                                <div class="stats-mini-body">
                                    <div class="stats-mini-content">
                                        <div class="stats-mini-value">${card.value}</div>
                                        <div class="stats-mini-meta">
                                            ${card.change ? `<span class="stats-mini-change ${card.change_type}">${card.change}</span>` : ''}
                                            <span class="stats-mini-period">${card.period}</span>
                                        </div>
                                    </div>
                                    ${card.chart && card.chart.length ? `<div class="stats-mini-chart" data-chart='${JSON.stringify(card.chart)}'></div>` : ''}
                                </div>
                            </div>
                        </div>
                    `).join('');

                    // Site-wide initializer, if one already exists (see FLAG above).
                    if (typeof window.renderStatsMiniCharts === 'function') {
                        window.renderStatsMiniCharts();
                    } else {
                        renderBarCardSparklinesFallback(wrap);
                    }
                }

                // Fallback only — used if no global [data-chart] renderer is found.
                function renderBarCardSparklinesFallback(wrap) {
                    wrap.querySelectorAll('.stats-mini-chart[data-chart]').forEach((el, idx) => {
                        let values;
                        try {
                            values = JSON.parse(el.getAttribute('data-chart'));
                        } catch (e) {
                            return;
                        }
                        const canvas = document.createElement('canvas');
                        canvas.height = 36;
                        el.appendChild(canvas);
                        new Chart(canvas, {
                            type: 'line',
                            data: {
                                labels: values.map((_, i) => i),
                                datasets: [{
                                    data: values,
                                    borderWidth: 2,
                                    tension: 0.35,
                                    pointRadius: 0,
                                    fill: false
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        enabled: false
                                    }
                                },
                                scales: {
                                    x: {
                                        display: false
                                    },
                                    y: {
                                        display: false
                                    }
                                }
                            }
                        });
                    });
                }

                function renderBarEngagementChart(labels, values) {
                    const ctx = document.getElementById('bar-engagement-chart');
                    if (!ctx) return;
                    if (barEngagementChartInstance) barEngagementChartInstance.destroy();
                    barEngagementChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Assignment submissions',
                                data: values,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    });
                }

                // ---------------------------------------------------------------
                // Shared table controller: search + per-page + ellipsis
                // pagination, matching the handleXPagination('previousPage' /
                // 'nextPage' / 'goToPage', page) convention from the snippet
                // you sent, and the same page-item/hw-40/three-dots markup.
                // ---------------------------------------------------------------
                function createBarTableController(opts) {
                    let currentPage = 1;
                    let totalPages = 1;
                    let searchTimer = null;

                    function load(page) {
                        const intakeId = document.getElementById('bar-intake-selector').value;
                        if (!intakeId || intakeId === '0') return;
                        currentPage = page || 1;

                        const perPage = document.getElementById(opts.perPageSelectId).value;
                        const search = document.getElementById(opts.searchInputId).value;
                        const tbody = document.getElementById(opts.tableBodyId);
                        tbody.innerHTML = `<tr><td colspan="${opts.colspan}" class="text-center py-4 text-14 sub-text">Loading...</td></tr>`;

                        const params = new URLSearchParams({
                            'intake-id': intakeId,
                            page: currentPage,
                            'per-page': perPage,
                            search: search
                        });

                        fetch(`${opts.endpoint}?${params.toString()}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.status !== 'success') {
                                    tbody.innerHTML = `<tr><td colspan="${opts.colspan}" class="text-center py-4 text-14 sub-text">${data.message || 'Could not load records.'}</td></tr>`;
                                    return;
                                }
                                document.getElementById(opts.recordCountId).textContent = `${data.total_records} Records`;
                                tbody.innerHTML = data.records.length ? data.records.map(opts.renderRow).join('') :
                                    `<tr><td colspan="${opts.colspan}" class="text-center py-4 text-14 sub-text">${opts.emptyMessage}</td></tr>`;
                                totalPages = data.total_pages;
                                currentPage = data.page;
                                renderPaginationEllipsis(document.getElementById(opts.paginationListId), totalPages, currentPage, load);
                            })
                            .catch(() => {
                                tbody.innerHTML = `<tr><td colspan="${opts.colspan}" class="text-center py-4 text-14 sub-text">Could not load records.</td></tr>`;
                            });
                    }

                    function onSearchKeyup() {
                        clearTimeout(searchTimer);
                        searchTimer = setTimeout(() => load(1), 350);
                    }

                    function handlePagination(action) {
                        if (action === 'previousPage') load(Math.max(1, currentPage - 1));
                        else if (action === 'nextPage') load(Math.min(totalPages, currentPage + 1));
                    }

                    return {
                        load,
                        onSearchKeyup,
                        handlePagination
                    };
                }

                // Ellipsis pagination list — mirrors the PHP renderPageButton()
                // logic from the snippet you sent, done client-side since these
                // tables load via AJAX.
                function renderPaginationEllipsis(listEl, totalPages, currentPage, onGoToPage) {
                    if (!listEl) return;
                    listEl.innerHTML = '';
                    let shudder1 = false;
                    let shudder2 = false;
                    for (let i = 1; i <= totalPages; i++) {
                        if (i === 1 || i === totalPages || Math.abs(currentPage - i) <= 1) {
                            const li = document.createElement('li');
                            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
                            li.id = `page-${i}`;
                            const btn = document.createElement('button');
                            btn.className = 'hw-40';
                            btn.textContent = i;
                            if (i !== currentPage) btn.onclick = () => onGoToPage(i);
                            li.appendChild(btn);
                            listEl.appendChild(li);
                        } else if (!shudder1 && i < currentPage - 1 && i > 1) {
                            shudder1 = true;
                            listEl.insertAdjacentHTML('beforeend', `<li class="d-flex align-items-end h-100"><iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon></li>`);
                        } else if (!shudder2 && i > currentPage + 1 && i < totalPages) {
                            shudder2 = true;
                            listEl.insertAdjacentHTML('beforeend', `<li class="d-flex align-items-end h-100"><iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon></li>`);
                        }
                    }
                }

                const barActiveTable = createBarTableController({
                    endpoint: './api/getBatchActivityActiveStudents.php',
                    tableBodyId: 'bar-active-table-body',
                    recordCountId: 'bar-active-record-count',
                    searchInputId: 'bar-active-search-text',
                    perPageSelectId: 'bar-active-per-page-selector',
                    paginationListId: 'bar-active-pagination-list',
                    colspan: 5,
                    emptyMessage: 'No active students found in this intake.',
                    renderRow: r => `
                        <tr>
                            <td class="px-4">${r.student_name}</td>
                            <td>${r.student_id_number}</td>
                            <td class="text-center"><span class="bar-status-pill active">Active</span></td>
                            <td class="text-end bar-balance-cell ${r.has_balance ? '' : 'clear'}">${r.balance_formatted}</td>
                            <td class="text-center">${r.payment_status_label}</td>
                        </tr>
                    `
                });

                const barDeactivatedTable = createBarTableController({
                    endpoint: './api/getBatchActivityDuePayments.php',
                    tableBodyId: 'bar-deactivated-table-body',
                    recordCountId: 'bar-deactivated-record-count',
                    searchInputId: 'bar-deactivated-search-text',
                    perPageSelectId: 'bar-deactivated-per-page-selector',
                    paginationListId: 'bar-deactivated-pagination-list',
                    colspan: 6,
                    emptyMessage: 'No students currently overdue in this intake.',
                    renderRow: r => `
                        <tr>
                            <td class="px-4">${r.student_name}</td>
                            <td>${r.student_id_number}</td>
                            <td class="text-center"><span class="bar-status-pill overdue">Overdue</span></td>
                            <td class="text-end bar-balance-cell">${r.balance_formatted}</td>
                            <td><span class="bar-reason-cell" title="${r.reason_full}">${r.reason_short}</span></td>
                            <td class="text-center">${r.deactivated_on}</td>
                        </tr>
                    `
                });
            </script>

        </body>

        </html>
<?php
    }
}
?>