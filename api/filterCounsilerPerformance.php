<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/connection.php");
require_once("../includes/classes/GetLeadStatusStyle.php");
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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 24))) {
                        header("Location: ./dashboard");
                    } else {

                        $user = isset($decoded['user']) ? $decoded['user'] : '';
                        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                        $lead_status = isset($decoded['status']) ? $decoded['status'] : 0;
                        $source = isset($decoded['source']) ? $decoded['source'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                        $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                        $query = "
                                                          SELECT 
                                                            leads.*,
                                                            users.*,
                                                        programmes.*,
                                                        lead_source.*,
                                                            institutes.*,
                                                            institute_has_branches.*,
                                                            lead_student_reg_status.*,
                                                        latest_logs.latest_log,
                                                            `leads`.`lead_id` AS 'nid', `leads`.`name` AS 'full_name', 
                                                            `lead_status`.`lead_status` AS 'new_status',`lead_status`.`lead_status_id` AS `sid`,
                                                            `users`.`user_id` AS `uid`
                                                        FROM `leads`
                                                        INNER JOIN `lead_status` 
                                                            ON leads.lead_status_lead_status_id = lead_status.lead_status_id
                                                        LEFT JOIN `users` 
                                                            ON leads.users_user_id = users.user_id
                                                        LEFT JOIN `programmes` ON `leads`.`programmes_programme_id` = `programmes`.`programme_id`
                                                        LEFT JOIN `lead_source` 
                                                            ON leads.lead_source_lead_source_id = lead_source.lead_source_id
                                                        LEFT JOIN `institutes` 
                                                            ON leads.institutes_institute_id = institutes.institute_id
                                                        LEFT JOIN `institute_has_branches` 
                                                            ON institutes.institute_id = institute_has_branches.institutes_institute_id
                                                        LEFT JOIN `lead_student_reg_status` 
                                                            ON leads.lead_student_reg_status_lead_student_reg_status_id = lead_student_reg_status.lead_student_reg_status_id
                                                        LEFT JOIN (
                                                                                                    SELECT `leads_id`, MAX(`date_updated`) AS latest_log
                                                                                                    FROM `lead_logs`
                                                                                                    GROUP BY `leads_id`
                                                                                                ) AS latest_logs ON `leads`.`lead_id` = `latest_logs`.`leads_id`
                                                        WHERE leads.lead_status_lead_status_id != '1'
                                                          AND leads.users_user_id = '" . $user . "'
                                                         
                                                     ";


                        if (!empty($search_text)) {
                            $query .= " AND (`leads`.`name` LIKE '%" . $search_text . "%' 
                            OR `programmes`.`programme` LIKE '%" . $search_text . "%' 
                            OR `lead_source`.`lead_source` LIKE '%" . $search_text . "%' 
                            OR `institutes`.`institute` LIKE '%" . $search_text . "%' 
                            OR `institute_has_branches`.`institute_has_branch` LIKE '%" . $search_text . "%')";
                        }

                        if (!empty($lead_status)) {
                            $query .= " AND leads.lead_status_lead_status_id = '" . $lead_status . "'";
                        }

                        if (!empty($source)) {
                            $query .= " AND leads.lead_source_lead_source_id = '" . $source . "'";
                        }

                        $query .= "  GROUP BY `leads`.`lead_id`
                                    ORDER BY `leads`.`lead_id` DESC";

                        $results = Database::search($query);

                        $results_count = $results->num_rows;
                        $result_per_page = (int)$resultPerPage;

                        if ($results_count > $result_per_page) {
                            $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                        } else {
                            $number_of_pages = 1;
                        }

                        $page_results = ($pageno - 1) * $result_per_page;
                        $leads = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                        <table class="table bl-solid m-0 p-0 g-0" id="leads-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                        <div class="d-flex flex-row align-items-center">
                                            <span class="text-14 main-text fw-medium mx-2">Lead Id</span>
                                        </div>
                                    </th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Full Name</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Contact Number</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Programme</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>

                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lead Source</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Registration Status</th>
                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                </tr>
                            </thead>
                            <tbody id="user-table-data">
                                <?php

                                if ($leads->num_rows > 0) {
                                    $status_classes = [
                                        "1"  => "table-btn-yellow",
                                        "2"  => "table-btn-primary",
                                        "3"  => "table-btn-danger",
                                        "4"  => "table-btn-success",
                                        "5"  => "table-btn-pink",
                                    ];
                                    while ($data = $leads->fetch_assoc()) {
                                        $statusStyle = GetLeadStatusStyle::get($data['sid']);

                                ?>
                                        <tr class="position-relative">
                                            <td class="align-middle lead-id-td">
                                                <div class="lead-id-card position-relative d-flex align-items-center">
                                                    <span class="mini-left-ribbon"
                                                        style="background: <?= htmlspecialchars($statusStyle['ribbon_bg']) ?>;">
                                                        <?= htmlspecialchars($statusStyle['ribbon_text']) ?>
                                                    </span>
                                                    <div class="lead-status-icon-wrap <?= htmlspecialchars($statusStyle['animation_class']) ?>"
                                                        style="background: <?= htmlspecialchars($statusStyle['label_bg']) ?>; color: <?= htmlspecialchars($statusStyle['label_text']) ?>;">
                                                        <iconify-icon icon="<?= htmlspecialchars($statusStyle['icon']) ?>" width="18" height="18"></iconify-icon>
                                                    </div>

                                                    <span class="text-14 main-text lead-id-text">
                                                        #<?= htmlspecialchars($data["lead_id"]); ?>
                                                    </span>

                                                </div>
                                            </td>

                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["full_name"]; ?></span></td>
                                            <td class="py-3 align-middle">
                                                <span class="text-14 main-text"><?php echo $data["contact"]; ?></span>
                                            </td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["programme"]; ?></span></td>
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?php echo (new DateTime($data["latest_log"]))->format('Y-m-d h:i A') ?></span>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <?php
                                                if (!empty($data['lead_source_id'])) {

                                                ?>
                                                    <button class="table-btn lead-source w-100" data-id="<?= $data["lead_source_id"]; ?>"> <?php echo $data["lead_source"]; ?> <?= $data['institute'] != null ? " - " . $data['institute'] : "";  ?> <?= $data["institute_has_branch"] != null ? " " . $data["institute_has_branch"] : ""; ?></button>
                                                <?php
                                                }
                                                ?>
                                            </td>
                                            <td class="py-2 align-middle">
                                                <?php
                                                if (empty($data["lead_student_reg_status"])) {
                                                ?><span class="text-14 main-text">N/A</span><?php
                                                                                        } else {
                                                                                            $sbutton_class = $status_classes[$data["lead_student_reg_status_id"]];
                                                                                            ?>
                                                    <label class="text-center table-btn <?php echo $sbutton_class; ?> w-100"> <?php echo $data["lead_student_reg_status"]; ?></label>
                                                <?php
                                                                                        }
                                                ?>
                                            </td>
                                            <td class="py-3 align-middle">

                                                <div class="dropdown">
                                                    <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text-color cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                    <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lead-view?lead-id=<?= $data['nid']; ?>">
                                                                <iconify-icon icon="carbon:view" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                View</a></li>
                                                        <?php
                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                                                        ?>
                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick='window.location.href = "edit-lead?lead-id=<?= $data["nid"]; ?>"'>
                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                    Edit</a></li>
                                                        <?php
                                                        }
                                                        ?>

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
                        <div class="bl-solid-lrb br-b-5 m-0 g-0 component-theme" id="pagination-container">
                            <nav aria-label="..." class="col-12 px-2 pe-2 m-0 container-fluid">
                                <div class="row p-4 align-items-center">
                                    <!-- Previous Button (col-1) -->
                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleCounsilerPerformancePagination(<?= $user ?>,'previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($i, $isActive, $id)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleCounsilerPerformancePagination('{$id}','goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton($i, $pageno == $i, $user);
                                                } elseif (!$shudderPages1 && ($i < $pageno - 1 && $i > 1)) {
                                                    $shudderPages1 = true;
                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                } elseif (!$shudderPages2 && ($i > $pageno + 1 && $i < $number_of_pages)) {
                                                    $shudderPages2 = true;
                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text-color m-0 p-0'></iconify-icon></li>";
                                                }
                                            }
                                            ?>
                                        </ul>
                                    </div>
                                    <!-- Next Button (col-1) -->
                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleCounsilerPerformancePagination(<?= $user ?>,'nextPage',null,'<?= $number_of_pages ?>')">
                                        <span class="text-14 main-text fw-semibold">Next</span>
                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                    </button>
                                </div>
                            </nav>
                        </div>
<?php

                    }
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
