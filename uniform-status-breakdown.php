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

        $summaryCards = [
            ['id' => 'total',             'title' => 'Eligible Students',  'icon' => 'mdi:account-group-outline',  'period' => 'With a uniform fee'],
            ['id' => 'pending-payments',  'title' => 'Pending Payments',   'icon' => 'mdi:cash-clock',             'period' => 'Fee not fully paid'],
            ['id' => 'payments-received', 'title' => 'Payments Received',  'icon' => 'mdi:cash-check',             'period' => 'Fee fully paid'],
            ['id' => 'uniforms-pending',  'title' => 'Uniforms Pending',   'icon' => 'mdi:tshirt-crew-outline',    'period' => 'Not yet distributed'],
            ['id' => 'uniforms-received', 'title' => 'Uniforms Received',  'icon' => 'mdi:check-decagram-outline', 'period' => 'Distribution confirmed'],
            ['id' => 'collected',         'title' => 'Fee Collection',     'icon' => 'mdi:cash-multiple',          'period' => 'Total uniform fees paid'],
        ];
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Uniform Status Breakdown - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>

            <style>
                .ub-chart-wrap {
                    position: relative;
                    height: 260px;
                }

                .ub-context-line {
                    line-height: 1.3;
                }

                .ub-pending-row {
                    background: #fffaf0;
                }
            </style>
        </head>

        <body class="overflow-hidden" onload="initializeUniformBreakdown()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("uniform-status-breakdown");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("uniform-status-breakdown");
                                ?>
                            </div>

                            <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column">
                                <div class="row p-4 m-0 g-0">

                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <span class="main-topic lh-lg d-block">Uniform Status Breakdown</span>
                                        <span class="text-14 sub-text lh-base mt-1 d-block">Track uniform fee payments and confirm uniform distribution for eligible students</span>
                                    </div>

                                    <!-- FILTERS -->
                                    <div class="col-12 p-4 mb-4 component-theme">
                                        <div class="row gy-3 align-items-end">
                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Search</label>
                                                <input type="text" id="ub-search" class="form-control custom-input" placeholder="Student name or ID" oninput="onSearchInput()">
                                            </div>

                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Faculty</label>
                                                <select id="faculty-selector" class="form-select custom-select" onchange="onSelectorChanged()">
                                                    <option value="0">All Faculties</option>
                                                    <?php
                                                    $query = "SELECT * FROM `faculties`";

                                                    if (SessionUser::ifUserIsCoordinator($sessionUserRole)) {
                                                        $query .= " WHERE faculties.faculty_id IN (SELECT faculties_faculty_id FROM users_has_permission_to_programmes
                                    INNER JOIN programmes ON programmes.programme_id = users_has_permission_to_programmes.programmes_programme_id 
                                    WHERE users_has_permission_to_programmes.users_user_id = '" . $sessionUserObject['user_id'] . "' 
                                    AND users_has_permission_to_programmes.status_status_id = 1 AND `permission_to_permission_to_id`='72') ";
                                                    }


                                                    $faculties = Database::search($query);
                                                    while ($data = $faculties->fetch_assoc()) {
                                                    ?>
                                                        <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                    <?php
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Programme</label>
                                                <select id="programme-selector" class="form-select custom-select" onchange="onSelectorChanged(1)">
                                                    <option value="0">All Programmes</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Intake</label>
                                                <select id="intake-selector" class="form-select custom-select" onchange="onSelectorChanged(2)">
                                                    <option value="0">All Intakes</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Payment Status</label>
                                                <select id="payment-status-selector" class="form-select custom-select" onchange="loadBreakdown()">
                                                    <option value="">All</option>
                                                    <option value="PENDING">Pending Payment</option>
                                                    <option value="RECEIVED">Payment Received</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <label class="text-14 main-text mb-2 d-block">Uniform Status</label>
                                                <select id="uniform-status-selector" class="form-select custom-select" onchange="loadBreakdown()">
                                                    <option value="">All</option>
                                                    <option value="PENDING">Pending Uniform</option>
                                                    <option value="RECEIVED">Uniform Received</option>
                                                </select>
                                            </div>
                                            <div class="col-12 col-md-3">
                                                <button type="button" class="custom-btn w-100" onclick="loadBreakdown()">
                                                    <iconify-icon icon="mdi:refresh" width="16"></iconify-icon> Refresh
                                                </button>
                                            </div>
                                        </div>
                                        <span id="ub-feedback" class="text-dark-red text-12 d-block mt-2"></span>
                                    </div>

                                    <!-- SUMMARY CARDS -->
                                    <div class="container-fluid p-0 mb-4">
                                        <div class="row g-3">
                                            <?php foreach ($summaryCards as $card) { ?>
                                                <div class="col-12 col-md-6 col-lg-2">
                                                    <div class="stats-mini-card">
                                                        <div class="stats-mini-top">
                                                            <div class="stats-mini-left">
                                                                <div class="stats-mini-icon"><iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon></div>
                                                                <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="stats-mini-body">
                                                            <div class="stats-mini-content">
                                                                <div class="stats-mini-value" id="ub-value-<?= $card['id'] ?>">-</div>
                                                                <div class="stats-mini-meta"><span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>

                                    <!-- CHARTS -->
                                    <div class="col-12 mb-4">
                                        <div class="row g-3">
                                            <div class="col-12 col-lg-4">
                                                <div class="p-4 component-theme h-100">
                                                    <div class="text-14 main-text fw-medium mb-3">Payment Status</div>
                                                    <div class="ub-chart-wrap"><canvas id="ub-payment-chart"></canvas></div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <div class="p-4 component-theme h-100">
                                                    <div class="text-14 main-text fw-medium mb-3">Uniform Distribution</div>
                                                    <div class="ub-chart-wrap"><canvas id="ub-uniform-chart"></canvas></div>
                                                </div>
                                            </div>
                                            <div class="col-12 col-lg-4">
                                                <div class="p-4 component-theme h-100">
                                                    <div class="text-14 main-text fw-medium mb-3">Collected vs Outstanding</div>
                                                    <div class="ub-chart-wrap"><canvas id="ub-fee-chart"></canvas></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TABLE -->
                                    <div class="col-12 p-4 component-theme" style="overflow-x:auto">
                                        <table class="table bl-solid m-0 p-0 g-0">
                                            <thead>
                                                <tr class="br-t-5">
                                                    <th class="px-4 py-3 bg-table-header main-text fw-medium">Student</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Student ID</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Faculty / Programme / Intake</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Scheduled</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Paid</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-end">Outstanding</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Payment</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Uniform</th>
                                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ub-table-body">
                                                <tr>
                                                    <td colspan="9" class="text-center py-4 text-14 sub-text">Loading...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div class="flex-grow-1"></div>
                                <footer><?php include './includes/footer.php' ?></footer>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONFIRM MODAL -->
            <div class="modal fade" id="ub-confirm-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-2">
                        <div class="modal-header">
                            <h5 class="modal-title main-text">Confirm Uniform Distribution</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-14 main-text" id="ub-confirm-msg"></p>
                            <label class="text-14 main-text mb-2 d-block" for="ub-note">Distribution notes</label>
                            <textarea id="ub-note" class="form-control custom-input" rows="3" maxlength="500" placeholder="e.g. Collected in person, sizes issued..."></textarea>
                            <span id="ub-confirm-error" class="text-dark-red text-12 d-block mt-2"></span>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="custom-outline-btn" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="custom-btn" id="ub-confirm-btn" onclick="submitConfirmation()">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DETAILS MODAL -->
            <div class="modal fade" id="ub-details-modal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-2">
                        <div class="modal-header">
                            <h5 class="modal-title main-text">Distribution Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="ub-details-body"></div>
                        <div class="modal-footer"><button type="button" class="custom-outline-btn" data-bs-dismiss="modal">Close</button></div>
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
?>