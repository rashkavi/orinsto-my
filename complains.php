<?php
require_once './includes/UserClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null && $sessionUserRole == "Developer") {
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        $d = new DateTime();
        $tz = new DateTimeZone("Asia/Colombo");
        $d->setTimezone($tz);
        $date = $d->format("Y-m-d H:i:s");

        $query = "SELECT *, `complains`.`complain_id` AS 'cid' FROM `complains` 
                                                INNER JOIN `users` ON `complains`.`users_user_id` = `users`.`user_id` 
                                                INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`
                                                ";

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || $sessionUserRole == "Developer")) {
            $query .= " AND `added_by` = '" . $sessionUserObject["user_id"] . "'";
        }
        $query .= " ORDER BY `complains`.`complain_id` DESC";

        $results = Database::search($query);

?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Complains - Admin Dashboard | <?= Project::getBaseName() ?></title>

            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
          
            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">

            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
        </head>

        <body class="overflow-hidden">
            <div class="notifications"></div>
            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                <div id="appContainer" class="row p-0">
                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                        <?php
                        include "./includes/side-menu.php";
                        load_side_menu::load("complains"); ?>
                    </div>
                    <div id="app-wrapper" class="app-wrapper p-0 align-content-center m-0">
                        <div class="row p-0 m-0 g-0 h-100">
                            <div class="col-12 p-0 m-0 g-0">
                                <?php
                                include "./includes/header.php";
                                selectItem::setSelected("complains");
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
                                                    <span class="main-topic lh-lg">Complains</span>
                                                    <span class="text-14 sub-text-color lh-base mt-1"> Call and follow up on leads to strengthen client engagement and drive opportunities.</span>
                                                </div>
                                            </div>
                                            <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                <div class="row gx-3 align-content-center justify-content-end">
                                                    <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                        <button class="custom-btn w-100" data-bs-target="#addComplains" data-bs-toggle="modal">Add Complain</button>
                                                    </div>
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
                                                            <span class="text-14 dark-theme fw-medium"><?php echo $results->num_rows ?> Complains</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-8 col-lg-7 d-flex flex-column">
                                                        <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                            <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="darkColor px-3 pe-3 py-2"></iconify-icon>
                                                            <input type="text" class="h-100 sub-text-color form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text" onkeyup="handleNewLeadsPagination('filter')">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-6 col-lg-2 d-flex flex-column justify-content-end">
                                                        <select class="form-select custom-select" aria-label="Default select example" id="agent-selector" onchange="handleNewLeadsPagination('filter')" <?= SessionUser::ifUserIsPro($sessionUserRole) ? '' : 'disabled' ?>>
                                                            <option selected value="0">- Select Agent -</option>
                                                            <?php
                                                            $user_roles = Database::search("SELECT * FROM `users` WHERE `user_roles_user_role_id` = 4");
                                                            while ($data = $user_roles->fetch_assoc()) {
                                                            ?>
                                                                <option value="<?php echo $data["user_id"]; ?>"><?php echo $data["user_fname"] . " " . $data["user_lname"]; ?></option>
                                                            <?php
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                  

                                                    <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                                        <button class="custom-btn w-100" type="button">Search</button>
                                                    </div>
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
                                                                            <span class="text-14 main-text fw-medium mx-2">Id</span>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Intake</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Registration Number</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Name</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Contact Number</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Complain</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Assign To</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Date Added</th>
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
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer position-relative">
                                                                                        <span class="text-14 main-text ms-2">#<?php echo $data["cid"]; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </td>

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["intake"]; ?></span></td>
                                                                            <td class="py-3 align-middle">
                                                                                <span class="text-14 main-text"><?php echo $data["registration_no"]; ?></span>
                                                                            </td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["name"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["contact"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["complain"]; ?></span></td>
                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_role"] . " - " . $data["user_fname"] . "" . $data["user_lname"]; ?></span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <span class="text-14 main-text"><?php echo (new DateTime($data["datetime"]))->format('Y-m-d h:i A') ?></span>
                                                                            </td>
                                                                            <td class="py-3 align-middle">
                                                                                <?php
                                                                                if (SessionUser::ifUserIsPro($sessionUserRole)) {
                                                                                ?>
                                                                                    <div class="dropdown">
                                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text-color cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="complain-view?complain-id=<?= $data['cid']; ?>">
                                                                                                    <iconify-icon icon="carbon:view" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                    View</a></li>
                                                                                            <hr class="mt-1 mb-1 mb-0 me-2 mx-2">
                                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center">
                                                                                                    <iconify-icon icon="ic:sharp-edit" width="20" height="20" class="sub-text-color me-3"></iconify-icon>
                                                                                                    Edit</a></li>
                                                                                        </ul>
                                                                                    </div>
                                                                                <?php
                                                                                } else {
                                                                                ?>
                                                                                    <button class="table-btn table-btn-dark-theme active border-0" onclick="window.location.href = 'lead-view?lead-id=<?= $data['cid']; ?>'">View</button>
                                                                                <?php
                                                                                }
                                                                                ?>

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
            <div class="modal fade br-0" id="addComplains" aria-hidden="true" aria-labelledby="addComplainsLabel" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered br-0">
                    <div class="modal-content p-4">
                        <div class="modal-header p-0 border-0">
                            <h5 class="modal-title">Add Complain</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-0 mt-3">
                            <div class="col-12">
                                <div class="row">
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="intake" class="form-label">Intake</label>
                                        <input type="text" class="form-control custom-select" id="intake">
                                        <div id="intake-feedback"></div>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="rnumber" class="form-label">Registration Number</label>
                                        <input type="text" class="form-control custom-select" id="rnumber">
                                        <div id="rnumber-feedback"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="name" class="form-label">Name</label>
                                        <input type="text" class="form-control custom-select" id="name">
                                        <div id="name-feedback"></div>
                                    </div>
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="cnumber" class="form-label">Contact Number</label>
                                        <input type="number" class="form-control custom-select" id="cnumber">
                                        <div id="cnumber-feedback"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="complain" class="form-label">Complain</label>
                                        <textarea type="text" class="form-control custom-select" id="complain" style="height: 100px;"></textarea>
                                        <div id="complain-feedback"></div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-12 col-lg-6 mb-3">
                                        <label for="agent" class="form-label">Assign an Agent</label>
                                        <select class="form-select custom-select" id="agent">
                                            <option value="0">- Please Select -</option>
                                            <?php
                                            $roles = Database::search("SELECT *, `users`.`user_id` AS 'uid' FROM `users` 
                                            INNER JOIN `user_roles` ON `users`.`user_roles_user_role_id` = `user_roles`.`user_role_id`");
                                            if ($roles->num_rows > 0) {
                                                while ($row = $roles->fetch_assoc()) {
                                            ?>
                                                    <option value="<?php echo ($row["uid"]); ?>"><?php echo ($row["user_role"] . " - " . $row["user_fname"] . " " . $row["user_lname"]); ?> </option>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div id="agent-feedback"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer p-0 mt-4 border-0">
                            <button class="col-8 col-md-4 custom-btn m-0" onclick="addComplain(this)">Add Complain</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Lead Add Model => End -->

       
           
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