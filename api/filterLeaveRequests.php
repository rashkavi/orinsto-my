<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
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
                    $type = isset($decoded['type']) ? $decoded['type'] : 0;
                    $day_type = isset($decoded['dayType']) ? $decoded['dayType'] : 0;
                    $req_status = isset($decoded['status']) ? $decoded['status'] : '';
                    $sort_name = isset($decoded['sortName']) ? $decoded['sortName'] : '';
                    $sort_date = isset($decoded['sortDate']) ? $decoded['sortDate'] : '';
                    $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                    $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                    $query = "
                    SELECT 
                        lr.leave_request_id,
                        lr.from_date,
                        lr.to_date,
                        lr.total_days,
                        lr.reason,
                        lr.requested_at,
                    
                        lrs.leave_request_status,
                        lrs.leave_request_status_id,
                    
                        lt.leave_type,
                    
                        ldt.leave_day_type,
                    
                        u.user_id,
                        u.user_fname,
                        u.user_lname,
                        u.profile_img,
                    
                        t.title
                    
                    FROM leave_requests AS lr
                    
                    INNER JOIN leave_types AS lt
                        ON lt.leave_type_id = lr.leave_types_leave_type_id
                    
                    INNER JOIN leave_request_status AS lrs
                        ON lrs.leave_request_status_id = lr.leave_request_status_leave_request_status_id
                    
                    INNER JOIN leave_day_type AS ldt
                        ON ldt.leave_day_type_id = lr.leave_day_type_leave_day_type_id
                    
                    INNER JOIN users AS u
                        ON u.user_id = lr.users_user_id
                    
                    INNER JOIN title AS t
                        ON t.title_id = u.title_title_id
                    
                   
                    ";

                    if ($search_text != "") {
                        $query .= " WHERE `user_fname` LIKE '%" . $search_text . "%' OR `user_lname` LIKE '%" . $search_text . "%' OR `user_email` LIKE '%" . $search_text . "%' OR `user_mobile` LIKE '%" . $search_text . "%'";
                    }

                    if ($type != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `lt`.`leave_type_id` = '" . $type . "'";
                        } else {
                            $query .= " AND `lt`.`leave_type_id` = '" . $type . "'";
                        }
                    }

                    if ($day_type != 0) {

                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `ldt`.`leave_day_type_id` = '" . $day_type . "'";
                        } else {
                            $query .= " AND `ldt`.`leave_day_type_id` = '" . $day_type . "'";
                        }
                    }

                    if ($req_status != 0) {
                        if (strpos($query, 'WHERE') == false) {
                            $query .= " WHERE `lrs`.`leave_request_status_id` = '" . $req_status . "'";
                        } else {
                            $query .= " AND `lrs`.`leave_request_status_id` = '" . $req_status . "'";
                        }
                    }

                    if ($sort_name != 0) {
                        $sort = $sort_name == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `user_fname` " . $sort;
                        } else {
                            $query .= ", `user_fname` " . $sort;
                        }
                    }

                    if ($sort_date != 0) {
                        $sort = $sort_date == 1 ? 'ASC' : 'DESC';
                        if (strpos($query, 'ORDER BY') === false) {
                            $query .= " ORDER BY `requested_at` " . $sort;
                        } else {
                            $query .= ", `requested_at` " . $sort;
                        }
                    }


                    if ($sort_name == 0 && $sort_date == 0) {
                        $query .= " ORDER BY `requested_at` DESC";
                    }

                    $results = Database::search($query);
                    $results_count = $results->num_rows;
                    $result_per_page = (int)$resultPerPage;

                    if ($results_count > $result_per_page) {
                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                    } else {
                        $number_of_pages = 1;
                    }

                    $page_results = ($pageno - 1) * $result_per_page;
                    $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                    <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                        <thead>
                            <tr class="br-t-5 align-content-center">
                                <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                    <div class="d-flex flex-row align-items-center cursor-pointer">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                        <div class="d-flex justify-content-between align-items-center w-100" onclick="handleLeaveRequestPagination('sortByName')">
                                            <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                            <?php
                                            if ($sort_name == 0) {
                                            ?>
                                                <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                            <?php
                                            } else {
                                            ?>
                                                <iconify-icon icon="<?= $sort_name == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Leave Type</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Day Type</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Date Range</th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Days</th>
                                <th scope="col" class="py-3 bg-table-header align-content-center cursor-pointer" onclick="handleLeaveRequestPagination('sortByDate')">
                                    <div class="d-flex justify-content-between align-items-center w-100">
                                        <span class="text-14 main-text fw-medium">Requested At</span>
                                        <?php
                                        if ($sort_date == 0) {
                                        ?>
                                            <iconify-icon icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                        <?php
                                        } else {
                                        ?>
                                            <iconify-icon icon="<?= $sort_date == 2 ? 'mingcute:up-fill' : 'mingcute:down-fill' ?>" width="20" height="20" class="dark-theme"></iconify-icon>
                                        <?php
                                        }
                                        ?>
                                    </div>
                                </th>
                                <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Status</th>
                                <?php

                                if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 4) || UserPermissions::hasPermission('users', 10)) {
                                ?>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center"></th>
                                <?php
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody id="user-table-data">
                            <?php
                            if ($users->num_rows > 0) {
                                $statusStyles = [
                                    1 => "table-btn-yellow",   // Pending
                                    2 => "table-btn-success",   // Approved
                                    3 => "table-btn-danger",    // Rejected
                                    4 => "table-btn-gray",       // Cancelled
                                    5 => "table-btn-pink"       // Cancelled
                                ];
                                while ($data = $users->fetch_assoc()) {
                                    $statusClass = $statusStyles[$data["leave_request_status_id"]] ?? "table-btn-gray";
                            ?>
                                    <tr>

                                        <!-- Employee -->
                                        <td class="px-4 py-3 pe-0 align-middle">
                                            <div class="d-flex align-items-center">

                                                <?php
                                                if (!empty($data["profile_img"]) && file_exists('../assets/imgs/users/' . $data["profile_img"])) {
                                                ?>
                                                    <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40">
                                                <?php
                                                } else {
                                                    $fn = strtoupper(substr($data["user_fname"], 0, 1));
                                                    $ln = strtoupper(substr($data["user_lname"], 0, 1));
                                                ?>
                                                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                        <?php echo $fn . $ln; ?>
                                                    </div>
                                                <?php } ?>

                                                <span class="text-14 main-text mx-2">
                                                    <?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?>
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Leave Type -->
                                        <td class="py-3">
                                            <span class="text-14 main-text"><?php echo $data["leave_type"]; ?></span>
                                        </td>

                                        <!-- Day Type -->
                                        <td class="py-3">
                                            <span class="text-14 sub-text"><?php echo $data["leave_day_type"]; ?></span>
                                        </td>

                                        <!-- Date Range -->
                                        <td class="py-3">
                                            <span class="text-14 main-text">
                                                <?php echo $data["from_date"]; ?> → <?php echo $data["to_date"]; ?>
                                            </span>
                                        </td>

                                        <!-- Days -->
                                        <td class="py-3 text-start">
                                            <span class="badge bg-primary-subtle text-primary px-3 py-2 rounded-0">
                                                <?php echo $data["total_days"]; ?>
                                            </span>
                                        </td>

                                        <!-- Requested -->
                                        <td class="py-3">
                                            <span class="text-14 main-text">
                                                <?php echo date("Y-m-d h:i A", strtotime($data["requested_at"])); ?>
                                            </span>
                                        </td>

                                        <!-- Status -->
                                        <td class="py-3 text-center">
                                            <button class="table-btn <?php echo $statusClass; ?> w-100">
                                                <?php echo $data["leave_request_status"]; ?>
                                            </button>
                                        </td>

                                        <!-- Actions -->
                                        <td class="py-3 align-middle text-center">
                                            <div class="d-flex justify-content-center align-items-center">
                                                <div class="dropdown">
                                                    <iconify-icon
                                                        icon="charm:menu-kebab"
                                                        width="20"
                                                        height="20"
                                                        class="cursor-pointer"
                                                        data-bs-toggle="dropdown">
                                                    </iconify-icon>

                                                    <ul class="dropdown-menu shadow border-0 br-5">
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center"
                                                                href="leave-request-view?leave-id=<?php echo $data['leave_request_id']; ?>">
                                                                <iconify-icon icon="mdi:eye-outline" width="20" height="20" class="me-2"></iconify-icon>
                                                                View
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
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
                                <!-- Previous Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleLeaveRequestPagination('previousPage')">
                                    <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Previous</span>
                                </button>
                                <!-- Page Numbers (col-10, centered) -->
                                <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                    <ul class="pagination-list d-flex mb-0">
                                        <?php
                                        $shudderPages1 = false;
                                        $shudderPages2 = false;

                                        function renderPageButton($i, $isActive = false)
                                        {
                                            $activeClass = $isActive ? 'active' : '';
                                            $action = $isActive ? '' : "onclick=\"handleLeaveRequestPagination('goToPage', '{$i}')\"";
                                            echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                        }

                                        for ($i = 1; $i <= $number_of_pages; $i++) {
                                            if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                renderPageButton($i, $pageno == $i);
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
                                <!-- Next Button (col-1) -->
                                <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLeaveRequestPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                    <span class="text-14 main-text fw-semibold d-none d-md-block">Next</span>
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