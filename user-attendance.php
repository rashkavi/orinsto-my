<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

        try {



            $query = "
        SELECT 
            in_rec.user_in_out_record_id AS check_in_record_id,
            in_rec.date_updated AS check_in_time,
    
            out_rec.date_updated AS check_out_time,
            out_rec.user_in_out_record_id AS check_out_record_id,

            u.user_id,
            u.user_fname,
            u.user_lname,
            u.user_email,
            u.user_mobile,
            u.profile_img,
            u.nic,
            u.fingerprint_employee_no,
    
            title.title,
          
            in_rec.date_updated AS check_in_time,
            out_rec.date_updated AS check_out_time,
    
            in_rec.users_user_id AS check_in_user_id,
            out_rec.users_user_id AS check_out_user_id,
    
            in_rec.check_in_status
    
        FROM user_in_out_records in_rec

        INNER JOIN users u
            ON in_rec.users_user_id = u.user_id
        INNER JOIN title
            ON u.title_title_id = title.title_id
    
        LEFT JOIN user_in_out_records out_rec
            ON out_rec.user_in_out_record_id = (
                SELECT MIN(o.user_in_out_record_id)
                FROM user_in_out_records o
                WHERE o.users_user_id = in_rec.users_user_id
                  AND o.student_in_out_status_student_in_out_status_id = 2
                  AND o.user_in_out_record_id > in_rec.user_in_out_record_id
                  AND o.date_updated >= in_rec.date_updated
            )
            
        WHERE in_rec.student_in_out_status_student_in_out_status_id = 1
        AND DATE(in_rec.date_updated) = CURDATE()
        GROUP BY in_rec.user_in_out_record_id
        ORDER BY in_rec.date_updated DESC
    ";

            $results = Database::search($query);

            $cardData = Database::search("
        SELECT
         (
                SELECT COUNT(DISTINCT users_user_id)
                FROM user_in_out_records
                WHERE student_in_out_status_student_in_out_status_id = 1
                AND DATE(date_updated) = CURDATE()
            ) AS today_attendance,
            (
                SELECT COUNT(*)
                FROM user_in_out_records
                WHERE student_in_out_status_student_in_out_status_id = 1
                AND DATE(date_updated) = CURDATE()
            ) AS today_check_in_count,
    
            (
                SELECT COUNT(*)
                FROM user_in_out_records
                WHERE student_in_out_status_student_in_out_status_id = 2
                AND DATE(date_updated) = CURDATE()
            ) AS today_check_out_count,

            (
                SELECT COUNT(*) FROM users WHERE fingerprint_employee_no IS NOT NULL
            ) AS enrolled_count
            
    ")->fetch_assoc();

            $attendanceCards = [
                [
                    'title' => 'Today Attendance',
                    'value' => number_format((int)$cardData['today_attendance']),
                    'icon' => 'ci:wavy-check',
                    'change' => '',
                    'change_type' => 'success',
                    'period' => 'Users Attended',
                    'card_type' => 'success',
                    'chart' => []
                ],
                [
                    'title' => 'Today Checked IN',
                    'value' => number_format((int)$cardData['today_check_in_count']),
                    'icon' => 'ph:sign-in-bold',
                    'change' => '',
                    'change_type' => 'success',
                    'period' => 'Users Checked In',
                    'card_type' => 'success',
                    'chart' => []
                ],

                [
                    'title' => 'Today Checked OUT',
                    'value' => number_format((int)$cardData['today_check_out_count']),
                    'icon' => 'ph:sign-out-bold',
                    'change' => '',
                    'change_type' => 'primary',
                    'period' => 'Users Checked Out',
                    'card_type' => 'primary',
                    'chart' => []
                ],

                [
                    'title' => 'Currently In Campus',
                    'value' => number_format(
                        (int)$cardData['today_check_in_count'] - (int)$cardData['today_check_out_count']
                    ),
                    'icon' => 'mdi:account-school-outline',
                    'change' => '',
                    'change_type' => 'warning',
                    'period' => 'Still Inside Campus',
                    'card_type' => 'warning',
                    'chart' => []
                ],


            ];
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>User Attendance - Admin Dashboard | <?= Project::getBaseName() ?></title>

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
                            include "includes/side-menu.php";
                            load_side_menu::load("user-attendance");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("user-attendance");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-courses.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">

                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row align-items-center p-0 m-0 g-0">
                                                <div class="col-12 col-md-8">
                                                    <div class="row p-0 m-0 g-0">
                                                        <span class="main-topic lh-lg">Users Attendance</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./attendance.php">Attendance</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Users Attendance</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-4 mt-3 mt-xxl-0 d-none">
                                                    <div class="row gx-3 justify-content-end">
                                                        <div class="col-12 text-end">
                                                            <button class="custom-btn w-100 d-flex align-items-center justify-content-center gap-1" onclick="openFingerprintStatusModal()">
                                                                <iconify-icon icon="mdi:fingerprint" width="18" height="18"></iconify-icon>
                                                                Fingerprint Status
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="container-fluid p-0 mb-4">
                                            <div class="row g-3">
                                                <?php foreach ($attendanceCards as $card) {
                                                ?>
                                                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                                                        <div class="stats-mini-card">
                                                            <div class="stats-mini-top">
                                                                <div class="stats-mini-left">
                                                                    <div class="stats-mini-icon">
                                                                        <iconify-icon icon="<?= htmlspecialchars($card['icon']) ?>" width="16" height="16"></iconify-icon>
                                                                    </div>
                                                                    <span class="stats-mini-title"><?= htmlspecialchars($card['title']) ?></span>
                                                                </div>

                                                                <button type="button" class="stats-mini-menu">
                                                                    <iconify-icon icon="mdi:dots-horizontal" width="16" height="16"></iconify-icon>
                                                                </button>
                                                            </div>

                                                            <div class="stats-mini-body">
                                                                <div class="stats-mini-content">
                                                                    <div class="stats-mini-value"><?= htmlspecialchars($card['value']) ?></div>

                                                                    <div class="stats-mini-meta">
                                                                        <?php if ($card['change'] !== '') { ?>
                                                                            <span class="stats-mini-change <?= htmlspecialchars($card['change_type']) ?>">
                                                                                <?= htmlspecialchars($card['change']) ?>
                                                                            </span>
                                                                        <?php } ?>
                                                                        <span class="stats-mini-period"><?= htmlspecialchars($card['period']) ?></span>
                                                                    </div>
                                                                </div>
                                                                <?php if (!empty($card['chart'])) { ?>
                                                                    <div class="stats-mini-chart" data-chart='<?= json_encode($card['chart']) ?>'></div>
                                                                <?php } ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php } ?>
                                            </div>
                                        </div>


                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 g-0 align-content-center">
                                                <div class="col-12 mb-4">
                                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                                <span class="text-14 dark-theme fw-medium"><?= $results->num_rows ?> Records</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-8 col-md-6 col-lg-9 d-flex flex-column">
                                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="Search user name or email..." data-i18n="[placeholder]search-placeholder" id="search-text" onkeyup="handleUsersAttendancePagination('filter')">
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-2 col-lg-1 d-flex flex-column justify-content-end">
                                                            <button class="custom-btn w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsFilter" aria-expanded="false" aria-controls="studentsFilter">
                                                                <iconify-icon icon="mage:filter" width="26" height="26" class="text-white px-3 pe-3 py-2"></iconify-icon>
                                                            </button>
                                                        </div>
                                                        <div class="collapse" id="studentsFilter">
                                                            <div class="card card-body border-0 p-0">
                                                                <div class="col-12">
                                                                    <div class="row justify-content-end">
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <select class="form-select custom-select" id="month-selector" onchange="handleUsersAttendancePagination('filter')">
                                                                                <option value="0">- Select Month -</option>
                                                                                <?php
                                                                                $currentYear = date("Y");
                                                                                $currentMonth = date("m");

                                                                                for ($m = 1; $m <= $currentMonth; $m++) {

                                                                                    $month = str_pad($m, 2, "0", STR_PAD_LEFT);
                                                                                    $monthName = date("F", strtotime("$currentYear-$month-01"));

                                                                                    $value = $currentYear . "-" . $month;

                                                                                    echo "<option value='$value'>$monthName $currentYear</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>

                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column justify-content-end">
                                                                            <input type="date" class="form-control custom-input" id="date" onchange="handleUsersAttendancePagination('filter')">
                                                                        </div>
                                                                        <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                            <select name="" id="status-selector" class="form-select custom-select mt-2" onchange="handleUsersAttendancePagination('filter')">
                                                                                <option value="0" selected>- Select Status -</option>
                                                                                <option value="1">Not Check Out</option>
                                                                            </select>
                                                                            <span id="status-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6 d-none d-lg-block"></div>
                                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                                            <label for="" class="pe-2 sub-text" data-i18n="results-per-page">Results Per Page</label>
                                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector" onchange="handleUsersAttendancePagination('filter')">
                                                                <option selected value="10">10</option>
                                                                <option value="25">25</option>
                                                                <option value="50">50</option>
                                                                <option value="100">100</option>
                                                                <option value="250">250</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="table-responsive-wrapper">
                                                    <div class="table-responsive" id="users-table-container">
                                                        <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                            <thead>
                                                                <tr class="br-t-5">
                                                                    <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium align-content-center w-25">
                                                                        <div class="d-flex flex-row align-items-center cursor-pointer">
                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                            <div class="d-flex justify-content-between align-items-center w-100" onclick="handleUsersAttendancePagination('sortByName')">
                                                                                <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                <iconify-icon id="sort-icon" icon="flowbite:chevron-sort-outline" width="24" height="24" class="sub-text"></iconify-icon>
                                                                            </div>
                                                                        </div>
                                                                    </th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">User ID</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Check IN Status</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Checked IN Time</th>
                                                                    <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium align-content-center">Checked OUT Time</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="user-table-data">
                                                                <?php

                                                                $pageno = 1;
                                                                $results_count = $results->num_rows;
                                                                $result_per_page = 10;

                                                                if ($results_count > $result_per_page) {
                                                                    $number_of_pages = ceil($results_count / $result_per_page);
                                                                } else {
                                                                    $number_of_pages = 1;
                                                                }

                                                                $page_results = ($pageno - 1) * $result_per_page;
                                                                $user_records = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                                if ($user_records->num_rows > 0) {
                                                                    while ($data = $user_records->fetch_assoc()) {
                                                                ?>
                                                                        <tr>
                                                                            <td class="px-4 py-3 pe-0 align-middle">
                                                                                <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
                                                                                    <div class="flex-grow-1 d-flex align-items-center cursor-pointer" onclick="toggleCheckbox(<?php echo $data['user_id']; ?>)">
                                                                                        <?php
                                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])) {
                                                                                        ?>
                                                                                            <img src="./assets/imgs/users/<?php echo $data["profile_img"]; ?>" class="hwr-40" alt="User image">
                                                                                        <?php
                                                                                        } else {
                                                                                            $firstNameInitial = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                            $lastNameInitial = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                        ?>
                                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . ". " . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            </td>

                                                                            <td class="py-2 align-middle"><span class="text-14 main-text"><?php echo $data["user_id"]; ?></span></td>
                                                                            <td class="py-2 align-middle">
                                                                                <?php
                                                                                $checkInStatus = $data["check_in_status"];

                                                                                // check_in_status only ever holds ON_TIME or LATE_COMING
                                                                                // (or is null for check-out rows, which display as On Time
                                                                                // here since this column has no meaning for check-outs).
                                                                                if ($checkInStatus === 'LATE_COMING') {
                                                                                    $badgeClass = 'table-btn-yellow text-warning';
                                                                                    $icon = 'mdi:clock-alert-outline';
                                                                                    $label = 'Late Coming';
                                                                                } else {
                                                                                    $badgeClass = 'table-btn-success';
                                                                                    $icon = 'mdi:check-circle-outline';
                                                                                    $label = 'On Time';
                                                                                }
                                                                                ?>

                                                                                <span class="table-btn <?php echo $badgeClass; ?> br-0 w-100 py-2 d-inline-flex align-items-center justify-content-center gap-1 fw-normal">
                                                                                    <iconify-icon icon="<?php echo $icon; ?>" width="14"></iconify-icon>
                                                                                    <?php echo $label; ?>
                                                                                </span>
                                                                            </td>

                                                                            <td class="py-2 align-middle">
                                                                                <label class="table-btn table-btn-success text-center w-100"> <?php echo date("Y-m-d h:i A", strtotime($data["check_in_time"])); ?></label>
                                                                            </td>

                                                                            <?php
                                                                            if (!empty($data['check_out_time'])) {
                                                                            ?>
                                                                                <td class="py-2 align-middle">
                                                                                    <label class="table-btn table-btn-danger text-center w-100"> <?php echo date("Y-m-d h:i A", strtotime($data["check_out_time"])); ?></label>
                                                                                </td>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <td class="py-2 align-middle">
                                                                                    <span class="text-14 main-text">N/A</span>
                                                                                </td>
                                                                            <?php
                                                                            }
                                                                            ?>

                                                                        </tr>
                                                                    <?php
                                                                    }
                                                                } else {
                                                                    ?>
                                                                    <tr class="justify-content-center align-middle">
                                                                        <td colspan="6" class="align-middle justify-content-center">
                                                                            <div class="d-flex flex-column align-items-center">
                                                                                <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
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
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleUsersAttendancePagination('previousPage');">
                                                                        <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                                        <span class="text-14 main-text fw-semibold">Previous</span>
                                                                    </button>
                                                                    <div class="col-8 col-lg-10 custom-pagination d-flex justify-content-center">
                                                                        <ul class="pagination-list d-flex mb-0">
                                                                            <?php
                                                                            $shudderPages1 = false;
                                                                            $shudderPages2 = false;

                                                                            function renderPageButton($i, $isActive = false)
                                                                            {
                                                                                $activeClass = $isActive ? 'active' : '';
                                                                                $action = $isActive ? '' : "onclick=\"handleUsersAttendancePagination('goToPage', '{$i}')\"";
                                                                                echo "<li class='page-item {$activeClass}' id='page-{$i}'><button class='py-2 px-3' {$action}>{$i}</button></li>";
                                                                            }

                                                                            for ($i = 1; $i <= $number_of_pages; $i++) {
                                                                                if ($i == 1 || $i == $number_of_pages || abs($pageno - $i) <= 2) {
                                                                                    renderPageButton($i, $pageno == $i);
                                                                                } elseif (!$shudderPages1 && ($i < $pageno - 2 && $i > 1)) {
                                                                                    $shudderPages1 = true;
                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                } elseif (!$shudderPages2 && ($i > $pageno + 2 && $i < $number_of_pages)) {
                                                                                    $shudderPages2 = true;
                                                                                    echo "<li class='d-flex align-items-end h-100'><iconify-icon icon='bi:three-dots' width='20' height='20' class='sub-text m-0 p-0'></iconify-icon></li>";
                                                                                }
                                                                            }
                                                                            ?>

                                                                        </ul>
                                                                    </div>
                                                                    <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleUsersAttendancePagination('nextPage',<?= $number_of_pages ?>);">
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

                    <!-- Fingerprint device status modal.
                     Marking happens entirely server-side via the terminal
                     pushing to markAttendance.php - this modal is purely
                     informational, it never triggers marking itself. -->
                    <div class="modal fade" id="fingerprintStatusModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content p-4">
                                <div class="modal-header border-0 p-0">
                                    <h6 class="modal-title d-flex align-items-center gap-2">
                                        <iconify-icon icon="mdi:fingerprint" width="22" height="22"></iconify-icon>
                                        Fingerprint Terminal Status
                                    </h6>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body p-0 mt-3">
                                    <p class="text-14 sub-text mb-2">
                                        Staff scan their finger directly on the terminal - no action needed here.
                                        The device pushes attendance to this system automatically.
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center py-2">
                                        <span class="text-14 main-text">Staff enrolled on device</span>
                                        <span class="fw-semibold"><?= number_format((int)$cardData['enrolled_count']) ?></span>
                                    </div>
                                    <button class="custom-btn w-100 mt-3" onclick="location.reload()">Refresh List</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script id="c6z7wq">
                    // Marking is fully automatic (device pushes to markAttendance.php).
                    // This modal is display-only.
                    function openFingerprintStatusModal() {
                        const modalEl = document.getElementById('fingerprintStatusModal');
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    }
                </script>

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/InfoTooltipManager.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/fileChooser.js"></script>

            </body>

            </html>
<?php
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}
?>