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
                        $type = isset($decoded['type']) ? $decoded['type'] : 0;
                        $period = isset($decoded['period']) ? $decoded['period'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                        $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                        $query = "
                        SELECT 
                            lt.`lead_target_id`,
                            lt.`target`,
                            lt.`start_date`,
                            lt.`end_date`,
                            lt.`date_added`,
                            lt.`users_user_id`,
                            lt.`counselor_user_id`,
                            lt.`lead_target_period_lead_target_period_id`,
                            lt.`lead_target_type_lead_target_type_id`,
                            lt.`intakes_intake_id`,
                    
                            u.`user_fname`,
                            u.`user_lname`,
                            ltp.`lead_target_period`,
                            ltp.`lead_target_period_id`,
                            ltt.`lead_target_type`,

                            i.`intake`,
                    
                            COALESCE(a.`achieved_count`, 0) AS `achieved_count`,
                            GREATEST(lt.`target` - COALESCE(a.`achieved_count`, 0), 0) AS `pending_count`,
                            CASE 
                                WHEN lt.`target` > 0 
                                THEN ROUND((COALESCE(a.`achieved_count`, 0) / lt.`target`) * 100, 0)
                                ELSE 0
                            END AS `achieved_percentage`
                    
                        FROM `lead_targets` lt
                        INNER JOIN `users` u 
                            ON u.`user_id` = lt.`counselor_user_id`
                        INNER JOIN `lead_target_period` ltp
                            ON ltp.`lead_target_period_id` = lt.`lead_target_period_lead_target_period_id`
                        INNER JOIN `lead_target_type` ltt
                            ON ltt.`lead_target_type_id` = lt.`lead_target_type_lead_target_type_id`
                        LEFT JOIN `intakes` i
                            ON i.`intake_id` = lt.`intakes_intake_id`
                    
                        LEFT JOIN (
                            SELECT 
                                lt2.`lead_target_id`,
                                COUNT(DISTINCT CASE
                                    WHEN lt2.`lead_target_type_lead_target_type_id` = 1
                                         AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                    THEN ll.`leads_id`
                    
                                    WHEN lt2.`lead_target_type_lead_target_type_id` = 2
                                         AND ll.`lead_status_lead_status_id` IN (7,11,12,13,14,15,16,17)
                                         AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                    THEN ll.`leads_id`
                    
                                    WHEN lt2.`lead_target_type_lead_target_type_id` = 3
                                         AND ll.`lead_status_lead_status_id` = 11
                                         AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                    THEN ll.`leads_id`
                                END) AS `achieved_count`
                            FROM `lead_targets` lt2
                            LEFT JOIN `lead_logs` ll
                                ON ll.`users_user_id` = lt2.`counselor_user_id`
                            GROUP BY lt2.`lead_target_id`
                        ) a ON a.`lead_target_id` = lt.`lead_target_id`
                    WHERE lt.`counselor_user_id` = '" . $user . "'
                        
                    ";

                        if (!empty($search_text)) {
                            $query .= " AND (`ltt`.`lead_target_type` LIKE '%" . $search_text . "%' 
                            OR `ltp`.`lead_target_period` LIKE '%" . $search_text . "%' 
                            OR `lt`.`target` LIKE '" . $search_text . "%'
                          
                            OR `i`.`intake` LIKE '%" . $search_text . "%')";
                        }

                        if (!empty($type)) {
                            $query .= " AND ltt.lead_target_type_id = '" . $type . "'";
                        }

                        if (!empty($period)) {
                            $query .= " AND ltp.lead_target_period_id = '" . $period . "'";
                        }

                        $query .= " ORDER BY lt.`lead_target_id` DESC";

                        function getPreviousTargetPercentage($currentTargetId, $counselorId, $targetTypeId, $periodTypeId)
                        {
                            $previousQuery = "
                                       SELECT 
                                           lt.`target`,
                                           COALESCE(a.`achieved_count`, 0) AS `achieved_count`
                                       FROM `lead_targets` lt
                                       LEFT JOIN (
                                           SELECT 
                                               lt2.`lead_target_id`,
                                               COUNT(DISTINCT CASE
                                                   WHEN lt2.`lead_target_type_lead_target_type_id` = 1
                                                       AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                   THEN ll.`leads_id`

                                                   WHEN lt2.`lead_target_type_lead_target_type_id` = 2
                                                       AND ll.`lead_status_lead_status_id` IN (7,11,12,13,14,15,16,17)
                                                       AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                   THEN ll.`leads_id`

                                                   WHEN lt2.`lead_target_type_lead_target_type_id` = 3
                                                       AND ll.`lead_status_lead_status_id` = 11
                                                       AND ll.`date_updated` BETWEEN lt2.`start_date` AND CONCAT(lt2.`end_date`, ' 23:59:59')
                                                   THEN ll.`leads_id`
                                               END) AS `achieved_count`
                                           FROM `lead_targets` lt2
                                           LEFT JOIN `lead_logs` ll
                                               ON ll.`users_user_id` = lt2.`counselor_user_id`
                                           GROUP BY lt2.`lead_target_id`
                                       ) a ON a.`lead_target_id` = lt.`lead_target_id`
                                       WHERE lt.`counselor_user_id` = '$counselorId'
                                       AND lt.`lead_target_type_lead_target_type_id` = '$targetTypeId'
                                       AND lt.`lead_target_period_lead_target_period_id` = '$periodTypeId'
                                       AND lt.`lead_target_id` < '$currentTargetId'
                                       ORDER BY lt.`lead_target_id` DESC
                                       LIMIT 1
                                   ";

                            $result = Database::search($previousQuery);

                            if ($result->num_rows > 0) {
                                $data = $result->fetch_assoc();
                                $target = (int)$data['target'];
                                $achieved = (int)$data['achieved_count'];

                                if ($target > 0) {
                                    return round(($achieved / $target) * 100);
                                }
                            }

                            return 0;
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
                        $targets = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

?>
                        <table class="table bl-solid m-0 p-0 g-0" id="leads-table">
                            <thead>
                                <tr class="br-t-5">
                                    <th class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Target Type</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Intake</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Target</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium">Expected End</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Achieved</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Pending</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Achieved %</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Previous %</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Trend</th>
                                    <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php

                                if ($targets->num_rows > 0) {
                                    $status_classes = [
                                        "1"     => "table-btn-orange",
                                        "2"  => "table-btn-pink",
                                        "3"  => "table-btn-yellow",
                                    ];

                                    function getProgressBarColor($percentage)
                                    {
                                        if ($percentage < 50) {
                                            return "bg-dark-red";
                                        } elseif ($percentage < 75) {
                                            return "bg-dark-orange";
                                        } else {
                                            return "bg-dark-green";
                                        }
                                    }
                                    while ($data = $targets->fetch_assoc()) {
                                        $previousPercentage = getPreviousTargetPercentage(
                                            $data["lead_target_id"],
                                            $data["counselor_user_id"],
                                            $data["lead_target_type_lead_target_type_id"],
                                            $data["lead_target_period_lead_target_period_id"]
                                        );

                                        $percentage = (int)$data["achieved_percentage"];
                                        $achieved = (int)$data["achieved_count"];
                                        $pending = (int)$data["pending_count"];

                                        if ($percentage > $previousPercentage) {
                                            $trendIcon = "mdi:trending-up";
                                            $trendClass = "text-success";
                                            $trendBg = "bg-success-subtle";
                                            $trendText = "Up";
                                        } elseif ($percentage < $previousPercentage) {
                                            $trendIcon = "mdi:trending-down";
                                            $trendClass = "text-danger";
                                            $trendBg = "bg-danger-subtle";
                                            $trendText = "Down";
                                        } else {
                                            $trendIcon = "mdi:minus";
                                            $trendClass = "text-secondary";
                                            $trendBg = "bg-secondary-subtle";
                                            $trendText = "Same";
                                        }


                                ?>
                                        <tr>

                                            <td class="px-4 py-3">
                                                <span class="text-14 main-text"><?= $data["lead_target_type"] ?></span>
                                            </td>

                                            <td class="py-3">
                                                <span class="text-14 main-text"><?= !empty($data["intake"]) ? $data["intake"] : "-" ?></span>
                                            </td>
                                            <td class="py-3 text-center">
                                                <span class="fw-bold"><?= $data["target"] ?></span>
                                            </td>
                                            <td class="py-3">
                                                <span class="text-14 main-text">
                                                    <?= $data["end_date"] ?>
                                                </span>
                                            </td>
                                            <td class="py-3 text-center">
                                                <span class="badge rounded-pill bg-success-subtle text-success px-3 py-2"><?= $achieved ?></span>
                                            </td>
                                            <td class="py-3 text-center">
                                                <span class="badge rounded-pill bg-warning-subtle text-warning px-3 py-2"><?= $pending ?></span>
                                            </td>
                                            <td class="py-3 text-center" style="min-width: 180px;">
                                                <div class="d-flex flex-column gap-1">
                                                    <div class="fw-semibold"><?= $percentage ?>%</div>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar <?= getProgressBarColor($percentage) ?> br-0" role="progressbar" style="width: <?= min($percentage, 100) ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-3 text-center">
                                                <span class="text-14 main-text"><?= $previousPercentage ?>%</span>
                                            </td>
                                            <td class="py-3 text-center">
                                                <div class="d-inline-flex align-items-center justify-content-center gap-2 px-3 py-2 w-100 <?= $trendBg ?>">
                                                    <iconify-icon icon="<?= $trendIcon ?>" class="<?= $trendClass ?>" width="18" height="18"></iconify-icon>
                                                    <span class="text-14 <?= $trendClass ?> fw-semibold"><?= $trendText ?></span>
                                                </div>
                                            </td>
                                            <td class="py-3 align-middle">
                                                <div class="dropdown">
                                                    <iconify-icon
                                                        icon="charm:menu-kebab"
                                                        class="cursor-pointer"
                                                        data-bs-toggle="dropdown"
                                                        data-bs-boundary="viewport"></iconify-icon>

                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 br-5">
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center"
                                                                href="counselor-target-summary?target-id=<?= $data['lead_target_id']; ?>">
                                                                <iconify-icon icon="carbon:view" width="20" class="me-2"></iconify-icon>
                                                                View
                                                            </a>
                                                        </li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center cursor-pointer"
                                                                onclick='openEditTargetModal(
                                                                                                                <?= json_encode($data["lead_target_id"]) ?>,
                                                                                                                <?= json_encode($data["lead_target_type_lead_target_type_id"]) ?>,
                                                                                                                <?= json_encode($data["lead_target_period_lead_target_period_id"]) ?>,
                                                                                                                <?= json_encode($data["intakes_intake_id"] ?? 0) ?>,
                                                                                                                <?= json_encode($data["start_date"]) ?>,
                                                                                                                <?= json_encode($data["end_date"]) ?>,
                                                                                                                <?= json_encode($data["target"]) ?>
                                                                                                            )'>
                                                                <iconify-icon icon="ic:sharp-edit" width="20" class="me-2"></iconify-icon>
                                                                Edit
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php
                                    }
                                } else {
                                    ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                        </td>
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
                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleLeadsTargetPagination(<?= $user ?>,'previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($i, $isActive = false, $id)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleLeadsTargetPagination('{$id}','goToPage', '{$i}')\"";
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
                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleLeadsTargetPagination(<?= $user ?>,'nextPage',null,'<?= $number_of_pages ?>')">
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
