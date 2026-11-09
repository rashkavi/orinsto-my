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

        // ---- Deduped payable sessions with status ----
        // Grouping by (lecturer, date, start_time, end_time) collapses a lecture
        // taught to multiple batches at the exact same slot into ONE row.
        // No longer filtered to unpaid-only — shows every session with its
        // current payment status (Pending / Confirmed / Rejected).
        $where = " tt.time_table_status_time_table_status_id = 1
                                                                               AND tt.date < CURDATE()
                                                                               AND l.`lecture_payment _type_lecture_payment _type_id` != 4 ";

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

        $results = Database::search($query);
?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Lecturers Lecture Payments - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 bg-content-body contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12">
                                                <div class="row">
                                                    <span class="main-topic lh-lg">Lecturers Lecture Payments</span>
                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                        <ol class="breadcrumb m-0">
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./payments';">Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-payments';">Lecturers Payments</a></li>
                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" onclick="window.location.href='./lecturers-lecture-payments';">Lecturers Lecture Payments</a></li>
                                                        </ol>
                                                    </nav>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">

                                                            <span class="text-14 dark-theme fw-medium"><?= $results->num_rows ?> pending Confirmation</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="user-search-text" onkeyup="handleLecturersLecturePaymentsPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                        <select class="form-select custom-select" aria-label="Default select example" id="sort-selector" onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                            <option selected value="0">- Filter Data -</option>
                                                            <option value="1">Pending</option>
                                                            <option value="2">Scheduled</option>
                                                            <option value="3">Paid</option>
                                                            <option value="4">Rejected</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button">Search</button>
                                                    </div>

                                                    <div class="d-none d-lg-block col-6"></div>
                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleLecturersLecturePaymentsPagination('filter')">
                                                            <option selected value="10">10</option>
                                                            <option value="25">25</option>
                                                            <option value="50">50</option>
                                                            <option value="100">100</option>
                                                            <option value="250">250</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12">
                                                <div class="row p-0 m-0 g-0 align-content-center">
                                                    <div class="table-responsive-wrapper">
                                                        <div class="table-responsive" id="users-table-container">
                                                            <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                <thead>
                                                                    <tr class="br-t-5">
                                                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Lecturer</th>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lecture / Intake</th>
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
                                                                                    <span class="text-14 main-text"><?= $row['user_fname'] . ' ' . $row['user_lname'] ?></span>
                                                                                </td>
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= $row['subject'] ?></span>
                                                                                    <?php if ($row['batch_count'] > 1) { ?>
                                                                                        <span class="table-btn table-btn-yellow ms-1"><?= $row['batch_count'] ?> batches</span>
                                                                                    <?php } ?>
                                                                                </td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $row['date'] . ' ' . substr($row['start_time'], 0, 5) . '-' . substr($row['end_time'], 0, 5) ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $row['payment_type_name'] ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $units ?></span></td>
                                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= number_format($displayAmount, 2) ?></span></td>
                                                                                <td class="py-2 align-middle">
                                                                                    <button class="table-btn <?= $statusClass ?> w-100"><?= $statusLabel ?></button>
                                                                                </td>

                                                                                <td class="py-3 pe-4 align-middle">
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lecturer-lecture-payment?lecture-id=<?php echo $row['time_table_id']; ?>">
                                                                                                    <iconify-icon icon="solar:eye-broken" width="20" height="20" class="sub-text me-3"></iconify-icon>
                                                                                                    View</a></li>
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
                                                            <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme pagination-wrapper" id="pagination-container">
                                                                <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                                                    <div class="row p-4 align-items-center">
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLecturersLecturePaymentsPagination('previousPage')">
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
                                                                                    $action = $isActive ? '' : "onclick=\"handleLecturersLecturePaymentsPagination('goToPage', '{$i}')\"";
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
                                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLecturersLecturePaymentsPagination('nextPage',null,'<?= $number_of_pages ?>')">
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

            <!-- Confirmation Modal=>Start -->
            <div class="modal fade  confirmation " id="confirmation" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h1 class="modal-title text-16 text-dark-color" id="editPaymentLabel">Confirmation Message</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="model-body p-0 mt-3">
                            <div class="col-12 container-fluid p-0">
                                <div class="d-flex flex-row align-items-center">
                                    <iconify-icon icon="fluent:question-circle-16-filled" class="dark-blue" width="40" height="40"></iconify-icon>
                                    <p class="text-16 main-text px-2 m-0">Would you like to remove selected
                                        examination ?</p>
                                </div>
                                <div class="col-12 d-flex flex-row align-items-center justify-content-end mt-3">
                                    <button class="custom-btn px-5 pe-5 me-3">Yes</button>
                                    <button class="custom-btn component-theme bl-solid main-text px-5 pe-5">No</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Confirmation Modal => End -->

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