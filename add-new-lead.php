<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 2))) {
            header("Location: ./students");
        } else {
?>
            <!DOCTYPE html>
            <html lang="en">

            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="robots" content="noindex, nofollow">
                <title>Add New Lead - Admin Dashboard | <?= Project::getBaseName(); ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                <style>
                  
                </style>
            </head>

            <body class="overflow-hidden" onload="initializeAddLead();">
                <div class="notifications"></div>
                <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                    <div id="appContainer" class="row p-0">
                        <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                            <?php
                            include "includes/side-menu.php";
                            load_side_menu::load("new-leads");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("new-leads");
                                    ?>
                                </div>
                                <div class="container p-4 m-0" id="skeleton-list">
                                    <?php
                                    include "./includes/components/skeleton-header.php";
                                    include "./includes/components/skeleton-add-edit.php"; ?>
                                </div>
                                <div class="col-12 p-0 m-0 g-0 contentBodyArea vh-100 d-flex flex-column d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12 col-md-8">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg" data-i18n="add-student">Add Student</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="students" href="./students">Students</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="add-student">Add Student</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                                <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                    <div class="row h-100 justify-content-end align-items-center gx-3">
                                                        <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" data-i18n="" onclick="addLead(this)">Save Lead</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 p-4 component-theme">
                                            <div class="row p-0 m-0 align-content-center">
                                                <div class="col-12 p-0 m-0 g-0">
                                                    <div class="row justify-content-center p-0 m-0 g-0">

                                                        <div class="col-12">
                                                            <div class="row m-0 p-0">
                                                                <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                                    <div class="row gx-4 gy-4">
                                                                        <span class="sub-topic">Personal Details</span>

                                                                        <div class="col-12 col-lg-8 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Full Name</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="full-name" maxlength="150">
                                                                            <span id="fullname-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-lg-2 d-flex flex-column">
                                                                            <label class="text-14 main-text not-required" for="">Date of Birth</label>
                                                                            <input type="date" class="form-control custom-input mt-2" id="dob">
                                                                            <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-lg-2 d-flex flex-column">
                                                                            <label class="text-14 main-text not-required" for="">Select Gender</label>
                                                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="gender-selector">
                                                                                <option selected value="0">- Select -</option>
                                                                                <?php
                                                                                $genders = Database::search("SELECT * FROM `gender`");
                                                                                while ($data = $genders->fetch_assoc()) {
                                                                                ?>
                                                                                    <option value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                                <?php
                                                                                }

                                                                                ?>
                                                                            </select>
                                                                            <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                        </div>

                                                                        <div class="col-12 d-flex flex-column">
                                                                            <label class="text-14 main-text" for="">Address</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="address" maxlength="100">
                                                                            <span id="address-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-6 d-flex flex-column">
                                                                            <label class="text-14 main-text not-required" for="">Email</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="email" maxlength="100">
                                                                            <span id="email-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text required" for="">Mobile</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="mobile">
                                                                            <span id="mobile-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                                            <label class="text-14 main-text not-required" for="">Whatsapp</label>
                                                                            <input type="text" class="form-control custom-input mt-2" id="whatsapp">
                                                                            <span id="whatsapp-feedback" class="text-dark-red text-12"></span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row gx-4 gy-3">
                                                        <span class="sub-topic">Past Education Qualifications</span>

                                                        <div class="col-12 col-md-9 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">School</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="school" maxlength="100">
                                                            <span id="school-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Grade</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="grade" maxlength="100">
                                                            <span id="grade-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">College/Univercity</label>
                                                            <input type="text" class="form-control custom-input mt-2" id="college" maxlength="100">
                                                            <span id="college-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 mt-5">
                                                    <div class="row gx-4 gy-2">
                                                        <div class="col-12 mb-3">
                                                            <span class="sub-topic">Other</span>
                                                        </div>

                                                        <div class="col-12 col-lg-3 d-flex flex-column">
                                                            <label class="text-14 main-text not-required" for="">Interested Programme</label>
                                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="programe-selector">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $programme = Database::search("SELECT * FROM `programmes`");
                                                                while ($data = $programme->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["programme_id"]; ?>"><?php echo $data["programme"]; ?></option>
                                                                <?php
                                                                }

                                                                ?>
                                                            </select>
                                                            <span id="programe-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-lg-3 d-flex flex-column">
                                                            <label class="text-14 main-text required" for="">Lead Sourse</label>
                                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="source-selector" onchange="changeSource(this.value);">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $sources = Database::search("SELECT * FROM `lead_source`");
                                                                while ($data = $sources->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["lead_source_id"]; ?>"><?php echo $data["lead_source"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span id="source-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex flex-column d-none" id="institutes">
                                                            <label class="text-14 main-text required" for="">Institutes</label>
                                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="institute-selector" onchange="changeInstitute(this.value);">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $sources = Database::search("SELECT `institutes`.*,COUNT(`institutes_institute_id`) AS `count` FROM `institutes`
                                                                LEFT JOIN `institute_has_branches` ON `institutes`.`institute_id` = `institute_has_branches`.`institutes_institute_id`
                                                                GROUP BY `institutes`.`institute_id`");
                                                                while ($data = $sources->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["institute_id"]; ?>" data-branch-count="<?php echo $data["count"]; ?>"><?php echo $data["institute"]; ?></option>
                                                                <?php
                                                                }

                                                                ?>
                                                            </select>
                                                            <span id="institute-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                        <div class="col-12 col-lg-3 d-flex flex-column d-none" id="institutes-branches">
                                                            <label class="text-14 main-text required" for="">Institute Location</label>
                                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="institute-branch-selector">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $sources = Database::search("SELECT * FROM `institute_has_branches`");
                                                                while ($data = $sources->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["institute_has_branch_id"]; ?>" data-institute="<?php echo $data["institutes_institute_id"]; ?>"><?php echo $data["institute_has_branch"]; ?></option>
                                                                <?php
                                                                }

                                                                ?>
                                                            </select>
                                                            <span id="institute-branch-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 col-lg-3 d-flex flex-column">
                                                            <label class="text-14 main-text not-required" for="">Assign Counselor / SRO</label>
                                                            <select class="form-select custom-select mt-2" aria-label="Default select example" id="user-selector">
                                                                <option selected value="0">- Select -</option>
                                                                <?php
                                                                $users = Database::search("SELECT `users`.`user_id`,`title`.`title`,`user_fname`,' ',`user_lname` FROM `users` 
                                                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                WHERE `user_roles_user_role_id` IN (7,8,11,12,21) AND `status_status_id` = '1'");
                                                                while ($data = $users->fetch_assoc()) {
                                                                ?>
                                                                    <option value="<?php echo $data["user_id"]; ?>"><?php echo $data["title"] . " " . $data["user_fname"] . " " . $data["user_lname"]; ?></option>
                                                                <?php
                                                                }

                                                                ?>
                                                            </select>
                                                            <span id="user-feedback" class="text-dark-red text-12"></span>
                                                        </div>

                                                        <div class="col-12 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Note</label>
                                                            <textarea type="text" class="form-control custom-input mt-2" rows="5" id="note" maxlength="100"></textarea>
                                                            <span id="note-feedback" class="text-dark-red text-12"></span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                            <button class="custom-btn w-100" data-i18n="" onclick="addLead(this);">Save Lead</button>
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

                <div class="modal fade duplicate-lead-modal" id="duplicateMobileModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered duplicate-lead-dialog">
                        <div class="modal-content duplicate-lead-content border-0">

                            <button type="button" class="duplicate-lead-close" data-bs-dismiss="modal">
                                <iconify-icon icon="mdi:close" width="18" height="18"></iconify-icon>
                            </button>

                            <div class="modal-body text-center px-4 py-4">

                                <div class="duplicate-lead-icon mx-auto mb-3">
                                    <iconify-icon icon="solar:phone-calling-rounded-bold" width="46" height="46"></iconify-icon>
                                </div>

                                <h5 class="duplicate-lead-title mb-2">
                                    Duplicate Mobile Number
                                </h5>

                                <p class="duplicate-lead-text mb-4">
                                    This mobile number already exists in ongoing leads. Do you still want to continue?
                                </p>


                                <button type="button"
                                    class="btn duplicate-lead-primary w-100 mb-2"
                                    data-bs-dismiss="modal">
                                    Yes, Continue
                                </button>

                                <button type="button"
                                    class="btn duplicate-lead-secondary w-100"
                                    data-bs-dismiss="modal"
                                    onclick="clearMobileField();">
                                    No, Cancel
                                </button>

                            </div>
                        </div>
                    </div>
                </div>

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php
        }
    }
}
?>