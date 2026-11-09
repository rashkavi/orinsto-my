<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
            $content = trim(file_get_contents('php://input'));
            $decoded = json_decode($content, true);
            if (json_last_error() == JSON_ERROR_NONE) {

                $intakeId = isset($decoded['id']) ? $decoded['id'] : 0;
                $plan = isset($decoded['plan']) ? $decoded['plan'] : 0;
                $category = isset($decoded['category']) ? $decoded['category'] : 0;

                if ($intakeId == 0) {
                    $status->intake = "Please select intake";
                } else {
                    $isValidIntake = Database::search("SELECT * FROM `intakes` WHERE `intake_id` = '" . $intakeId . "'");
                    if ($isValidIntake->num_rows == 0) {
                        $status->intake = "Invalid Intake Id";
                    } else {
?>
                        <div class="col-12 cumponent-theme p-4">
                            <div class="row p-0 m-0">
                                <div class="col-12 p-0">
                                    <div class="row p-0 gy-3 gx-3 align-content-center justify-content-end">
                                        <div class="col-4 col-md-2 d-flex flex-column justify-content-start">
                                            <div class="d-flex custom-div align-items-center justify-content-center">
                                                <?php
                                                $studentCount = Database::search("
                                                                SELECT COUNT(DISTINCT `student_id`) AS unique_student_count 
                                                                FROM `students_has_intakes`
                                                                INNER JOIN `payment_plan_has_students_has_intakes`
                                                                ON `students_has_intakes`.`students_has_intakes_id` = `payment_plan_has_students_has_intakes`.`students_has_intakes_students_has_intakes_id`
                                                                WHERE `intakes_intake_id` = '" . $intakeId . "' 
                                                                AND `payment_category_payment_category_id` = '" . $category . "'
                                                                AND `payment_plan_payment_plan_id` = '" . $plan . "'
                                                                
                                                            ");

                                                $data = $studentCount->fetch_assoc();
                                                ?>
                                                <span class="text-14 dark-theme fw-medium"><?= $data["unique_student_count"]; ?> Students</span>
                                            </div>
                                        </div>
                                        <div class="col-8 col-md-8 col-lg-9 d-flex flex-column">
                                            <div class="h-100 d-flex align-items-center p-1 bl-solid">
                                                <iconify-icon icon="teenyicons:search-outline" width="20" height="20" class="text-dark-color px-3 pe-3 pt-2 pb-2"></iconify-icon>
                                                <input type="text" class="h-100 sub-text form-control border-0 shadow-none" placeholder="What do you want to search..." id="search-text">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4 col-lg-1 d-flex flex-column justify-content-end">
                                            <button class="custom-btn w-100" type="button">Search</button>
                                        </div>

                                        <div class="col-6 col-md-4 col-lg-3 d-flex align-items-center justify-content-end">
                                            <label for="" class="pe-2 sub-text">Results Per Page</label>
                                            <select class="form-select custom-select w-25" aria-label="Default select example" id="show-result-selector">
                                                <option selected value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                                <option value="250">250</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 mt-3 p-0">
                                    <div class="row p-0 m-0 g-0 align-content-center">
                                        <div class="overflow-x-auto bg-table-header p-0 m-0 g-0" id="students-table-container">
                                            <table class="table bl-solid m-0 p-0 g-0" id="students-table">
                                                <thead>
                                                    <tr class="br-t-5">
                                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                            <div class="d-flex flex-row align-items-center">
                                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                <span class="text-14 main-text fw-medium mx-2">Student Name</span>
                                                            </div>
                                                        </th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Student Id</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Amount</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Discount</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Setteled Amount</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Pending Amount</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Due Amount</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Last Payment Month</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium">Last Payment Status</th>
                                                        <th scope="col" class="py-3 bg-table-header text-14 main-text fw-medium"></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="user-table-data">
                                                    <?php
                                                    $query = "
                                                                    SELECT 
                                                                `pphsi`.`payment_plan_has_students_has_intakes_id` AS `pphshiId`, 
                                                                `shi`.`students_has_intakes_id` AS `shiId`, 
                                                                `shi`.`student_id`,
                                                                `ld`.`first_name`, 
                                                                `ld`.`last_name`, 
                                                                `s`.`profile_img`, 
                                                                `t`.`title`,
                                                                `pphsi`.`amount`, 
                                                                `pphsi`.`discount`,
                                                                IFNULL(SUM(`cp`.`discount`), 0) AS `total_paid_discount`,
                                                                IFNULL(SUM(CASE WHEN `cp`.`payment_status_payment_status_id` BETWEEN 1 AND 7 THEN `cp`.`amount` ELSE 0 END), 0) AS `pending_payments`,
                                                                IFNULL(SUM(CASE WHEN `cp`.`payment_status_payment_status_id` = 8 THEN `cp`.`amount` ELSE 0 END), 0) AS `approve_payments`,
                                                                                                                                
                                                                IFNULL((
                                                                    SELECT MONTHNAME(`cp2`.`date_paid`) 
                                                                    FROM `course_payments` AS `cp2`
                                                                    WHERE `cp2`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                                    ORDER BY `cp2`.`date_paid` DESC LIMIT 1
                                                                ), '-') AS `last_payment_month`,

                                                                IFNULL((
                                                                    SELECT `ps`.`payment_status_id` 
                                                                    FROM `course_payments` AS `cp2`
                                                                    LEFT JOIN `payment_status` AS `ps` 
                                                                    ON `cp2`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                    WHERE `cp2`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                                    ORDER BY `cp2`.`course_payments_id` DESC LIMIT 1
                                                                ), '0') AS `last_payment_status_id`,
                                                                
                                                                IFNULL((
                                                                    SELECT `ps`.`payment_status` 
                                                                    FROM `course_payments` AS `cp2`
                                                                    LEFT JOIN `payment_status` AS `ps` 
                                                                    ON `cp2`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                    WHERE `cp2`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                                    ORDER BY `cp2`.`course_payments_id` DESC LIMIT 1
                                                                ), 'Not Added Any Payment') AS `last_payment_status`

                                                            FROM 
                                                                `payment_plan_has_students_has_intakes` AS `pphsi`
                                                            INNER JOIN 
                                                                `students_has_intakes` AS `shi`
                                                                ON `pphsi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                                                            INNER JOIN 
                                                                `students` AS `s`
                                                                ON `shi`.`students_student_id` = `s`.`student_id`
                                                            INNER JOIN 
                                                                        `login_details` AS `ld`
                                                                        ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                                                            INNER JOIN 
                                                                `intakes` AS `i`
                                                                ON `shi`.`intakes_intake_id` = `i`.`intake_id`
                                                            INNER JOIN 
                                                                `title` AS `t`
                                                                ON `s`.`title_title_id` = `t`.`title_id`
                                                            LEFT JOIN 
                                                                `course_payments` AS `cp` 
                                                                ON `pphsi`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`

                                                            WHERE 
                                                                `shi`.`intakes_intake_id` = '" . $intakeId . "'
                                                               AND `pphsi`.`payment_category_payment_category_id` = '" . $category . "'
                                                                AND `pphsi`.`payment_plan_payment_plan_id` = '" . $plan . "'
                                                            GROUP BY 
                                                                `pphsi`.`payment_plan_has_students_has_intakes_id`, 
                                                                `shi`.`student_id` 
                                                                ORDER BY 
                                                                `pphsi`.`payment_plan_has_students_has_intakes_id` DESC
                                                                ";

                                                    $results = Database::search($query);
                                                    $pageno = 1;
                                                    $results_count = $results->num_rows;
                                                    $result_per_page = 10;  // Consistent variable name

                                                    if ($results_count > $result_per_page) {
                                                        $number_of_pages = ceil($results_count / $result_per_page);  // Corrected variable name
                                                    } else {
                                                        $number_of_pages = 1;
                                                    }

                                                    $page_results = ($pageno - 1) * $result_per_page;
                                                    $students = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                                    if ($students->num_rows > 0) {
                                                        while ($data = $students->fetch_assoc()) {
                                                            $duePayment = $data["amount"] - $data["discount"] - $data["total_paid_discount"] - $data["approve_payments"];

                                                    ?>
                                                            <tr>
                                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?= $data["pphshiId"]; ?>" onchange="checkRowEvents(<?= $data['pphshiId']; ?>);">
                                                                        <?php
                                                                        if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                                                        ?>
                                                                            <img src="./assets/imgs/students/<?= $data["profile_img"]; ?>" class="hwr-40" alt="Students image">
                                                                        <?php
                                                                        } else {
                                                                            $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                                            $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                                                        ?>
                                                                            <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $firstNameInitial . $lastNameInitial; ?></div>
                                                                        <?php
                                                                        }
                                                                        ?>
                                                                        <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["first_name"] . " " . $data["last_name"]; ?></span>
                                                                    </div>
                                                                </td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["student_id"]; ?></span></td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["amount"] ?></span></td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["discount"] + $data["total_paid_discount"]; ?></span></td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["approve_payments"]; ?></span></td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $data["pending_payments"]; ?></span></td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text">LKR <?= $duePayment; ?></span></td>
                                                                <td class="py-2 align-middle"><span class="text-14 main-text"><?= $data["last_payment_month"]; ?></span></td>

                                                                <td class="pt-3 pb-3 align-middle">
                                                                    <?php
                                                                    $status_classes = [
                                                                        "0"     => "table-btn-purple",
                                                                        "1"  => "table-btn-yellow",
                                                                        "2"  => "table-btn-orange",
                                                                        "3"  => "table-btn-success",
                                                                        "4"  => "table-btn-danger",
                                                                        "5"  => "table-btn-orange",
                                                                        "6"  => "table-btn-success",
                                                                        "7"  => "table-btn-danger",
                                                                        "8"  => "table-btn-success",
                                                                    ];
                                                                    $sbutton_class = $status_classes[$data["last_payment_status_id"]];
                                                                    ?>
                                                                    <button class="table-btn <?= $sbutton_class; ?> w-100"> <?= $data["last_payment_status"]; ?></button>
                                                                </td>
                                                                <td class="py-3 align-middle">
                                                                    <div class="dropdown">
                                                                        <iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" id="dropdownMenuButton2" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>
                                                                        <ul class="dropdown-menu border-0 shadow w-100 br-5" aria-labelledby="dropdownMenuButton2">
                                                                            <li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="intake-student-payments?student-id=<?= $data['shiId']; ?>">
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
                                                        <!-- Previous Button (col-1) -->
                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme previous-button" onclick="handleStudentsPagination('previousPage');">
                                                            <iconify-icon icon="tabler:arrow-left" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                                            <span class="text-14 main-text fw-semibold">Previous</span>
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
                                                                    $action = $isActive ? '' : "onclick=\"handleStudentsPagination('goToPage', '{$i}')\"";
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
                                                        <!-- Next Button (col-1) -->
                                                        <button class="col-2 col-lg-1 p-2 bl-solid br-8 d-flex align-items-center justify-content-center component-theme next-button" onclick="handleStudentsPagination('nextPage',<?= $number_of_pages ?>);">
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
<?php
                    }
                }
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error') || property_exists($status, 'programme')) {
    echo (json_encode($status));
}
