<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
        header("Location: ./dashboard");
    } else {

        $sessionUserObject = SessionUser::getCurrentUserObject();
        $lecturerId = $sessionUserObject['user_id'];
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        // ---- Totals for the "Mark as Paid" summary modal (unchanged logic) ----
        $totalsQuery = "SELECT 
            u.*,
            t.title,
            lp.total_lecture_payments,
            ap.total_assignments_payments
        FROM users u
        INNER JOIN title t 
            ON u.title_title_id = t.title_id
        LEFT JOIN (
            SELECT users_user_id, SUM(amount) AS total_lecture_payments
            FROM lecturer_payments
            WHERE earning_status_earning_status_id = '1'
            AND users_user_id = '" . $lecturerId . "'
            GROUP BY users_user_id
        ) lp ON lp.users_user_id = u.user_id
        LEFT JOIN (
            SELECT users_user_id, SUM(amount) AS total_assignments_payments
            FROM assignment_payments
            WHERE earning_status_earning_status_id = '1'
            AND users_user_id = '" . $lecturerId . "'
            GROUP BY users_user_id
        ) ap ON ap.users_user_id = u.user_id
        WHERE u.user_id = '" . $lecturerId . "'";

        $totalsResult = Database::search($totalsQuery);
        if ($totalsResult->num_rows == 0) {
            header("Location: ./dashboard");
            exit;
        }
        $lecturerData = $totalsResult->fetch_assoc();
        $totalAmount = $lecturerData['total_lecture_payments'] + $lecturerData['total_assignments_payments'];

        // ---- Deduped payable sessions with status, scoped to this lecturer ----
        // Mirrors the lecturers-lecture-payments.php pattern: grouping by
        // (lecturer, date, start_time, end_time) collapses a lecture taught to
        // multiple batches at the exact same slot into ONE row, and shows every
        // session with its current payment status (Scheduled / Confirmed / Rejected).
        $where = " tt.time_table_status_time_table_status_id = 1
            AND TIMESTAMP(tt.date, tt.start_time) < NOW()
            AND l.`lecture_payment _type_lecture_payment _type_id` != 4
            AND u.user_id = '" . $lecturerId . "'";

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
                   MAX(lp.amount) AS confirmed_amount,
                   MAX(lp.lecturer_payments_id) AS lecturer_payments_id
            FROM time_table tt
            INNER JOIN lecturers l ON l.lecturers_id = tt.lecturers_lecturers_id
            INNER JOIN users u ON u.user_id = l.users_user_id
            INNER JOIN subjects s ON s.subject_id = tt.subjects_subject_id
            INNER JOIN `lecture_payment _type` lpt ON lpt.`lecture_payment _type_id` = l.`lecture_payment _type_lecture_payment _type_id`
            INNER JOIN lecturer_payments_has_lectures lpl ON lpl.time_table_time_table_id = tt.time_table_id
            INNER JOIN lecturer_payments lp ON lp.lecturer_payments_id = lpl.lecturer_payments_lecturer_payments_id
            INNER JOIN earning_status es ON es.earning_status_id = lp.earning_status_earning_status_id
            WHERE $where
            GROUP BY tt.lecturers_lecturers_id, tt.date, tt.start_time, tt.end_time
            ORDER BY tt.date DESC, tt.start_time DESC
        ";

        $results = Database::search($query);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>My Scheduled Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                        include "./includes/side-menu.php";
                        load_side_menu::load("my-payments");
                        ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                        <div class="row p-0 m-0 g-0">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("my-payments");
                                ?>
                            </div>
                            <div class="container p-4 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php"; ?>
                            </div>
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0 align-items-center">
                                            <div class="col-12 col-lg-10">
                                                <div class="row">
                                                    <span class="main-topic lh-lg">My Scheduled Payments</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="payments" href="./my-payments">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="#">My Scheduled Payments</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 component-theme p-4">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <span class="sub-topic mb-4">Scheduled Lecture Payments</span>
                                            <div class="table-responsive-wrapper">
                                                <div class="table-responsive" id="users-table-container">
                                                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                        <thead>
                                                            <tr class="br-t-5">
                                                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                    <div class="d-flex flex-row align-items-center">
                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="lp-check-all" onchange="lecturerPaymentsCheckEvents()">
                                                                        <span class="text-14 main-text fw-medium mx-2">Lecture / Intake</span>
                                                                    </div>
                                                                </th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date / Time</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Payment Type</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Units</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Status</th>
                                                                <th scope="col" class="pe-4 py-3 bg-table-header text-14 main-text fw-medium">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="user-table-data">
                                                            <?php
                                                            $pageno = 1;
                                                            $results_count = $results->num_rows;
                                                            $result_per_page = 10;
                                                            $number_of_pages = $results_count > $result_per_page ? ceil($results_count / $result_per_page) : 1;
                                                            $page_results = ($pageno - 1) * $result_per_page;
                                                            $sessions = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                            if ($sessions->num_rows > 0) {
                                                                while ($row = $sessions->fetch_assoc()) {
                                                                    $ttIds = $row['time_table_ids'];
                                                                    $rowKey = str_replace(',', '-', $ttIds);
                                                                    $hours = $row['duration'] / 60;
                                                                    $minHours = (float) $row['minimum_payable_hours'];
                                                                    $billedHours = ($minHours > 0 && $hours < $minHours) ? $minHours : $hours;

                                                                    if ($row['payment_type_id'] == 1) { // Per Hour
                                                                        $units = round($billedHours, 2);
                                                                        $suggestedAmount = round($billedHours * $row['lecture_rate'], 2);
                                                                    } else { // Per Day / Per Project
                                                                        $units = 1;
                                                                        $suggestedAmount = round((float) $row['lecture_rate'], 2);
                                                                    }
                                                                    $displayAmount = $row['confirmed_amount'] !== null ? $row['confirmed_amount'] : $suggestedAmount;

                                                                    $statusId = $row['earning_status_id'];

                                                                    if ($statusId == 1) {
                                                                        $statusLabel = 'Scheduled';
                                                                        $statusClass = 'table-btn-pink';
                                                                    } elseif ($statusId == 2) {
                                                                        $statusLabel = $row['earning_status_name'] ?: 'Confirmed';
                                                                        $statusClass = 'table-btn-success';
                                                                    } elseif ($statusId == 3) {
                                                                        $statusLabel = $row['earning_status_name'] ?: 'Rejected';
                                                                        $statusClass = 'table-btn-danger';
                                                                    } else {
                                                                        $statusLabel = $row['earning_status_name'] ?: 'Pending';
                                                                        $statusClass = 'table-btn-yellow';
                                                                    }
                                                            ?>
                                                                    <tr>
                                                                        <td class="px-4 py-3 align-middle">
                                                                            <div class="d-flex flex-row align-items-center p-0 g-0 m-0">
                                                                                <?php if ($statusId == 1 || $statusId === null) { ?>
                                                                                    <input type="checkbox"
                                                                                        class="form-check-input text-20 mt-0 mb-0 me-2 lp-row-check"
                                                                                        data-amount="<?= $displayAmount ?>"
                                                                                        value="<?= $row['lecturer_payments_id'] ?>"
                                                                                        id="lp-<?= $rowKey ?>"
                                                                                        onchange="lecuturePaymentsCheckRowEvents('<?= $rowKey ?>')">
                                                                                <?php } else { ?>
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" disabled>
                                                                                <?php } ?>
                                                                                <div class="mx-2">
                                                                                    <span class="text-14 main-text d-block"><?= $row['subject'] ?></span>
                                                                                    <?php if ($row['batch_count'] > 1) { ?>
                                                                                        <span class="table-btn table-btn-yellow mt-1"><?= $row['batch_count'] ?> batches</span>
                                                                                    <?php } ?>
                                                                                </div>
                                                                            </div>
                                                                        </td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $row['date'] . ' ' . substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5) ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $row['payment_type_name'] ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text"><?= $units ?></span></td>
                                                                        <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($displayAmount, 2) ?></span></td>
                                                                        <td class="py-2 align-middle">
                                                                            <button class="table-btn <?= $statusClass ?> w-100"><?= $statusLabel ?></button>
                                                                        </td>
                                                                        <td class="pe-4 py-3 align-middle">
                                                                            <button class="table-btn table-btn-dark-theme w-100" onclick="window.location.href='my-lecture-payment?lecture-id=<?= $row['time_table_id']; ?>'">View</button>
                                                                        </td>

                                                                    </tr>
                                                                <?php
                                                                }
                                                            } else {
                                                                ?>
                                                                <tr class="justify-content-center align-middle">
                                                                    <td colspan="7" class="align-middle justify-content-center">
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
                                                    <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                        <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                            <div class="row p-4 align-items-center">
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleMyPaymentsPagination('previousPage')">
                                                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                    <span class="text-14 main-text fw-semibold">Previous</span>
                                                                </button>
                                                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                    <ul class="pagination-list d-flex mb-0">
                                                                        <?php
                                                                        $shudderPages1 = false;
                                                                        $shudderPages2 = false;
                                                                        function renderGeneratePageButton($i, $isActive = false)
                                                                        {
                                                                            $activeClass = $isActive ? 'active' : '';
                                                                            $action = $isActive ? '' : "onclick=\"handleMyPaymentsPagination('goToPage', '{$i}')\"";
                                                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                                                        }
                                                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                                                renderGeneratePageButton($i, $pageno == $i);
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
                                                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleMyPaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

            <div class="modal fade" id="markAsPaidModal" tabindex="-1" role="dialog" aria-labelledby="markAsPaidModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exampleModalLongTitle">Mark as Paid</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="col-12 p-0 g-0">
                                <div class="row gx-4 gy-3">
                                    <div class="col-12 col-lg-6 d-flex flex-column">
                                        <label for="" class="text-14 main-text">Total Payable Amount</label>
                                        <input type="text" class="form-control custom-input mt-2" value="<?= "LKR " . $totalAmount ?>" disabled>
                                    </div>
                                    <div class="col-12 col-lg-6 d-flex flex-column">
                                        <label for="" class="text-14 main-text">Total Collecting Amount</label>
                                        <input type="text" class="form-control custom-input mt-2" id="modalTotalCollected" value="LKR 0" disabled>
                                    </div>
                                    <div class="col-12 d-flex flex-column">
                                        <label class="text-14 main-text" for="">Note</label>
                                        <textarea class="form-control custom-input mt-2" id="note" rows="5"></textarea>
                                        <span id="note-feedback" class="text-dark-red text-12"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="col-12 col-md-4 custom-btn" onclick="markAsPaidLecturerPayments(this, <?= $lecturerId ?>);">Confirm</button>
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
?>