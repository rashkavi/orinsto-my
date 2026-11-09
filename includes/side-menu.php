<?php
require "includes/nav-item.php";

class load_side_menu
{
    public static function load($selectedKey)
    {
        require_once "UserClass.php";
        require_once "UserPermissionsClass.php";

        $sessionUser = SessionUser::getCurrentUserObject();
        $sessionUserRole = SessionUser::getCurrentUserRole();


        $menuItems = [
            [
                "title" => "Dashboard",
                "icon" => "material-symbols:team-dashboard-outline",
                "link" => "counselor-dashboard",
                "key" => "dashboard"
            ]
        ];

        $menuItems = [
            [
                "title" => "Dashboard",
                "icon" => "material-symbols:team-dashboard-outline",
                "link" => "dashboard",
                "key" => "dashboard"
            ]
        ];



        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsCoordinator($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) ||  UserPermissions::validPermisisonType('students')) {
            $menuItems[] = [
                "title" => "Academic",
                "icon" => "solar:square-academic-cap-broken",
                "link" => "academic-department",
                "key" => "academic-department",
                "children" => [
                    ["title" => "All Students", "link" => "students", "key" => "students"],
                    ["title" => "Course Overview", "link" => "faculties", "key" => "course-overview"],
                    ["title" => "Batch Activity", "link" => "batch-activity-report", "key" => "batch-activity"],
                    ["title" => "Lecture Schedule", "link" => "lecture-schedule", "key" => "lecture-schedule"],
                    [
                        "title" => "Attendance",
                        "key"   => "attendance",
                        "children" => [
                            ["title" => "Ongoing Lecturers", "link" => "attendance",                      "key" => "attendance-ongoing"],
                            ["title" => "Students Breakdown",       "link" => "students-attendance-breakdown",   "key" => "students-attendance-breakdown"],
                            ["title" => "Intake Wise Breakdown",       "link" => "intakes-attendance-breakdown",      "key" => "attendance-intake-breakdown"],
                        ]
                    ],
                    ["title" => "Lecture Feedbacks", "link" => "lecture-feedback-report", "key" => "lecture-feedbacks"],
                    ["title" => "Student Policy", "link" => "student-policy", "key" => "student-policy"],
                ]
            ];
        }

        if (SessionUser::ifUserIsMarketingSRO($sessionUserRole)) {
            $menuItems[] = [
                "title" => "Academic",
                "icon" => "solar:square-academic-cap-broken",
                "link" => "academic-department",
                "key" => "academic-department",
                "children" => [

                    ["title" => "Students", "link" => "counselor-students", "key" => "counselor-students"],
                    ["title" => "Course Overview", "link" => "faculties", "key" => "course-overview"],

                    ["title" => "Attendance Breakdown", "link" => "students-attendance-breakdown", "key" => "students-attendance-breakdown"],
                ]
            ];
        }


        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $menuItems[] = [
                "title" => "Academic",
                "icon" => "solar:square-academic-cap-broken",
                "link" => "academic-department",
                "key" => "academic-department",
                "children" => [

                    ["title" => "Course Overview", "link" => "faculties", "key" => "course-overview"],
                    ["title" => "My Subjects", "link" => "my-subjects", "key" => "my-subjects"],
                    ["title" => "Lecture Schedule", "link" => "lecture-schedule", "key" => "lecture-schedule"],
                    ["title" => "Attendance", "link" => "attendance", "key" => "attendance"],

                ]
            ];
        }

        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAdmin($sessionUserRole) || UserPermissions::validPermisisonType('payments') || UserPermissions::hasPermission('users', 5) ||  UserPermissions::hasPermission('users', 6)) {
            $financeChildrens = [];

            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments')) {
                $financeChildrens[] =  ["title" => "Payments", "link" => "payments", "key" => "payments"];
            } else if (SessionUser::ifUserIsAdmin($sessionUserRole)) {
                $financeChildrens[] =  ["title" => "Payments", "link" => "lecturers-payments", "key" => "payments"];
            }

            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::validPermisisonType('payments')) {
                $financeChildrens[] = ["title" => "Payroll Runs", "link" => "payroll-runs?department=finance", "key" => "finance-payroll-runs"];
                $financeChildrens[] =  ["title" => "Payment Plans", "link" => "payment-plans", "key" => "payment-plans"];
            }

            if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 5) ||  UserPermissions::hasPermission('users', 6)) {
                $financeChildrens[] = ["title" => "Permissions", "link" => "finance-permissions", "key" => "finance-permissions"];
            }
            $menuItems[] = [
                "title" => "Finance",
                "icon" => "material-symbols:finance-mode-rounded",
                "link" => "financial-department",
                "key" => "financial-department",
                "children" => $financeChildrens
            ];
        }
        if (SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $menuItems[] = [
                "title" => "Payments",
                "icon" => "f7:wallet",
                "link" => "my-payments",
                "key" => "my-payments"
            ];
        }

        if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('leads') ||  UserPermissions::hasPermission('users', 5) ||  UserPermissions::hasPermission('users', 6)) {

            $marketingChildren = [];
            if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('leads')) {
                $marketingChildren = [
                    ["title" => "Dashboard", "link" => "marketing-dashboard", "key" => "marketing-dashboard"],
                    ["title" => "New Leads", "link" => "new-leads", "key" => "new-leads"],
                    ["title" => "Daily Works", "link" => "daily-works", "key" => "daily-works"],
                    ["title" => "Inquiries", "link" => "inquiries", "key" => "inquiries"],
                    ["title" => "Enrollments", "link" => "enrollments", "key" => "enrollments"],
                    ["title" => "Performance", "link" => "performance", "key" => "performance"],
                    ["title" => "Reports", "link" => "marketing-reports", "key" => "marketing-reports"],
                ];
            }


            if (
                SessionUser::ifUserIsPro($sessionUserRole) ||
                UserPermissions::hasPermission('users', 5) ||
                UserPermissions::hasPermission('users', 6)
            ) {
                $marketingChildren[] = [
                    "title" => "Permissions",
                    "link" => "marketing-permissions",
                    "key" => "marketing-permissions"
                ];
            }

            $menuItems[] = [
                "title" => "Marketing",
                "icon" => "lsicon:marketing-outline",
                "link" => "financial-department",
                "key" => "financial-department",
                "children" => $marketingChildren
            ];
        }


        $hrChildrens = [];

        if (SessionUser::ifUserIsPro($sessionUserRole)) {

            $hrChildrens = [
                ["title" => "Users", "link" => "users", "key" => "users"],
                ["title" => "Lecturers", "link" => "lecturers", "key" => "lecturers"],
                ["title" => "Leave Dashboard", "link" => "leave-dashboard", "key" => "leave-dashboard"],
                ["title" => "Leave Requests", "link" => "leave-requests", "key" => "leave-requests"],
                ["title" => "Payroll Runs", "link" => "payroll-runs?department=hr", "key" => "payroll-runs"],
                ["title" => "User Attendance", "link" => "user-attendance", "key" => "user-attendance"],
                ["title" => "My Leave Requests", "link" => "my-leave-requests", "key" => "my-leave-requests"],
                ["title" => "My Payroll Profile", "link" => "my-payroll-profile", "key" => "my-payroll-profile"],
                ["title" => "My Pay Sheets", "link" => "my-pay-sheets", "key" => "my-pay-sheets"],
                ["title" => "Permissions", "link" => "users-permissions", "key" => "users-permissions"],
            ];
        } elseif (UserPermissions::validPermisisonType('users') || SessionUser::ifUserIsAcademicPro($sessionUserRole)) {

            $hrChildrens = [
                ["title" => "Users", "link" => "users", "key" => "users"],
                ["title" => "Lecturers", "link" => "lecturers", "key" => "lecturers"],
            ];

            if (UserPermissions::hasPermission('users', 25)) {
                $hrChildrens[] = ["title" => "Leave Dashboard", "link" => "leave-dashboard", "key" => "leave-dashboard"];
                $hrChildrens[] = ["title" => "Leave Requests", "link" => "leave-requests", "key" => "leave-requests"];
            }

            if (UserPermissions::hasPermission('users', 27)) {
                $hrChildrens[] = ["title" => "Payroll Runs", "link" => "payroll-runs?department=hr", "key" => "payroll-runs"];
            }

            $hrChildrens[] = ["title" => "My Leave Requests", "link" => "my-leave-requests", "key" => "my-leave-requests"];
            $hrChildrens[] = ["title" => "My Payroll Profile", "link" => "my-payroll-profile", "key" => "my-payroll-profile"];
            $hrChildrens[] = ["title" => "My Pay Sheets", "link" => "my-pay-sheets", "key" => "my-pay-sheets"];

            if (
                UserPermissions::hasPermission('users', 5) ||
                UserPermissions::hasPermission('users', 6)
            ) {
                $hrChildrens[] = ["title" => "Permissions", "link" => "users-permissions", "key" => "users-permissions"];
            }
        } else {

            $hrChildrens = [];

            if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
                $hrChildrens[] = [
                    "title" => "My Leave Requests",
                    "link" => "my-leave-requests",
                    "key" => "my-leave-requests"
                ];
                $hrChildrens[] = [
                    "title" => "My Payroll Profile",
                    "link" => "my-payroll-profile",
                    "key" => "my-payroll-profile"
                ];
                $hrChildrens[] = [
                    "title" => "My Pay Sheets",
                    "link" => "my-pay-sheets",
                    "key" => "my-pay-sheets"
                ];
            }
        }

        if (count($hrChildrens) > 0) {
            $menuItems[] = [
                "title" => "HR",
                "icon" => "bx:user-pin",
                "link" => "financial-department",
                "key" => "financial-department",
                "children" => $hrChildrens
            ];
        }

        $menuItems[] = [
            "title" => "Event Calender",
            "icon" => "carbon:event",
            "link" => "event-calender",
            "key" => "event-calender",

        ];

        if (!SessionUser::ifUserIsLecturer($sessionUserRole)) {
            $menuItems[] = [
                "title" => "News",
                "icon" => "arcticons:patrika-news",
                "link" => "news",
                "key" => "news"
            ];

            $menuItems[] = [
                "title" => "FAQ",
                "icon" => "tdesign:questionnaire",
                "link" => "frequently-asked-questions",
                "key" => "faq"
            ];
        }

        $menuItems[] = [
            "title" => "Help Desk",
            "icon" => "clarity:help-info-line",
            "link" => "help-desk",
            "key" => "help-desk",
        ];
?>
        <nav class="sidebar-premium d-flex flex-column container-fluid p-0 m-0 vh-100 component-theme">
            <div class="sidebar-brand row container-fluid justify-content-center component-theme py-4 px-3 m-0">
                <img src="./assets/imgs/<?= Project::getInstituteLogo() ?>" alt="<?= Project::getBaseName() ?> Logo" class="institute-logo w-100 p-0 m-0">
            </div>

            <div class="sidebar-menu flex-grow-1 overflow-scroll px-3 pe-0 m-0">
                <?php
                foreach ($menuItems as $item) {
                    nav_item::loadItem($item, $selectedKey);
                }
                ?>
            </div>

            <button class="sidebar-user d-flex align-items-center border-0 px-3 py-2 m-3" onclick="signOut();">
                <?php
                if (!empty($sessionUser["user_image"]) && file_exists('./assets/imgs/users/' . $sessionUser["user_image"])) {
                ?>
                    <img src="./assets/imgs/users/<?php echo $sessionUser["user_image"]; ?>" class="hwr-40" alt="User image">
                <?php
                } else {
                    $firstNameInitial = strtoupper(substr($sessionUser["user_fname"], 0, 1));
                    $lastNameInitial = strtoupper(substr($sessionUser["user_lname"], 0, 1));
                ?>
                    <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?php echo $firstNameInitial . $lastNameInitial; ?></div>
                <?php
                }
                ?>
                <div class="d-flex flex-column align-items-start mx-2 flex-grow-1 overflow-hidden">
                    <span class="text-16 main-text fw-medium text-truncate">
                        <?php
                        $role = ucwords(strtolower($sessionUserRole));
                        echo strlen($role) > 18 ? substr($role, 0, 18) . '...' : $role;
                        ?>
                    </span>
                    <span class="text-12 sub-text fw-medium">Log Out</span>
                </div>
                <iconify-icon icon="ic:sharp-logout" width="20" height="20" class="text-dark-red"></iconify-icon>
            </button>
        </nav>
<?php
    }
}
?>