<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
    if (!(SessionUser::ifUserIsPro($sessionUserRole) ||
        UserPermissions::hasPermission('leads', 1)
        || UserPermissions::hasPermission('leads', 10) ||  UserPermissions::hasPermission('leads', 2) || UserPermissions::hasPermission('leads', 11))) {
        header("Location: ./dashboard");
    } else {

        $d = new DateTime();
        $tz = new DateTimeZone("Asia/Colombo");
        $d->setTimezone($tz);
        $date = $d->format("Y-m-d H:i:s");

        $query = "
        SELECT 
            leads.*,
            lead_status.*,
            users.*,
            programmes.*,
            lead_source.*,
            institutes.*,
            institute_has_branches.*,
            `leads`.`lead_id` AS 'nid',
             `leads`.`name` AS 'full_name',
              `lead_status`.`lead_status` AS 'new_status',
              `users`.`user_id` AS `uid`,
              `leads`.`date_added` AS 'lead_date_added'
        FROM `leads`
        INNER JOIN `lead_status` 
            ON leads.lead_status_lead_status_id = lead_status.lead_status_id
        LEFT JOIN `lead_source` 
            ON leads.lead_source_lead_source_id = lead_source.lead_source_id
        LEFT JOIN `institutes` 
            ON leads.institutes_institute_id = institutes.institute_id
        LEFT JOIN `institute_has_branches` 
            ON institutes.institute_id = institute_has_branches.institutes_institute_id
        LEFT JOIN `users` 
            ON leads.users_user_id = users.user_id
        LEFT JOIN `programmes` ON `leads`.`programmes_programme_id` = `programmes`.`programme_id`
      WHERE `leads`.`lead_status_lead_status_id` = '1'
     
    ";

        if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
            $query .= " AND (`leads`.`users_user_id` = '" . $sessionUserObject['user_id'] . "' 
            OR (`leads`.`users_user_id` IS NULL AND `leads`.`added_by` = '" . $sessionUserObject['user_id'] . "'))";
        }

        $query .= " GROUP BY `leads`.`lead_id` ORDER BY `leads`.`lead_id` DESC";

        $results = Database::search($query);

?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>New Leads - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">

            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden" onload="initializeLeads()">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "includes/side-menu.php";
                        load_side_menu::load("new-leads"); ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                        <div class="row p-0 m-0 g-0 h-100">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("new-leads");
                                ?>
                            </div>

                            <div class="col-12 p-4 w-100 m-0" id="skeleton-list">
                                <?php
                                include "./includes/components/skeleton-header.php";
                                include "./includes/components/skeleton-table.php";
                                ?>
                            </div>

                            <div class="col-12 d-flex flex-column contentBodyArea vh-100 d-none" id="real-list">
                                <div class="row p-4 m-0 g-0">
                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                        <div class="row p-0 m-0 g-0 align-content-center">
                                            <div class="col-12 col-lg-8">
                                                <div class="row">
                                                    <span class="main-topic lh-lg">New Leads</span>
                                                    <span class="text-14 sub-text-color lh-base mt-1"> Call and follow up on leads to strengthen client engagement and drive opportunities.</span>
                                                </div>
                                            </div>
                                            <?php
                                            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 2)) {
                                            ?>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row gx-3 align-content-center justify-content-end">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <div class="dropdown w-100">
                                                                <button class="custom-btn w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" data-i18n="action" aria-expanded="false">
                                                                    Action
                                                                </button>
                                                                <ul class="dropdown-menu dropdown-menu-end w-100" style="min-width: max-content;">
                                                                    <li><a class="dropdown-item" href="add-new-lead" data-i18n="">Add a New Lead</a></li>
                                                                    <li><a class="dropdown-item" href="upload-lead-bulk" data-i18n="">Add New Lead Bulk</a></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-12 p-4 component-theme">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 mb-4">
                                                <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                    <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                        <div class="d-flex custom-div align-items-center justify-content-center">
                                                            <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows ?> New Leads</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-9 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="darkColor px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text-color form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleNewLeadsPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                            <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                        </button>
                                                    </div>
                                                    <?php
                                                    if (!SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                                    ?>
                                                        <div class="collapse" id="studentsFilter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" aria-label="Default select example" id="agent-selector" onchange="handleNewLeadsPagination('filter')" <?= SessionUser::ifUserIsPro($sessionUserRole) ? '' : 'disabled' ?>>
                                                                                <option selected value="0">- Select Agent -</option>
                                                                                <?php
                                                                                $user_roles = Database::search("SELECT * FROM `users` WHERE `user_roles_user_role_id` IN (7,8,11,12) AND `status_status_id` = 1");
                                                                                while ($data = $user_roles->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?php echo $data["user_id"]; ?>"><?php echo $data["user_fname"] . " " . $data["user_lname"]; ?></option>
                                                                                <?php
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>


                                                    <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                        <label for="" class="pe-2 sub-text-color">Results Per Page</label>
                                                        <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleNewLeadsPagination('filter')">
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
                                                    <div class="overflow-x-auto p-0 m-0 g-0" id="leads-table-container">
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
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Added</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Lead Source</th>

                                                                    <?php
                                                                    if (!SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                                                    ?>
                                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assigned Counselor</th>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php
                                                                $pageno = 1;
                                                                $results_count = $results->num_rows;
                                                                $result_per_page = 10;  // Consistent variable name

                                                                if ($results_count > $result_per_page) {
                                                                    $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                                } else {
                                                                    $number_of_pages = 1;
                                                                }

                                                                $page_results = ($pageno - 1) * $result_per_page;
                                                                $users = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                if ($users->num_rows > 0) {
                                                                    while ($data = $users->fetch_assoc()) {
                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer position-relative ps-3">
                                                                                        <span class="text-14 main-text ms-2">#<?php echo $data["nid"]; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </td>

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["full_name"]; ?></span></td>
                                                                            <td class="py-3 align-middle">
                                                                                <span class="text-14 main-text"><?php echo $data["contact"]; ?></span>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["programme"]; ?></span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <span class="text-14 main-text">
                                                                                    <?php
                                                                                    if (!empty($data["lead_date_added"])) {
                                                                                        echo (new DateTime($data["lead_date_added"]))->format('Y-m-d h:i A');
                                                                                    } else {
                                                                                        echo "-"; // or "N/A"
                                                                                    }
                                                                                    ?>
                                                                                </span>
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

                                                                            <?php
                                                                            if (!SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
                                                                            ?>
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text"><?= $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                </td>
                                                                            <?php
                                                                            }
                                                                            ?>
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
                                                                                        if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 11)) {
                                                                                        ?>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" onclick='deleteLead(<?= $data["nid"] ?>)'>
                                                                                                    <iconify-icon icon="material-symbols:delete-outline" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                    Delete</a></li>
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
                                                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme Previous-button" onclick="handleNewLeadsPagination('previousPage')">
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
                                                                                $action = $isActive ? '' : "onclick=\"handleNewLeadsPagination('goToPage', '{$i}')\"";
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
                                                                    <button class="col-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleNewLeadsPagination('nextPage',null,'<?= $number_of_pages ?>')">
                                                                        <span class="text-14 main-text fw-semibold">Next</span>
                                                                        <iconify-icon icon="tabler:arrow-right" width="20" height="20" class="darkColor px-1"></iconify-icon>
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
                                <div class="flex-grow-1"></div>
                                <footer>
                                    <?php include './includes/footer.php' ?>
                                </footer>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lead Add Model => Start -->
            <div class="modal fade br-0" id="addLead" aria-hidden="true" aria-labelledby="addLeadLabel" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h5 class="modal-title">Add Lead</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 mt-3">
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="name" class="form-label required">Full Name</label>
                                        <input type="text" class="form-control custom-select" id="name">
                                        <span class="text-12 text-dark-red" id="name-feedback"></span>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="aid" class="form-label required">Assign an Agent</label>
                                        <select class="form-select custom-select" id="aid">
                                            <option value="0">- Please Select -</option>
                                            <?php
                                            $user = Database::search("SELECT * FROM `users` WHERE `user_roles_user_role_id` = '7' AND `status_status_id` = '1'");
                                            if ($user->num_rows > 0) {
                                                while ($row = $user->fetch_assoc()) {
                                            ?>
                                                    <option value="<?php echo ($row["user_id"]); ?>"><?php echo ($row["user_fname"] . " " . $row["user_lname"]); ?> </option>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </select>
                                        <span class="text-12 text-dark-red" id="aid-feedback"></span>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="cnumber" class="form-label required">Contact Number</label>
                                        <input type="text" class="form-control custom-select" id="cnumber">
                                        <span class="text-12 text-dark-red" id="cnumber-feedback"></span>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="wnumber" class="form-label required">WhatsApp Number</label>
                                        <input type="text" class="form-control custom-select" id="wnumber">
                                        <span class="text-12 text-dark-red" id="wnumber-feedback"></span>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label for="programme" class="form-label required">Programme</label>
                                        <select class="form-select custom-select" id="programme-selector">
                                            <option value="0">- Select -</option>
                                            <?php
                                            $programme = Database::search("SELECT * FROM `programmes` ORDER BY `programme_id` DESC");
                                            if ($programme->num_rows > 0) {
                                                while ($row = $programme->fetch_assoc()) {
                                            ?>
                                                    <option value="<?php echo ($row["programme_id"]); ?>"><?php echo ($row["programme"]); ?></option>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </select>
                                        <span class="text-12 text-dark-red" id="programme-feedback"></span>
                                    </div>
                                </div>

                            </div>
                        </div>
                        <div class="modal-footer p-0 mt-4 border-0">
                            <button class="col-8 col-md-4 custom-btn m-0" onclick="addLead(this)">Add Lead</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Lead Add Model => End -->

            <!-- Lead edit Model => Start -->
            <div class="modal fade br-0" id="editLead" aria-hidden="true" aria-labelledby="editLeadLabel" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h5 class="modal-title">Edit Lead</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 mt-3">
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="name" class="form-label required">Full Name</label>
                                        <input type="text" class="form-control custom-select" id="edit-name">
                                        <div id="edit-name-feedback"></div>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="aid" class="form-label required">Assign an Agent</label>
                                        <select class="form-select custom-select" id="edit-aid">
                                            <option value="0">- Please Select -</option>
                                            <?php
                                            $user = Database::search("SELECT * FROM `users` WHERE `user_roles_user_role_id` = '7' AND `status_status_id` = '1'");
                                            if ($user->num_rows > 0) {
                                                while ($row = $user->fetch_assoc()) {
                                            ?>
                                                    <option value="<?php echo ($row["user_id"]); ?>"><?php echo ($row["user_fname"] . " " . $row["user_lname"]); ?> </option>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div id="edit-aid-feedback"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="cnumber" class="form-label required">Contact Number</label>
                                        <input type="text" class="form-control custom-select" id="edit-cnumber">
                                        <div id="edit-cnumber-feedback"></div>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="wnumber" class="form-label required">WhatsApp Number</label>
                                        <input type="text" class="form-control custom-select" id="edit-wnumber">
                                        <div id="edit-wnumber-feedback"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="programme" class="form-label required">Programme</label>
                                        <select class="form-select custom-select" id="edit-programme">
                                            <option value="0">- Please Select -</option>
                                            <?php
                                            $programme = Database::search("SELECT * FROM `programmes` ORDER BY `programme_id` DESC LIMIT 5");
                                            if ($programme->num_rows > 0) {
                                                while ($row = $programme->fetch_assoc()) {
                                            ?>
                                                    <option value="<?php echo ($row["programme_id"]); ?>"><?php echo ($row["programme"]); ?></option>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div id="edit-programme-feedback"></div>
                                    </div>

                                </div>

                            </div>
                        </div>
                        <div class="modal-footer p-0 mt-4 border-0">
                            <button class="col-8 col-md-4 custom-btn m-0" id="edit-lead-btn">Save Lead</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Lead edit Model => End -->

            <!-- Lead Bulk Upload Model => Start -->
            <div class="modal fade br-0" id="leadBulkUpload" aria-hidden="true" aria-labelledby="leadBulkUploadLabel" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h1 class="modal-title text-16 darkColor" id="leadBulkUploadLabel">Add Lead Bulk</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 mt-3">
                            <div class="col-12 container-fluid">
                                <div class="row">
                                    <div class="col-12 p-0 component-theme mb-4">
                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                            <div class="col-12 d-flex flex-column">
                                                <div class="p-2 d-flex flex-column bl-solid align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                    <span>Drag & Drop your files here</span>
                                                    <span>OR</span>
                                                    <div class="col-12 mt-3">
                                                        <div class="row gap-3 align-items-center justify-content-center">
                                                            <button class="custom-btn px-4 col-4" onclick="downloadLeadFormatCSV()">Download CSV Format</button>
                                                            <button class="custom-btn px-4 col-4" id="file-selector">Browse Files</button>
                                                        </div>
                                                    </div>
                                                    <input type="file" class="d-none" id="file-selector-input" accept=".csv">
                                                    <div class="col-12 px-1 mt-4">
                                                        <div class="row" id="list-section">
                                                        </div>
                                                    </div>
                                                </div>
                                                <span class="text-12 text-dark-red" id="csv-file-feedback"></span>
                                            </div>
                                            <!-- File upload input field for PDF, Word, and PowerPoint files -->
                                            <input type="file" id="file-input2" class="d-none" accept=".csv">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-0 m-0 border-0">
                            <button class="col-8 col-md-4 custom-btn m-0" onclick="uploadLeadBulk(this);">Upload</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Modal -->
            <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content modern-modal">
                        <button type="button" class="close-btn" data-bs-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                        <div class="modal-body text-center">
                            <div class="modal-icon">
                                <iconify-icon icon="bx:error-circle" width="42" height="42" class="modal-alert-icon"></iconify-icon>
                            </div>
                            <h5 class="modal-title mt-3 mb-2">Are you sure?</h5>
                            <p class="modal-text">This action can’t be undone. Please confirm if you want to proceed.</p>
                            <div class="modal-actions mt-4">
                                <button class="btn btn-outline-secondary br-0" id="cancelBtn" data-bs-dismiss="modal">Cancel</button>
                                <button class="table-btn table-btn-dark-theme active border-0" id="confirmBtn">Confirm</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
        </body>

        </html>
<?php

    }
}
?>