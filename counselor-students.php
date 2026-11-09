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
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        // NOTE: swap this for your actual SessionUser role-check helper
        // (e.g. SessionUser::ifUserIsCounselor($sessionUserRole)) if one exists.
        $isCounselor = SessionUser::ifUserIsMarketingSRO($sessionUserRole);
        $counselorUserId = (int) $sessionUserObject['user_id'];

        if (!$isCounselor && !SessionUser::ifUserIsPro($sessionUserRole)) {
            header("Location: ./index");
            exit;
        }
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Counselor Students - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            <style>
                #counselorTrendChartWrap {
                    position: relative;
                    height: 300px;
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
                        load_side_menu::load("counselor-students");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("counselor-students");
                                ?>
                            </div>

                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100" id="real-list">
                                <div class="row p-4 m-0 g-0">

                                    <!-- Page header -->
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-12 col-xxl-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg" data-i18n="counselor-dashboard">Students</span>
                                                    <span class="text-14 sub-text lh-base mt-1" data-i18n="counselor-dashboard-desc">Track your assigned students, admissions activity, and performance at a glance</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-12 col-xxl-4 mt-3 mt-xxl-0">
                                                <div class="row gx-3 justify-content-end">
                                                    <div class="col-6 col-md-4 mt-3 m-xxl-0 d-flex justify-content-end align-items-center">
                                                        <div class="dropdown w-100">
                                                            <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                Action
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                <li><a class="dropdown-item" href="#" onclick="downloadCounselorAssignedStudentsCSV(); return false;" data-i18n="download-assigned-students">Download Assigned Students</a></li>
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stat cards -->
                                    <div class="col-12 mb-4">
                                        <div class="container-fluid p-0">
                                            <div class="row g-3" id="counselorStatsCards">
                                                <!-- Rendered by JS from api/counselor-dashboard-data.php -->
                                                <div class="col-12 text-center py-4 sub-text" id="counselorStatsLoading">Loading stats...</div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Trend chart -->
                                    <div class="col-12 p-4 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0 align-items-center mb-3">
                                            <div class="col-12 col-md-6">
                                                <span class="text-16 main-text fw-medium" data-i18n="assignment-trend">Assigned Students Trend</span>
                                                <span class="text-14 sub-text d-block" data-i18n="assignment-trend-desc">Students assigned to you over the last 6 months</span>
                                            </div>
                                        </div>
                                        <div id="counselorTrendChartWrap">
                                            <canvas id="counselorTrendChart"></canvas>
                                        </div>
                                    </div>

                                    <!-- Assigned students list -->
                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <span class="text-14 dark-theme fw-medium" id="counselor-students-count">0 <span data-i18n="assigned">Assigned</span></span>
                                                        </div>
                                                    </div>
                                                    <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." data-i18n="[placeholder]search-placeholder" id="counselor-search-text" onkeyup="handleCounselorPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#counselorStudentsFilter" aria-expanded="false" aria-controls="counselorStudentsFilter">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <div class="collapse" id="counselorStudentsFilter">
                                                        <div class="card card-body border-0 p-0">
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">
                                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                        <select class="form-select custom-select" id="counselor-filter-selector" onchange="handleCounselorFilterChange()">
                                                                            <option value="all">All Assigned</option>
                                                                            <option value="today">Today Assigned</option>
                                                                            <option value="month" selected>This Month Assigned</option>
                                                                            <option value="custom">Custom Range</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-12 col-md-4 col-lg-2 d-none" id="counselor-custom-range-row">
                                                                        <div class="row g-2">
                                                                            <div class="col-6">
                                                                                <input type="date" class="form-select custom-select" id="counselor-range-from">
                                                                            </div>
                                                                            <div class="col-6">
                                                                                <input type="date" class="form-select custom-select" id="counselor-range-to">
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6 d-none d-lg-block"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" id="counselor-show-result-selector" onchange="handleCounselorPagination('filter')">
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
                                                <div class="table-responsive" id="counselor-students-table-container">
                                                    <table class="table bl-solid m-0 p-0 g-0" id="counselor-students-table">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th scope="col" class="px-4 py-3 bg-table-header text-14 fw-medium align-content-center">Student Name</th>
                                                                <th scope="col" class="py-3 py-3 bg-table-header text-14  fw-medium align-content-center">Registration Number</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14  fw-medium align-content-center">Email Address</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14  fw-medium align-content-center">Mobile</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14  fw-medium align-content-center">Assigned Date</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14  fw-medium align-content-center">Status</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14  fw-medium align-content-center"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="counselor-table-data">
                                                            <tr>
                                                                <td colspan="5" class="align-middle text-center py-4 sub-text">Loading...</td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="counselor-pagination-container">
                                                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                            <div class="row p-4 align-items-center">
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleCounselorPagination('previousPage');">
                                                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                                                </button>
                                                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                    <ul class="pagination-list d-flex mb-0" id="counselor-pagination-list"></ul>
                                                                </div>
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCounselorPagination('nextPage');">
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

            <?php
            if ($isCounselor || SessionUser::ifUserIsPro($sessionUserRole)) {
            ?>
                <div class="modal fade br-0" id="counselorCsvDownload" aria-hidden="true" aria-labelledby="counselorCsvDownloadLabel" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                        <div class="modal-content p-4">
                            <div class="modal-header p-0 border-0">
                                <h1 class="modal-title text-16 text-dark-color" id="counselorCsvDownloadLabel" data-i18n="download-assigned-students">Download Assigned Students</h1>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body p-0 mt-3">
                                <div id="counselorWaitingMessage" class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden" data-i18n="loading">Loading...</span>
                                    </div>
                                    <h3 class="mt-3" data-i18n="preparing-csv">Preparing Your File...</h3>
                                    <p class="text-muted" data-i18n="please-wait-preparing-csv">Please wait while we generate your download.</p>
                                </div>

                                <div id="counselorSuccessMessage" class="text-center d-none">
                                    <div class="checkmark-circle">
                                        <div class="checkmark"></div>
                                    </div>
                                    <h3 class="mt-3" data-i18n="download-ready">Download Ready!</h3>
                                    <p class="text-muted" data-i18n="csv-ready">Your file has been successfully generated.</p>
                                    <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
                                </div>

                                <div id="counselorErrorMessage" class="text-center d-none">
                                    <h3 class="mt-3 text-danger" data-i18n="download-failed">Download Failed</h3>
                                    <p class="text-muted" data-i18n="download-failed-desc">Something went wrong while generating your file. Please try again.</p>
                                    <button class="custom-btn dark-theme px-5 mt-3 text-white" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
            }
            ?>

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            <script>
                // PHP is the single source of truth for status labels/colors,
                // JSON-encoded into JS — same pattern as the rest of the app.
                const COUNSELOR_STATUS_CLASSES = <?= json_encode([
                                                        "Active" => "table-btn-success",
                                                        "Inactive" => "table-btn-danger",
                                                    ]) ?>;
            </script>
            <script>
                // Counselor Dashboard controller
                // Depends on: COUNSELOR_STATUS_CLASSES (JSON-encoded from PHP), Chart.js, iconify-icon

                let counselorPaginationState = {
                    page: 1,
                    perPage: 10,
                    totalPages: 1,
                };

                let counselorTrendChartInstance = null;

                document.addEventListener('DOMContentLoaded', function() {
                    loadCounselorDashboardData();
                    handleCounselorPagination('filter');
                });

                // ---------- Stat cards + chart ----------

                function loadCounselorDashboardData() {
                    fetch('api/getCounselorDashboardData.php')
                        .then((res) => res.json())
                        .then((response) => {
                            if (!response.success) {
                                showToast('error', response.message || 'Failed to load dashboard data');
                                return;
                            }
                            renderCounselorStatCards(response.cards);
                            renderCounselorTrendChart(response.chart);
                        })
                        .catch(() => {
                            showToast('error', 'Failed to load dashboard data');
                        });
                }

                function renderCounselorStatCards(cards) {
                    const container = document.getElementById('counselorStatsCards');
                    container.innerHTML = '';

                    cards.forEach((card) => {
                        const changeHtml = card.change ?
                            `<span class="stats-mini-change ${card.change_type}">${card.change}</span>` :
                            '';

                        const chartHtml = card.chart && card.chart.length ?
                            `<div class="stats-mini-chart" data-chart='${JSON.stringify(card.chart)}'></div>` :
                            '';

                        const col = document.createElement('div');
                        col.className = 'col-12 col-md-6 col-lg-4 col-xl-3' + (card.href ? ' cursor-pointer' : '');
                        col.innerHTML = `
            <div class="stats-mini-card" ${card.href ? `onclick="window.location.href='${card.href}'"` : ''}>
                <div class="stats-mini-top">
                    <div class="stats-mini-left">
                        <div class="stats-mini-icon">
                            <iconify-icon icon="${card.icon}" width="16" height="16"></iconify-icon>
                        </div>
                        <span class="stats-mini-title">${card.title}</span>
                    </div>
                    <button type="button" class="stats-mini-menu" onclick="event.stopPropagation();">
                        <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                    </button>
                </div>
                <div class="stats-mini-body">
                    <div class="stats-mini-content">
                        <div class="stats-mini-value">${card.value}</div>
                        <div class="stats-mini-meta">
                            ${changeHtml}
                            <span class="stats-mini-period">${card.period}</span>
                        </div>
                    </div>
                    ${chartHtml}
                </div>
            </div>
        `;
                        container.appendChild(col);
                    });

                    renderStatsMiniBars();
                }

                function generateMiniBars(values = []) {
                    const max = Math.max(...values, 1);

                    return values.map((value, index) => {
                        const height = Math.max((value / max) * 36, 8);
                        const activeClass = index === values.length - 1 ? "active" : "";

                        return `<span class="stats-mini-bar ${activeClass}" style="height:${height}px;"></span>`;
                    }).join("");
                }

                function renderStatsMiniBars() {
                    document.querySelectorAll(".stats-mini-chart").forEach(chart => {
                        try {
                            const values = JSON.parse(chart.dataset.chart || "[]");
                            chart.innerHTML = generateMiniBars(values);
                        } catch (e) {
                            chart.innerHTML = "";
                        }
                    });
                }

                function renderCounselorTrendChart(chartData) {
                    const ctx = document.getElementById('counselorTrendChart').getContext('2d');

                    if (counselorTrendChartInstance) {
                        counselorTrendChartInstance.destroy();
                    }

                    counselorTrendChartInstance = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: chartData.labels,
                            datasets: [{
                                label: 'Students Assigned',
                                data: chartData.values,
                                backgroundColor: 'rgba(13, 110, 253, 0.6)',
                                borderRadius: 6,
                                maxBarThickness: 42,
                            }, ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        precision: 0
                                    },
                                },
                            },
                        },
                    });
                }

                // ---------- Filtered / paginated student list ----------

                function handleCounselorFilterChange() {
                    const filter = document.getElementById('counselor-filter-selector').value;
                    const customRow = document.getElementById('counselor-custom-range-row');
                    customRow.classList.toggle('d-none', filter !== 'custom');

                    if (filter !== 'custom') {
                        handleCounselorPagination('filter');
                    }
                }

                function handleCounselorPagination(action, pageArg) {
                    if (action === 'filter') {
                        counselorPaginationState.page = 1;
                    } else if (action === 'previousPage') {
                        counselorPaginationState.page = Math.max(1, counselorPaginationState.page - 1);
                    } else if (action === 'nextPage') {
                        counselorPaginationState.page = Math.min(counselorPaginationState.totalPages, counselorPaginationState.page + 1);
                    } else if (action === 'goToPage') {
                        counselorPaginationState.page = parseInt(pageArg, 10);
                    }

                    loadCounselorStudents();
                }

                function loadCounselorStudents() {
                    const filter = document.getElementById('counselor-filter-selector').value;
                    const search = document.getElementById('counselor-search-text').value;
                    const perPage = document.getElementById('counselor-show-result-selector').value;
                    const rangeFrom = document.getElementById('counselor-range-from').value;
                    const rangeTo = document.getElementById('counselor-range-to').value;

                    const params = new URLSearchParams({
                        filter: filter,
                        search: search,
                        per_page: perPage,
                        page: counselorPaginationState.page,
                    });

                    if (filter === 'custom') {
                        if (rangeFrom) params.set('range_from', rangeFrom);
                        if (rangeTo) params.set('range_to', rangeTo);
                    }

                    fetch('api/filterCounselorDashboardStudents.php?' + params.toString())
                        .then((res) => res.json())
                        .then((response) => {
                            console.log(response);
                            if (!response.success) {
                                showToast('error', response.message || 'Failed to load students');
                                return;
                            }
                            renderCounselorStudentsTable(response.rows, response.pagination.total_rows);
                            renderCounselorPagination(response.pagination);
                        })
                        .catch(() => {
                            showToast('error', 'Failed to load students');
                        });
                }

                function renderCounselorStudentsTable(rows, totalRows) {
                    const tbody = document.getElementById('counselor-table-data');
                    const countLabel = document.getElementById('counselor-students-count');
                    if (countLabel && typeof totalRows === 'number') {
                        countLabel.innerHTML = `${totalRows} <span>Assigned</span>`;
                    }

                    if (!rows.length) {
                        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="align-middle">
                    <div class="d-flex flex-grow-1 align-items-center justify-content-center py-4">
                        <div class="no-data-state">
                            <div class="no-data-icon-wrap">
                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                            </div>
                            <p class="no-data-text">No data</p>
                        </div>
                    </div>
                </td>
            </tr>
        `;
                        return;
                    }

                    tbody.innerHTML = rows
                        .map((row) => {
                            const avatar = row.profile_img ?
                                `<img src="./assets/imgs/students/${row.profile_img}" class="hwr-40" alt="Student image">` :
                                `<div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">${row.initials}</div>`;

                            const statusClass = COUNSELOR_STATUS_CLASSES[row.status] || 'table-btn-success';

                            return `
                <tr>
                    <td class="px-4 py-3 pe-0 align-middle">
                        <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                            <a href="student-activity?student-id=${row.sId}" class="flex-grow-1 d-flex align-items-center text-decoration-none">
                                ${avatar}
                                <span class="text-14 main-text mx-2">${row.name}</span>
                            </a>
                        </div>
                    </td>
                     <td class="py-2 align-middle"><span class="text-14 main-text">${row.regId}</span></td>
                    <td class="py-2 align-middle"><span class="text-14 main-text">${row.email}</span></td>
                    <td class="py-2 align-middle"><span class="text-14 main-text">${row.mobile}</span></td>
                    <td class="py-2 align-middle"><span class="text-14 main-text">${row.assigned_date}</span></td>
                    <td class="py-2 align-middle">
                        <button class="table-btn ${statusClass} w-100">${row.status}</button>
                    </td>
                     <td class="py-3 align-middle">
                     <div class="dropdown">
                                                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=${row.shiId}">
                                                                                                <iconify-icon icon="hugeicons:activity-01" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Payments</a></li>
                                                                                                <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                                <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="edit-intake-student?student-id=${row.shiId}">
                                                                                                <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                Edit</a></li>
                                                                                                                                                                             
                                                                                    </ul>
                                                                                </div>
                                                                            </td>
                </tr>
            `;
                        })
                        .join('');
                }

                function renderCounselorPagination(pagination) {
                    counselorPaginationState.page = pagination.page;
                    counselorPaginationState.perPage = pagination.per_page;
                    counselorPaginationState.totalPages = pagination.total_pages;

                    const list = document.getElementById('counselor-pagination-list');
                    list.innerHTML = '';

                    let shudder1 = false;
                    let shudder2 = false;

                    for (let i = 1; i <= pagination.total_pages; i++) {
                        const isEdge = i === 1 || i === pagination.total_pages;
                        const isNear = Math.abs(pagination.page - i) <= 2;

                        if (isEdge || isNear) {
                            const li = document.createElement('li');
                            li.className = 'page-item' + (pagination.page === i ? ' active' : '');
                            li.innerHTML = pagination.page === i ?
                                `<button class="py-2 px-3">${i}</button>` :
                                `<button class="py-2 px-3" onclick="handleCounselorPagination('goToPage', ${i})">${i}</button>`;
                            list.appendChild(li);
                        } else if (!shudder1 && i < pagination.page - 2 && i > 1) {
                            shudder1 = true;
                            list.innerHTML += `<li class="d-flex align-items-end h-100"><iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon></li>`;
                        } else if (!shudder2 && i > pagination.page + 2 && i < pagination.total_pages) {
                            shudder2 = true;
                            list.innerHTML += `<li class="d-flex align-items-end h-100"><iconify-icon icon="bi:three-dots" width="20" height="20" class="sub-text m-0 p-0"></iconify-icon></li>`;
                        }
                    }
                }

                // ---------- CSV / XLSX export ----------

                function downloadCounselorAssignedStudentsCSV() {
                    const modalEl = document.getElementById('counselorCsvDownload');
                    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

                    document.getElementById('counselorWaitingMessage').classList.remove('d-none');
                    document.getElementById('counselorSuccessMessage').classList.add('d-none');
                    document.getElementById('counselorErrorMessage').classList.add('d-none');
                    modal.show();

                    fetch('api/exportCounselorAssignedStudents.php')
                        .then((res) => {
                            if (!res.ok) throw new Error('Export failed');
                            const disposition = res.headers.get('Content-Disposition') || '';
                            const match = disposition.match(/filename="?([^"]+)"?/);
                            const filename = match ? match[1] : 'assigned-students.xlsx';
                            return res.blob().then((blob) => ({
                                blob,
                                filename
                            }));
                        })
                        .then(({
                            blob,
                            filename
                        }) => {
                            const url = window.URL.createObjectURL(blob);
                            const link = document.createElement('a');
                            link.href = url;
                            link.download = filename;
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                            window.URL.revokeObjectURL(url);

                            document.getElementById('counselorWaitingMessage').classList.add('d-none');
                            document.getElementById('counselorSuccessMessage').classList.remove('d-none');
                        })
                        .catch(() => {
                            document.getElementById('counselorWaitingMessage').classList.add('d-none');
                            document.getElementById('counselorErrorMessage').classList.remove('d-none');
                        });
                }
            </script>
        </body>

        </html>
<?php
    }
}
?>