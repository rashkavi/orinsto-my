<?php
$status = new stdClass();
require_once '../includes/connection.php';
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $sessionUserObject = SessionUser::getCurrentUserObject();
                    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                    $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                    $user_sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;


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
                                                                       
                                                                    ";


                    if ($search_text != "") {
                        $query .= " AND (`user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `user_email` LIKE '%" . $search_text . "%' OR `user_mobile` LIKE '%" . $search_text . "%')";
                    }

                    if ($user_sort != 0) {
                        switch ($user_sort) {
                            case 1:
                                $query .= " AND `lp`.`earning_status_earning_status_id` IS NULL";
                                break;
                            case 2:
                                $query .= " AND `lp`.`earning_status_earning_status_id` = 1";
                                break;
                            case 3:
                                $query .= " AND `lp`.`earning_status_earning_status_id` = 2";
                                break;
                            case 4:
                                $query .= " AND `lp`.`earning_status_earning_status_id` = 3";
                                break;
                        }
                    }

                    $query .= "  GROUP BY tt.lecturers_lecturers_id, tt.date, tt.start_time, tt.end_time
                                                                        ORDER BY tt.date DESC, tt.start_time DESC";

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $sessions = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");
?>
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
                                    <td colspan="8" class="align-middle justify-content-center">
                                        <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                            <div class="no-data-state">
                                                <div class="no-data-icon-wrap">
                                                    <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                </div>
                                                <p class="no-data-text">No sessions found</p>
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
<?php

                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}


?>