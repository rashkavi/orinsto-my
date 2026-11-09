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
                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 1) || UserPermissions::hasPermission('leads', 10))) {
                        header("Location: ./dashboard");
                    } else {

                        $search_text = isset($decoded['text']) ? $decoded['text'] : '';
                        $agent = isset($decoded['agent']) ? $decoded['agent'] : 0;
                        $sort = isset($decoded['sort']) ? $decoded['sort'] : 0;
                        $source = isset($decoded['source']) ? $decoded['source'] : 0;
                        $regStatus = isset($decoded['regStatus']) ? $decoded['regStatus'] : 0;
                        $resultPerPage = isset($decoded['resultPerPage']) ? $decoded['resultPerPage'] : 10;
                        $pageno = isset($decoded['page']) ? $decoded['page'] : 1;

                        $d = new DateTime();
                        $tz = new DateTimeZone("Asia/Colombo");
                        $d->setTimezone($tz);
                        $date = $d->format("Y-m-d H:i:s");

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
            `users`.`user_id` AS `uid`,
             `students`.`profile_img`,
            `login_details`.`first_name`,
            `login_details`.`last_name`,
            `title`.`title`,
            `students_has_intakes`.`students_has_intakes_id`
        FROM `leads`
        INNER JOIN `lead_status` 
            ON leads.lead_status_lead_status_id = lead_status.lead_status_id
        INNER JOIN `students_has_intakes` 
            ON `leads`.`lead_id` = `students_has_intakes`.`leads_lead_id`
       INNER JOIN `students` 
            ON `students_has_intakes`.`students_student_id`=`students`.`student_id`
        INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details_id`
        INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
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
         WHERE leads.lead_status_lead_status_id = 6
        
    ";

                        if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                            $query .= " AND `leads`.`users_user_id` = '" . $sessionUserObject['user_id'] . "'";
                        }

                        if ($search_text != "") {
                            $query .= " AND (`lead_id` LIKE '%" . $search_text . "%' OR `name` LIKE '%" . $search_text . "%' OR `contact` LIKE '%" . $search_text . "%' OR `programme` LIKE '%" . $search_text . "%')";
                        }

                        if ($agent != 0) {
                            $query .= " AND `leads`.`users_user_id` = '" . $agent . "'";
                        }

                        if ($sort != 0) {
                            $query .= " AND `leads`.`lead_status_lead_status_id` = '" . $sort . "'";
                        }

                        if ($source != 0) {
                            $query .= " AND `leads`.`lead_source_lead_source_id` = '" . $source . "'";
                        }

                        if ($regStatus != 0) {
                            $query .= " AND `leads`.`lead_student_reg_status_lead_student_reg_status_id` = '" . $regStatus . "'";
                        }

                        $query .= " GROUP BY `leads`.`lead_id` ORDER BY latest_logs.latest_log DESC";

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

                                    <?php
                                    if (!SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                    ?>
                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assigned Counselor</th>
                                    <?php
                                    }
                                    ?>
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

                                            <td class="py-2 pe-0 align-middle">
                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                    <?php
                                                    if (!empty($data["profile_img"])) {
                                                        $fileUrl = './assets/imgs/students/' . $data["profile_img"];
                                                        if (!file_exists('../assets/imgs/students/' . $data["profile_img"])) {
                                                            $fileUrl = Project::getBasePortalUrl() . '/assets/imgs/students/' . $data["profile_img"];
                                                        }
                                                    ?>
                                                        <img src="<?php echo $fileUrl; ?>" class="hwr-40" alt="Students image">
                                                    <?php
                                                    } else {
                                                        $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                        $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                    ?>
                                                        <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                    <?php
                                                    }
                                                    ?>
                                                    <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                </div>
                                            </td>
                                            <td class="py-3 align-middle">
                                                <span class="text-14 main-text"><?php echo $data["contact"]; ?></span>
                                            </td>
                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["programme"]; ?></span></td>
                                            <td class="py-2 align-middle">
                                                <span class="text-14 main-text"><?php echo (new DateTime($data["latest_log"]))->format('Y-m-d h:i A') ?></span>
                                            </td>

                                            <?php
                                            if (!SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                            ?>
                                                <td class="py-2 align-middle">
                                                    <span class="text-14 main-text"><?= $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                </td>
                                            <?php
                                            }
                                            ?>
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
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lead-registered-student-details?lead-id=<?= $data["nid"]; ?>">
                                                                <iconify-icon icon="solar:user-broken" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                Student Details</a></li>
                                                        <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                        <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="lead-registered-student-payments?student-id=<?= $data["students_has_intakes_id"]; ?>">
                                                                <iconify-icon icon="material-symbols-light:star" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                Payments</a></li>
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
                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleEnrollmentPagination('previousPage')">
                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="darkColor px-1"></iconify-icon>
                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                    </button>
                                    <!-- Page Numbers (col-10, centered) -->
                                    <div class="col-10 custom-pagination d-flex justify-content-center">
                                        <ul class="pagination-list d-flex mb-0">
                                            <?php
                                            $shudderPages1 = false;
                                            $shudderPages2 = false;

                                            function renderPageButton($i, $isActive = false)
                                            {
                                                $activeClass = $isActive ? 'active' : '';
                                                $action = $isActive ? '' : "onclick=\"handleEnrollmentPagination('goToPage', '{$i}')\"";
                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='hw-40' {$action}>{$i}</button></li>";
                                            }

                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 1) {
                                                    renderPageButton($i, $pageno == $i);
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
                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleEnrollmentPagination('nextPage',null,'<?= $number_of_pages ?>')">
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


?>