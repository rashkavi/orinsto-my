<?php
class AcademicDashboard
{
    public static function loadContent()
    {
        $sessionUserRole = SessionUser::getCurrentUserRole();
        $sessionUserObject = SessionUser::getCurrentUserObject();

        if (SessionUser::ifUserIsAcademicPro($sessionUserRole)) {
         
            $timezone = new DateTimeZone("Asia/Colombo");
            $dateTime = new DateTime("now", $timezone);
            $today = $dateTime->format('Y-m-d');
            $todayMonth = $dateTime->format('m');
            $todayDay = $dateTime->format('d');
?>
            <div class="container p-0 g-0">
                <div class="row g-4">
                    <div class="col-12 col-lg-6 d-flex">
                        <div class="d-flex flex-column component-theme p-4 w-100">
                            <div class="d-flex flex-row justify-content-between">
                                <div class="d-flex flex-column">
                                    <span class="sub-topic" data-i18n="today-users-birthdays">Today's Users Birthdays</span>
                                </div>
                                <div class="d-flex">
                                    <a class="text-decoration-none sub-text" href="users-birthdays" data-i18n="view-all">View All</a>
                                </div>
                            </div>
                            <?php
                            $todayBirthdays = Database::search("SELECT `title`.`title`, `users`.`user_fname`, `users`.`user_lname`, `users`.`user_id`, `users`.`profile_img` FROM `users`
                            INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id` 
                            WHERE MONTH(`dob`) = '" . $todayMonth . "' AND DAY(`dob`) = '" . $todayDay . "' ORDER BY `users`.`user_id` ASC LIMIT 5");
                            if ($todayBirthdays->num_rows > 0) {
                                while ($todayBirthday = $todayBirthdays->fetch_assoc()) {
                            ?>
                                    <div class="d-flex flex-column p-2">
                                        <div class="d-flex p-2">
                                            <?php
                                            if (!empty($todayBirthday["profile_img"]) && file_exists('./assets/imgs/users/' . $todayBirthday["profile_img"])) {
                                            ?>
                                                <img src="./assets/imgs/users/<?php echo $todayBirthday["profile_img"]; ?>" class="hwr-50" alt="Students image">
                                            <?php
                                            } else {
                                                $firstNameInitial = strtoupper(substr($todayBirthday["user_fname"], 0, 1));
                                                $lastNameInitial = strtoupper(substr($todayBirthday["user_lname"], 0, 1));
                                            ?>
                                                <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                            <?php
                                            }
                                            ?>
                                            <div class="d-flex flex-column mx-4 flex-grow-1">
                                                <span class="text-14 text-dark-color fw-medium lh-lg"><?= $todayBirthday['title'] . "." . $todayBirthday['user_fname'] . " " . $todayBirthday['user_lname'] ?></span>

                                                <div class="d-flex align-items-center">
                                                    <iconify-icon icon="fa:birthday-cake" width="14" height="14" class="sub-text"></iconify-icon>
                                                    <span class="text-14 sub-text mx-1">Celebrating today!</span>
                                                </div>
                                            </div>
                                            <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                <iconify-icon icon="iconamoon:email-duotone" width="18" height="18" class="dark-color"></iconify-icon>
                                            </div>
                                            <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium mx-2 me-0">
                                                <iconify-icon icon="la:gift" width="18" height="18" class="dark-color"></iconify-icon>
                                            </div>

                                        </div>
                                        <hr class="sub-text m-0">
                                    </div>
                                <?php
                                }
                            } else {
                                ?>
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                        <div class="no-data-state">
                                            <div class="no-data-icon-wrap">
                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                            </div>
                                            <p class="no-data-text">No data</p>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                        </div>
                    </div>
                    <div class="col-12 col-lg-6 d-flex">
                        <div class="d-flex flex-column component-theme p-4 w-100">
                            <div class="d-flex flex-row justify-content-between">
                                <div class="d-flex flex-column">
                                    <span class="sub-topic" data-i18n="today-students-birthdays">Today's Students Birthdays</span>
                                </div>
                                <div class="d-flex">
                                    <a class="text-decoration-none sub-text" href="users-birthdays" data-i18n="view-all">View All</a>
                                </div>
                            </div>
                            <?php
                            $todayStudentsBirthdays = Database::search("SELECT `title`.`title`, `login_details`.`first_name`, `login_details`.`last_name`, `students`.`student_id`, `students`.`profile_img` FROM `students`
                            INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id` 
                            WHERE MONTH(`date_of_birth`) = '" . $todayMonth . "' AND DAY(`date_of_birth`) = '" . $todayDay . "' ORDER BY `students`.`student_id` ASC LIMIT 5");
                            if ($todayStudentsBirthdays->num_rows > 0) {
                                while ($todayBirthday = $todayStudentsBirthdays->fetch_assoc()) {
                            ?>
                                    <div class="d-flex flex-column p-2">
                                        <div class="d-flex p-2">
                                            <?php
                                            if (!empty($todayBirthday["profile_img"]) && file_exists('./assets/imgs/students/' . $todayBirthday["profile_img"])) {
                                            ?>
                                                <img src="./assets/imgs/students/<?php echo $todayBirthday["profile_img"]; ?>" class="hwr-50" alt="Students image">
                                            <?php
                                            } else {
                                                $firstNameInitial = strtoupper(substr($todayBirthday["first_name"], 0, 1));
                                                $lastNameInitial = strtoupper(substr($todayBirthday["last_name"], 0, 1));
                                            ?>
                                                <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                            <?php
                                            }
                                            ?>
                                            <div class="d-flex flex-column mx-4 flex-grow-1">
                                                <span class="text-14 text-dark-color fw-medium lh-lg"><?= $todayBirthday['title'] . "." . $todayBirthday['first_name'] . " " . $todayBirthday['last_name'] ?></span>

                                                <div class="d-flex align-items-center">
                                                    <iconify-icon icon="fa:birthday-cake" width="14" height="14" class="sub-text"></iconify-icon>
                                                    <span class="text-14 sub-text mx-1">Celebrating today!</span>
                                                </div>
                                            </div>
                                            <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium">
                                                <iconify-icon icon="iconamoon:email-duotone" width="18" height="18" class="dark-color"></iconify-icon>
                                            </div>
                                            <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium mx-2 me-0">
                                                <iconify-icon icon="la:gift" width="18" height="18" class="dark-color"></iconify-icon>
                                            </div>

                                        </div>
                                        <hr class="sub-text m-0">
                                    </div>
                                <?php
                                }
                            } else {
                                ?>
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                        <div class="no-data-state">
                                            <div class="no-data-icon-wrap">
                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                            </div>
                                            <p class="no-data-text">No data</p>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }
                            ?>

                        </div>
                    </div>
                </div>
            </div>

            <div class="container p-0 mt-4 g-0">
                <div class="row g-4">
                    <div class="col-12 col-lg-8 d-flex">
                        <div class="d-flex flex-column component-theme p-4 w-100">
                            <div class="d-flex flex-row">
                                <div class="col-8 d-flex flex-column">
                                    <span class="sub-topic" data-i18n="student-engagements">Students Engagements</span>
                                </div>
                                <div class="col-4 d-flex flex-column">
                                    <select id="student-engagements-condition" class="form-select custom-select" onchange="loadStudentEngagemnet()">
                                        <option value="1" data-i18n="this-week">This Week</option>
                                        <option value="2" data-i18n="this-month">This Month</option>
                                        <option value="3" data-i18n="this-year">This Year</option>
                                    </select>
                                </div>
                            </div>
                            <div class="component-theme d-flex flex-row align-items-center justify-content-center p-0 mt-2 w-100">
                                <canvas id="studentEngagementlineChart" class="w-100 h-auto"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4 d-flex">
                        <div class="d-flex flex-column component-theme p-4 w-100">
                            <div class="d-flex flex-row p-0 m-0">
                                <span class="sub-topic" data-i18n="recently-student-engagements">Recently Student Engagement</span>
                            </div>
                            <?php
                            $recenteStudents = Database::search("SELECT `first_name`,`last_name`,`title`,`profile_img`,`date_registered` FROM `students`
                                            INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                                            INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                                            ORDER BY `students`.`student_id` DESC LIMIT 6
                                            ");
                            if ($recenteStudents->num_rows > 0) {
                                while ($data = $recenteStudents->fetch_assoc()) {
                            ?>
                                    <div class="d-flex flex-column p-2">
                                        <div class="d-flex p-2">
                                            <?php
                                            if (!empty($data["profile_img"]) && file_exists('./assets/imgs/students/' . $data["profile_img"])) {
                                            ?>
                                                <img src="./assets/imgs/students/<?php echo $data["profile_img"]; ?>" class="hwr-50" alt="Students image">
                                            <?php
                                            } else {
                                                $firstNameInitial = strtoupper(substr($data["first_name"], 0, 1));
                                                $lastNameInitial = strtoupper(substr($data["last_name"], 0, 1));
                                            ?>
                                                <div class="d-flex hwr-50 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                                            <?php
                                            }
                                            ?>
                                            <div class="d-flex flex-column mx-4 flex-grow-1">
                                                <span class="text-14 text-dark-color fw-medium lh-lg"><?= $data['title'] . "." . $data['first_name'] . " " . $data['last_name'] ?></span>
                                                <span class="text-14 sub-text">Registered On : <?= $data['date_registered'] ?></span>
                                            </div>
                                        </div>
                                        <hr class="sub-text m-0">
                                    </div>
                                <?php
                                }
                            } else {
                                ?>
                                <div class="d-flex flex-column align-items-center justify-content-center">
                                    <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                        <div class="no-data-state">
                                            <div class="no-data-icon-wrap">
                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                            </div>
                                            <p class="no-data-text">No data</p>
                                        </div>
                                    </div>
                                </div>
                            <?php
                            }

                            ?>
                        </div>
                    </div>
                </div>
            </div>
          
            <div class="container p-4 component-theme mt-4">
                <div class="row p-0 m-0 g-0 align-content-center">
                    <div class="col-12 mb-3 d-flex flex-column">
                        <span class="sub-topic" data-i18n="ongoing-lectures">Ongoing Lectures</span>
                    </div>

                    <div class="table-responsive-wrapper">
                        <div class="table-responsive" id="schedule-table-container">
                            <table class="table bl-solid m-0 p-0 g-0" id="lecture-schedule-table">
                                <thead>
                                    <tr class="br-t-5">
                                        <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                            <div class="d-flex flex-row align-items-center">
                                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                <span class="text-14 main-text fw-medium mx-2">Lecturer Name</span>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">lecture Type</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Intake</th>

                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Subject</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Time</th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium"></th>
                                        <th scope="col" class="px-4 py-3 bg-table-header">
                                            <div class="d-flex flex-row align-items-center">
                                                <span class="text-14 main-text fw-medium">Status</span>
                                                <iconify-icon icon="ion:arrow-down-sharp" width="20" height="20" class="text-dark-color px-1"></iconify-icon>
                                            </div>
                                        </th>

                                    </tr>
                                </thead>
                                <tbody id="user-table-data">
                                    <?php

                                    $timezone = new DateTimeZone("Asia/Colombo");
                                    $today = new DateTime("now", $timezone);
                                    $todayDate = $today->format('Y-m-d');
                                    $nowTime = $today->format('H:i:s');


                                    $query = "SELECT * FROM `time_table`
                                                                                INNER JOIN `lecturers` ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                                                                INNER JOIN `users` ON `lecturers`.`users_user_id` = `users`.`user_id`
                                                                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                                INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                                INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                            
                                                                                INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                                                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                                                                                WHERE `time_table`.`lecture_type_lecture_type_id` != '3'
                                                                                AND `time_table`.`date`='" . $todayDate . "' AND `time_table`.`start_time` <= '" . $nowTime . "'
                                                                                AND `time_table`.`end_time` >= '" . $nowTime . "' AND `time_table`.`time_table_status_time_table_status_id`='1'
                                                                                ";

                                    $query .= " ORDER BY `time_table`.`time_table_id` DESC";

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
                                    $lectures = Database::search("" . $query . " LIMIT " . $result_per_page . " OFFSET " . $page_results . "");

                                    if ($lectures->num_rows > 0) {
                                        while ($data = $lectures->fetch_assoc()) {
                                    ?>
                                            <tr>
                                                <td colspan="4" class="px-4 py-3 pe-0 align-middle">
                                                    <div class="d-flex flex-row align-items-center w-100 p-0 g-0 m-0">
                                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check" id="check-user<?php echo $data["user_id"]; ?>" onchange="checkRowEvents(<?php echo $data['user_id']; ?>);">
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
                                                        <span class="text-14 main-text mx-2"><?php echo $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"]; ?></span>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-2 align-middle">
                                                    <?php
                                                    if ($data["lecture_type_lecture_type_id"] == 1) {
                                                    ?>
                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                            <div class="custom-round-div bg-light-green border-white d-flex flex-row align-items-center justify-content-center">
                                                                <iconify-icon icon="material-symbols:home-work" width="15" height="15" class="text-dark-green m-0 p-0 g-0"></iconify-icon>
                                                            </div>
                                                            <span class="text-14 text-dark-green fw-medium px-3">Physical</span>
                                                        </div>
                                                    <?php
                                                    } else {
                                                    ?>
                                                        <div class="d-flex flex-row align-items-center  p-0 g-0 m-0">
                                                            <div class="custom-round-div bg-light-purple border-white d-flex flex-row align-items-center justify-content-center">
                                                                <iconify-icon icon="mdi:video" width="15" height="15" class="text-dark-purple m-0 p-0 g-0"></iconify-icon>
                                                            </div>
                                                            <span class="text-14 text-dark-purple fw-medium px-3">Online</span>
                                                        </div>
                                                    <?php
                                                    }
                                                    ?>
                                                </td>
                                                <td class="px-4 py-2 align-middle">
                                                    <span class="text-14 main-text"><?php echo $data["intake"]; ?></span>
                                                </td>

                                                <td class="px-4 py-2 align-middle">
                                                    <span class="text-14 main-text"><?php echo $data["subject"]; ?></span>
                                                </td>
                                                <?php
                                                $start = (new DateTime($data["start_time"]))->format('h:i A');
                                                $end = (new DateTime($data["end_time"]))->format('h:i A');
                                                $date = $data["date"] . " " . $start . " - " . $end;
                                                ?>
                                                <td colspan="3" class="px-4 py-2 align-middle"><span class="text-14 main-text"><?php echo $date ?></span></td>

                                                <td class="px-4 py-3 align-middle">

                                                    <button class="table-btn table-btn-yellow w-100">Ongoing</button>
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
                        </div>
                    </div>
                </div>
            </div>

<?php
        }
    }
}
