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

        $timezone = new DateTimeZone("Asia/Colombo");
        $dateTime = new DateTime("now", $timezone);
        $today = $dateTime->format('Y-m-d') ?? (new DateTime("now", $timezone))->format('Y-m-d');
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Intakes Attendance Breakdown - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/attendance.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            <style>
                .bas-card {
                    position: relative;
                    padding: 16px;
                }

                .bas-header {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                }

                .bas-thumb {

                    height: 56px;
                    width: auto;
                    object-fit: cover;
                    flex-shrink: 0;
                }

                .bas-thumb-placeholder {
                    width: 56px;
                    height: 56px;
                    border-radius: 14px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                }

                .bas-more-btn {
                    position: absolute;
                    top: 14px;
                    right: 14px;
                    height: 26px;
                    width: 26px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: transparent;
                    border: none;
                }

                .bas-more-btn:hover {
                    background: var(--line-color);
                }

                .bas-stats-list {
                    margin-top: 16px;
                    padding-top: 12px;
                    border-top: 1px solid var(--line-color);
                }

                .bas-stat-row {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 7px 0;
                }

                .bas-stat-pill {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 12px;
                }

                .bas-stat-row-value {
                    font-size: 14px;
                    font-weight: 600;
                }
            </style>
        </head>

        <body class="overflow-hidden" onload="initializeBatchesAttendanceSummary()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("attendance-intake-breakdown");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("attendance-intake-breakdown");
                                ?>
                            </div>

                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column overflow-auto" id="bas-real-list">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row align-items-center p-0 m-0 g-0">
                                            <div class="col-12 col-md-8">
                                                <div class="row p-0 m-0 g-0">
                                                    <span class="main-topic lh-lg">Intakes Attendance Breakdown</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./attendance.php">Attendance</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Attendance Breakdown</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-4 text-md-end">
                                                <span class="text-12 sub-text" id="bas-today-date">Loading...</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ============================ FILTER BAR ============================ -->
                                    <div class="col-12 p-4 mb-4 component-theme">
                                        <div class="row gy-3 align-items-end">
                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                <label class="text-14 main-text mb-2" for="bas-search">Search</label>
                                                <input type="text" id="bas-search" class="form-control custom-input" placeholder="Search by intake, programme, faculty or branch" oninput="filterBatchCards()">
                                            </div>

                                            <div class="col-6 col-md-2 d-flex flex-column">
                                                <label class="text-14 main-text mb-2" for="bas-faculty-filter">Faculty</label>
                                                <select id="bas-faculty-filter" class="form-select custom-select" onchange="filterBatchCards()">
                                                    <option value="">All Faculties</option>
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2 d-flex flex-column">
                                                <label class="text-14 main-text mb-2" for="bas-programme-filter">Programme</label>
                                                <select id="bas-programme-filter" class="form-select custom-select" onchange="filterBatchCards()">
                                                    <option value="">All</option>
                                                </select>
                                            </div>
                                            <div class="col-6 col-md-2 d-flex flex-column">
                                                <label class="text-14 main-text mb-2" for="bas-date">Date</label>
                                                <input type="date" id="bas-date" class="form-control custom-input" value="<?= $today ?>" max="<?= $today ?>" onchange="loadBatchesAttendanceSummary()">
                                            </div>

                                            <div class="col-6 col-md-2">
                                                <button type="button" class="custom-btn w-100" title="Refresh" onclick="loadBatchesAttendanceSummary()">
                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon> Refresh
                                                </button>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <button type="button" class="custom-btn w-100" title="Export to Excel" onclick="exportBatchesAttendanceSummary(this)">
                                                    <iconify-icon icon="mdi:file-excel-outline" width="16"></iconify-icon> Export Report
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- ============================ CARDS ============================ -->
                                    <div class="col-12" id="bas-cards-wrapper">
                                        <div class="row g-3" id="bas-cards-row">
                                            <div class="col-12 text-center py-4 text-14 sub-text" id="bas-loading-state">Loading today's intakes...</div>
                                        </div>
                                    </div>

                                    <!-- ============================ EMPTY STATE ============================ -->
                                    <div class="col-12 p-4 component-theme d-none" id="bas-empty-state">
                                        <div class="d-flex flex-column align-items-center">
                                            <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                            <span class="text-14 sub-text mt-2">No intakes have any attendance records for today yet.</span>
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
            <script>
                const initializeBatchesAttendanceSummary = () => {
                    let allCards = []; // raw data from the API, kept for client-side filtering

                    // Icon badge colours are tinted (15% background) versions of the same
                    // status colours used on the breakdown page's charts, so the whole
                    // attendance feature stays visually consistent.
                    const STAT_DEFS = [{
                            key: 'present',
                            label: 'Present',
                            icon: 'mdi:check-circle-outline',
                            color: '#2563eb'
                        },

                        {
                            key: 'recording_watched',
                            label: 'Recording Watched',
                            icon: 'mdi:weather-sunset-up',
                            color: '#a855f7'
                        },
                        {
                            key: 'absent',
                            label: 'Absent',
                            icon: 'mdi:calendar-remove-outline',
                            color: '#ef4444',
                            full: true
                        },
                    ];

                    window.loadBatchesAttendanceSummary = function() {
                        const row = document.getElementById('bas-cards-row');
                        const emptyState = document.getElementById('bas-empty-state');
                        const date = document.getElementById('bas-date').value;
                        emptyState.classList.add('d-none');
                        row.innerHTML = '<div class="col-12 text-center py-4 text-14 sub-text">Loading intakes...</div>';

                        fetch('api/getIntakesAttendanceSummary.php?date=' + encodeURIComponent(date))
                            .then(res => res.json())
                            .then(data => {
                                if (data.error) {
                                    row.innerHTML = '<div class="col-12 text-center py-4 text-14 sub-text">' + data.error + '</div>';
                                    return;
                                }

                                const label = (data.date === data.today) ?
                                    "Showing today's scheduled intakes - " + data.date :
                                    "Showing scheduled intakes for " + data.date;
                                document.getElementById('bas-today-date').innerText = label;

                                allCards = data.cards || [];
                                buildFilterOptions(allCards);
                                renderCards(allCards);
                            })
                            .catch(() => {
                                row.innerHTML = '<div class="col-12 text-center py-4 text-14 sub-text">Failed to load intakes. Please try again.</div>';
                            });
                    }

                    function buildFilterOptions(cards) {
                        const branchSel = document.getElementById('bas-branch-filter');
                        const facultySel = document.getElementById('bas-faculty-filter');
                        const programmeSel = document.getElementById('bas-programme-filter');

                        const branches = [...new Set(cards.map(c => c.branch))].sort();
                        const faculties = [...new Set(cards.map(c => c.faculty))].sort();
                        const programmes = [...new Set(cards.map(c => c.programme))].sort();

                        branchSel.innerHTML = '<option value="">All Branches</option>' + branches.map(b => `<option value="${b}">${b}</option>`).join('');
                        facultySel.innerHTML = '<option value="">All Faculties</option>' + faculties.map(f => `<option value="${f}">${f}</option>`).join('');
                        programmeSel.innerHTML = '<option value="">All Programmes</option>' + programmes.map(p => `<option value="${p}">${p}</option>`).join('');
                    }

                    window.filterBatchCards = function() {
                        const search = document.getElementById('bas-search').value.trim().toLowerCase();
                        const branch = document.getElementById('bas-branch-filter').value;
                        const faculty = document.getElementById('bas-faculty-filter').value;
                        const programme = document.getElementById('bas-programme-filter').value;

                        const filtered = allCards.filter(c => {
                            const haystack = (c.intake + ' ' + c.programme + ' ' + c.faculty + ' ' + c.branch).toLowerCase();
                            if (search && !haystack.includes(search)) return false;
                            if (branch && c.branch !== branch) return false;
                            if (faculty && c.faculty !== faculty) return false;
                            if (programme && c.programme !== programme) return false;
                            return true;
                        });

                        renderCards(filtered, true);
                    }

                    // initials fallback badge - same idea as the student table's avatar fallback
                    function getInitials(text) {
                        return (text || '').trim().slice(0, 2).toUpperCase();
                    }

                    function renderCards(cards, isFilterPass) {
                        const row = document.getElementById('bas-cards-row');
                        const emptyState = document.getElementById('bas-empty-state');

                        if (!cards || cards.length === 0) {
                            row.innerHTML = '';
                            emptyState.classList.remove('d-none');
                            emptyState.querySelector('span').innerText = isFilterPass ?
                                'No intakes match your search or filters.' :
                                "No intakes have any attendance records for today yet.";
                            return;
                        }

                        emptyState.classList.add('d-none');

                        let html = '';
                        cards.forEach(c => {
                            const thumb = c.intake_cover ?
                                `<img src="./assets/imgs/intakes-covers/${c.intake_cover}" class="bas-thumb" alt="">` :
                                `<div class="bas-thumb-placeholder dark-theme bg-light-theme"><iconify-icon icon="mdi:account-group-outline" width="24" class="sub-text"></iconify-icon></div>`;

                            const statsHtml = STAT_DEFS.map(s => `
                <div class="bas-stat-row">
                    <span class="bas-stat-pill" style="background:${s.color}1f; color:${s.color}">
                        <iconify-icon icon="${s.icon}" width="14"></iconify-icon>
                        ${s.label}
                    </span>
                    <span class="bas-stat-row-value main-text">${c[s.key]}</span>
                </div>
            `).join('');

                            html += `
                <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                    <div class="component-theme bas-card">
                        <button type="button" class="bas-more-btn" title="View breakdown" onclick="goToBatchBreakdown(${c.intake_id})">
                            <iconify-icon icon="mdi:arrow-top-right" width="16" class="sub-text"></iconify-icon>
                        </button>
                        <div class="bas-header">
                            ${thumb}
                            <div class="overflow-hidden pe-4">
                                <div class="text-14 main-text fw-medium text-truncate">${c.intake}</div>
                                <div class="text-11 sub-text text-truncate">${c.branch} - ${c.faculty} - ${c.programme}</div>
                            </div>
                        </div>
                        <div class="bas-stats-list">
                            ${statsHtml}
                        </div>
                    </div>
                </div>
            `;
                        });

                        row.innerHTML = html;
                    }

                    window.goToBatchBreakdown = function(intakeId) {
                        window.location.href = `students-attendance-breakdown.php?intake-id=${intakeId}`;
                    }

                    window.exportBatchesAttendanceSummary = function(btn) {
                        toggleBtnLoader(btn, true);
                        const params = new URLSearchParams({
                            date: document.getElementById('bas-date').value,
                            search: document.getElementById('bas-search').value.trim(),
                            branch: document.getElementById('bas-branch-filter').value,
                            faculty: document.getElementById('bas-faculty-filter').value,
                            programme: document.getElementById('bas-programme-filter').value,
                        });
                        window.location.href = 'api/exportIntakesAttendanceSummary.php?' + params.toString();
                        toggleBtnLoader(btn, false);

                    }

                    loadBatchesAttendanceSummary();
                }
            </script>
        </body>

        </html>
<?php
    }
}
?>