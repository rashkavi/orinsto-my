<?php

require_once './includes/UserClass.php';
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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['lead-id']) && !empty($_GET['lead-id']))) {
            header("Location: ./inquiries");
        } else {
            require_once "./includes/connection.php";
            $id = $_GET['lead-id'];

            $query = "
            SELECT 
                leads.*,
                users.*,
               programmes.*,
               faculties.*,
              gender.*,
              lead_source.*,
               
                `leads`.`lead_id` AS 'nid', `leads`.`name` AS 'full_name', 
                `lead_status`.`lead_status` AS 'new_status',`lead_status`.`lead_status_id` AS `sid`,
                `users`.`user_id` AS `uid`
            FROM `leads`
            INNER JOIN `lead_status` 
                ON leads.lead_status_lead_status_id = lead_status.lead_status_id
            LEFT JOIN `users` 
                ON leads.users_user_id = users.user_id
            LEFT JOIN `programmes` ON `leads`.`programmes_programme_id` = `programmes`.`programme_id`
            LEFT JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
            LEFT JOIN `gender` ON `leads`.`gender_gender_id` = `gender`.`gender_id`
            LEFT JOIN `lead_source` ON `leads`.`lead_source_lead_source_id` = `lead_source`.`lead_source_id`
           
            WHERE `leads`.`lead_id` = '" . $id . "'  AND NOT EXISTS (
                        SELECT 1 
                        FROM `students_has_intakes` s 
                        WHERE s.`leads_lead_id` = leads.`lead_id`
                    )
                    ";

            $lead = Database::search($query);
            if ($lead->num_rows == 0) {
                header("Location: ./lead-view?lead-id=" . $id);
            } else {
                $leadData = $lead->fetch_assoc();

                $hasPermissionUpdateLead = true;

                $countries = array();
                $countries_rs = Database::search("SELECT * FROM `countries`");
                while ($data = $countries_rs->fetch_assoc()) {
                    array_push($countries, [
                        'id' => $data['country_id'],
                        'name' => $data['country_name']
                    ]);
                }

?>
                <!DOCTYPE html>
                <html lang="en">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <meta name="robots" content="noindex, nofollow">
                    <title>Student Registration - Admin Dashboard | <?= Project::getBaseName(); ?></title>

                    <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                    <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                    <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                    <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                    <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                    <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                </head>

                <body class="overflow-hidden" onload="initializeAddStudent(),initializeStudentDocumentUploads()">
                    <div class="notifications"></div>
                    <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                        <div id="appContainer" class="row p-0">
                            <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                <?php
                                include "includes/side-menu.php";
                                load_side_menu::load("inquiries");
                                ?>
                            </div>
                            <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                <div class="row p-0 m-0 g-0">
                                    <div class="col-12 p-0 m-0 g-0">
                                        <?php
                                        include "./includes/header.php";
                                        selectItem::setSelected("inquiries");
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
                                                            <span class="main-topic lh-lg" data-i18n="">Student Registration</span>
                                                            <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                <ol class="breadcrumb m-0">
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./inquiries.php">All Leads</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./lead-view.php?lead-id=<?= $id ?>">Lead View</a></li>
                                                                    <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Student Registration</a></li>
                                                                </ol>
                                                            </nav>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                        <div class="row h-100 justify-content-end align-items-center gx-3">
                                                            <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                <button class="custom-btn w-100" data-i18n="save-student" onclick="convertLeadToStudent(this,<?= $id ?>)">Save Student</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="col-12 p-4 component-theme">
                                                <div class="row p-0 m-0 align-content-center">
                                                    <div class="col-12 p-0 m-0 g-0">
                                                        <div class="row justify-content-center p-0 m-0 g-0">
                                                            <div class="col-8 col-md-3 col-xl-3 col-xxl-2 d-flex flex-column p-0 m-0">
                                                                <div class="bl-solid p-3 m-0">
                                                                    <div class="row m-0 p-0 position-relative justify-content-center align-content-center" id="profile-image-container">
                                                                        <img src="./assets/imgs/common/empty-img.png" alt="user profile image" id="profile-image" class="w-100 h-auto me-2 mx-2 mt-0 mb-0 p-0">
                                                                        <div class="upload-image text-white p-2 bottom-0 start-0" onclick="uploadImage('file-input','profile-image');">
                                                                            <iconify-icon icon="ic:baseline-upload" width="20" height="20" class="text-white px-2"></iconify-icon>
                                                                            <span>Upload Image</span>
                                                                        </div>
                                                                    </div>
                                                                    <!-- Hiddedn file input -->
                                                                    <input type="file" id="file-input" class="d-none" accept="image/*" />
                                                                    <p class="text-center text-14 sub-text mt-2 mb-0" id="profile-image-text">Image size must be 2MB or less, with a 1:1 aspect ratio for optimal quality. Accepted formats: JPG, PNG.</p>
                                                                </div>
                                                                <span id="image-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-9 col-xl-9 col-xxl-10 px-0 px-md-4 pe-0 pe-md-0 pe-lg-0 d-block">
                                                                <div class="row m-0 p-0">
                                                                    <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                                        <div class="row gx-4 gy-4">
                                                                            <span class="sub-topic">Personal Details</span>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Select Title</label>
                                                                                <select class="form-select custom-select mt-2" aria-label="Default select example" id="title-selector">
                                                                                    <option selected value="0">- Select -</option>
                                                                                    <?php
                                                                                    $titleResult = Database::search("SELECT * FROM `title`");
                                                                                    $titles = [];
                                                                                    while ($data = $titleResult->fetch_assoc()) {
                                                                                        $titles[] = $data;
                                                                                    }
                                                                                    foreach ($titles as $title) {
                                                                                    ?>
                                                                                        <option value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="title-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">First Name</label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="first-name" maxlength="45">
                                                                                <span id="fname-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Last Name</label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="last-name" maxlength="45">
                                                                                <span id="lname-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-7 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Full Name</label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="full-name" maxlength="150" value="<?= $leadData["name"] ?>">
                                                                                <span id="fullname-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-5 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Name With Initials</label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="name-with-initials" maxlength="45">
                                                                                <span id="nwi-feedback" class="text-dark-red text-12"></span>
                                                                            </div>

                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Select Gender</label>
                                                                                <select class="form-select custom-select mt-2" aria-label="Default select example" id="gender-selector">
                                                                                    <option selected value="0">- Select -</option>
                                                                                    <?php
                                                                                    $genders = Database::search("SELECT * FROM `gender`");
                                                                                    while ($data = $genders->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?php echo $data["gender_id"]; ?>" <?= $leadData['gender_gender_id'] == $data['gender_id'] ? 'selected' : '' ?>><?php echo $data["gender"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Select Marital Status<Title></Title></label>
                                                                                <select class="form-select custom-select mt-2" aria-label="Default select example" id="marital-status-selector">
                                                                                    <option selected value="0">- Select -</option>
                                                                                    <?php
                                                                                    $marital_status = Database::search("SELECT * FROM `marital_status`");
                                                                                    while ($data = $marital_status->fetch_assoc()) {
                                                                                    ?>
                                                                                        <option value="<?php echo $data["marital_status_id"]; ?>"><?php echo $data["marital_status"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="marital-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column justify-content-end">
                                                                                <label class="text-14 main-text d-inline-flex align-items-center required" for="">
                                                                                    NIC
                                                                                    <span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="National Id Card Number">
                                                                                        <iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon>
                                                                                    </span>
                                                                                </label>
                                                                                <input type="text" class="form-control custom-input mt-2" id="nic" maxlength="12">
                                                                                <span id="nic-feedback" class="text-dark-red text-12"></span>
                                                                            </div>

                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text required" for="">Date of Birth</label>
                                                                                <input type="date" class="form-control custom-input mt-2" id="dob" value="<?= $leadData['date_of_birth'] ?>">
                                                                                <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text not-equired" for="">Nationality</label>
                                                                                <select class="form-select custom-select mt-2" id="nationality">
                                                                                    <?php
                                                                                    foreach ($countries as $country) {
                                                                                    ?>
                                                                                        <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == 69 ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="nationality-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text not-required" for="">Country of Birth</label>
                                                                                <select class="form-select custom-select mt-2" id="country-of-birth">
                                                                                    <?php
                                                                                    foreach ($countries as $country) {
                                                                                    ?>
                                                                                        <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == 69 ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="cob-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                <label class="text-14 main-text not-required" for="">Country of Residence</label>
                                                                                <select class="form-select custom-select mt-2" id="country-of-residence" onchange="changeCountryOfResidence(this.value)">
                                                                                    <?php
                                                                                    foreach ($countries as $country) {
                                                                                    ?>
                                                                                        <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == 69 ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                    <?php
                                                                                    }
                                                                                    ?>
                                                                                </select>
                                                                                <span id="cor-feedback" class="text-dark-red text-12"></span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <div class="row gx-4 gy-3">
                                                            <span class="sub-topic">Contact Details</span>

                                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                                <label class="text-14 main-text required" for="">Email</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="email" maxlength="100" value="<?= $leadData["email"] ?>">
                                                                <span id="email-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text required" for="">Mobile</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="mobile" value="<?= $leadData["contact"] ?>">
                                                                <span id="mobile-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text required" for="">Whatsapp</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="whatsapp" value="<?= $leadData["whatsapp"] ?>">
                                                                <span id="whatsapp-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Address Line 1</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="address" maxlength="100" value="<?= $leadData["address"] ?>">
                                                                <span id="address-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Address Line 2</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="address2" maxlength="100">
                                                                <span id="address2-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Province</label>
                                                                <select name="" id="province" class="form-select custom-select mt-2" onchange="loadDistrict();">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    $province = Database::search("SELECT * FROM `province`");
                                                                    while ($data = $province->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </select>
                                                                <span id="province-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">District</label>
                                                                <select name="" id="district" class="form-select custom-select mt-2" onchange="loadCities();">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    $district = Database::search("SELECT * FROM `district`");
                                                                    while ($data = $district->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $data["district_id"]; ?>"><?php echo $data["district"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="district-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">City</label>
                                                                <select name="" id="city" class="form-select custom-select mt-2">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    $city = Database::search("SELECT * FROM `city`");
                                                                    while ($data = $city->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $data["city_id"]; ?>"><?php echo $data["city"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="city-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Postal Code</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="postal-code" maxlength="5">
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 p-0 g-0 mt-3">
                                                        <div class="row gx-4 gy-3">
                                                            <span class="sub-topic">Advanced Profile Details</span>

                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Faculties</label>
                                                                <?php
                                                                $facultiesResult = Database::search("SELECT * FROM `faculties` ORDER BY `faculty_id` ASC");
                                                                $faculties = [];
                                                                while ($data = $facultiesResult->fetch_assoc()) {
                                                                    $faculties[] = $data;
                                                                }
                                                                ?>
                                                                <select id="faculty-selector" class="form-select custom-select mt-2" onchange="loadProgrammes();">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    foreach ($faculties as $data) {
                                                                    ?>
                                                                        <option value="<?php echo $data["faculty_id"]; ?>" <?= $leadData["faculty_id"] == $data["faculty_id"] ? 'selected' : ''; ?>><?php echo $data["faculty"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="text-dark-red text-12" id="faculty-feedback"></span>
                                                            </div>
                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Programmes</label>
                                                                <?php
                                                                $programmesResult = Database::search("SELECT * FROM `programmes` ORDER BY `programme_id` ASC");
                                                                $programmes = [];
                                                                while ($data = $programmesResult->fetch_assoc()) {
                                                                    $programmes[] = $data;
                                                                }
                                                                ?>
                                                                <select id="programme-selector" <?= !empty($leadData["programme_id"]) ? '' : 'disabled'  ?> class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    if (!empty($leadData["programme_id"])) {
                                                                    ?>
                                                                        <option value="<?php echo $leadData["programme_id"]; ?>" selected><?php echo $leadData["programme"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="text-dark-red text-12" id="programme-feedback"></span>
                                                            </div>
                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Intake</label>

                                                                <select id="intake-selector" <?= !empty($leadData["programme_id"]) ? '' : 'disabled'  ?> class="form-select custom-select mt-2">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    $intakesResult = Database::search("SELECT * FROM `intakes` WHERE `programmes_programme_id` = '" . $leadData["programme_id"] . "' ORDER BY `intake_id` ASC");
                                                                    while ($data = $intakesResult->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $data["intake_id"]; ?>"><?php echo $data["intake"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>

                                                                </select>
                                                                <span class="text-dark-red text-12" id="intake-feedback"></span>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <div class="row" id="student-documents-wrapper">
                                                            <span class="sub-topic mb-3">Student Required Documents</span>
                                                            <!-- AIC Application -->
                                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                <div class="student-doc-upload-box" data-doc-key="birth_certificate">
                                                                    <div class="doc-upload-header">
                                                                        <span class="doc-title">Birth Certificates</span>
                                                                        <!-- <span class="doc-required">Required</span> -->
                                                                    </div>

                                                                    <div class="doc-drop-area">
                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                        <span class="doc-drop-text">Drag & Drop file here</span>
                                                                        <span class="doc-or-text">OR</span>

                                                                        <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                            Browse File
                                                                        </button>

                                                                        <input type="file"
                                                                            class="d-none student-doc-input"
                                                                            id="doc-birth-certificate"
                                                                            data-doc-key="birth_certificate"
                                                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                    </div>

                                                                    <div class="doc-preview mt-2"></div>
                                                                    <span class="text-dark-red text-12 doc-feedback" id="doc-birth-certificate-feedback"></span>
                                                                </div>
                                                            </div>

                                                            <!-- Passport / NIC -->
                                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                <div class="student-doc-upload-box" data-doc-key="nic_passport">
                                                                    <div class="doc-upload-header">
                                                                        <span class="doc-title">NIC / Passport</span>
                                                                        <!-- <span class="doc-required">Required</span> -->
                                                                    </div>

                                                                    <div class="doc-drop-area">
                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                        <span class="doc-drop-text">Drag & Drop file here</span>
                                                                        <span class="doc-or-text">OR</span>

                                                                        <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                            Browse File
                                                                        </button>

                                                                        <input type="file"
                                                                            class="d-none student-doc-input"
                                                                            id="doc-nic-passport"
                                                                            data-doc-key="nic_passport"
                                                                            accept=".pdf,.jpg,.jpeg,.png">
                                                                    </div>

                                                                    <div class="doc-preview mt-2"></div>
                                                                    <span class="text-dark-red text-12 doc-feedback" id="doc-nic-passport-feedback"></span>
                                                                </div>
                                                            </div>

                                                            <!-- Academic Documents -->
                                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                <div class="student-doc-upload-box" data-doc-key="academic_documents">
                                                                    <div class="doc-upload-header">
                                                                        <span class="doc-title">Academic Documents</span>
                                                                        <!-- <span class="doc-required">Required</span> -->
                                                                    </div>

                                                                    <div class="doc-drop-area">
                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                        <span class="doc-drop-text">Drag & Drop files here</span>
                                                                        <span class="doc-or-text">OR</span>

                                                                        <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                            Browse Files
                                                                        </button>

                                                                        <input type="file"
                                                                            class="d-none student-doc-input"
                                                                            id="doc-academic-documents"
                                                                            data-doc-key="academic_documents"
                                                                            multiple
                                                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                    </div>

                                                                    <div class="doc-preview mt-2"></div>
                                                                    <span class="text-dark-red text-12 doc-feedback" id="doc-academic-documents-feedback"></span>
                                                                </div>
                                                            </div>

                                                            <!-- Academic Documents -->
                                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                <div class="student-doc-upload-box" data-doc-key="certificates">
                                                                    <div class="doc-upload-header">
                                                                        <span class="doc-title">Certificates</span>
                                                                        <!-- <span class="doc-required">Required</span> -->
                                                                    </div>

                                                                    <div class="doc-drop-area">
                                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                        <span class="doc-drop-text">Drag & Drop files here</span>
                                                                        <span class="doc-or-text">OR</span>

                                                                        <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                            Browse Files
                                                                        </button>

                                                                        <input type="file"
                                                                            class="d-none student-doc-input"
                                                                            id="doc-certificates"
                                                                            data-doc-key="certificates"
                                                                            multiple
                                                                            accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                    </div>

                                                                    <div class="doc-preview mt-2"></div>
                                                                    <span class="text-dark-red text-12 doc-feedback" id="doc-certificates-feedback"></span>
                                                                </div>
                                                            </div>


                                                        </div>
                                                    </div>

                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <div class="row gx-4 gy-3">
                                                            <span class="sub-topic">Gurdian Details</span>
                                                            <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Guardian Relationship</label>
                                                                <select name="" id="grelationship-selector" class="form-select custom-select mt-2">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    $guardianRelationships = Database::search("SELECT * FROM `guardian_relation`");
                                                                    while ($data = $guardianRelationships->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $data["guardian_relation_id"]; ?>"><?php echo $data["guardian_relation"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="grelationship-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                            <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Title</label>
                                                                <select name="" id="gtitle-selector" class="form-select custom-select mt-2">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    foreach ($titles as $title) {
                                                                    ?>
                                                                        <option value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                    <?php

                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="gtitle-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">First Name</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="guardian-fname" maxlength="45">
                                                                <span id="gfname-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Last Name</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="guardian-lname" maxlength="45">
                                                                <span id="glname-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Email</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="guardian-email" maxlength="100">
                                                                <span id="gemail-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Mobile</label>
                                                                <input type="text" class="form-control custom-input mt-2" id="guardian-mobile" maxlength="10">
                                                                <span id="gmobile-feedback" class="text-dark-red text-12"></span>
                                                            </div>

                                                        </div>
                                                    </div>

                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <div class="row gx-4 gy-3">
                                                            <span class="sub-topic">Registration Payment</span>

                                                            <div class="col-12">
                                                                <div class="reg-payment-section">

                                                                    <!-- Toggle row -->
                                                                    <div class="reg-payment-toggle-row">
                                                                        <div class="reg-payment-toggle-left">
                                                                            <label class="reg-payment-switch">
                                                                                <input type="checkbox" id="reg-payment-enabled" onchange="toggleRegPaymentSection(this)">
                                                                                <span class="reg-payment-slider"></span>
                                                                            </label>
                                                                            <div class="reg-payment-toggle-text">
                                                                                <span class="reg-payment-label">Enable Registration Payment</span>
                                                                                <span class="reg-payment-sublabel">Require a registration fee for this student</span>
                                                                            </div>
                                                                        </div>

                                                                    </div>

                                                                    <!-- Collapsible fields -->
                                                                    <div class="reg-payment-amount-wrap" id="reg-payment-fields">
                                                                        <div class="reg-payment-amount-inner">
                                                                            <div class="reg-payment-amount-row">
                                                                                <div class="row gx-4 gy-3">

                                                                                    <!-- Amount -->
                                                                                    <div class="col-12 col-md-4 d-flex flex-column">
                                                                                        <label class="reg-payment-amount-label">
                                                                                            Registration Amount
                                                                                            <span class="reg-required-star">*</span>
                                                                                        </label>
                                                                                        <select name="" id="reg-payment-amount" class="form-select custom-select mt-2" onchange="changeRegPaymentAMount(this.value)">
                                                                                            <option value="0">- Select -</option>
                                                                                            <option value="25000">25000</option>
                                                                                            <option value="30000">30000</option>
                                                                                            <option value="Other">Other</option>
                                                                                        </select>
                                                                                        <span class="text-dark-red text-12 mt-1" id="reg-payment-amount-feedback"></span>

                                                                                        <div class="reg-amount-input-group mt-2 d-none" id="custom-reg-amount-area">
                                                                                            <span class="reg-amount-currency">LKR</span>
                                                                                            <input type="number"
                                                                                                id="custom-reg-payment-amount"
                                                                                                class="custom-input"
                                                                                                placeholder="0.00"
                                                                                                min="0.01"
                                                                                                step="0.01">
                                                                                        </div>
                                                                                        <span class="text-dark-red text-12 mt-1" id="custom-reg-payment-amount-feedback"></span>

                                                                                        <label class="reg-payment-amount-label mt-2">
                                                                                            Note

                                                                                        </label>
                                                                                        <textarea name="" id="note" class="form-control custom-text-area mt-2"></textarea>
                                                                                    </div>

                                                                                    <!-- Payment Slip -->
                                                                                    <div class="col-12 col-md-8 d-flex flex-column">
                                                                                        <div class="student-doc-upload-box" data-doc-key="payment_slip">
                                                                                            <div class="doc-upload-header">
                                                                                                <span class="doc-title">Registration Payment Slip</span>
                                                                                            </div>

                                                                                            <div class="doc-drop-area">
                                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                                <span class="doc-drop-text">Drag & Drop file here</span>
                                                                                                <span class="doc-or-text">OR</span>

                                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn">
                                                                                                    Browse File
                                                                                                </button>

                                                                                                <input type="file"
                                                                                                    class="d-none student-doc-input"
                                                                                                    id="doc-payment-slip"
                                                                                                    data-doc-key="payment_slip"
                                                                                                    accept=".pdf,.jpg,.jpeg,.png">
                                                                                            </div>

                                                                                            <div class="doc-preview mt-2"></div>
                                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-payment-slip-feedback"></span>
                                                                                        </div>
                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                            <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                <button class="custom-btn w-100" data-i18n="save-student" onclick="convertLeadToStudent(this,<?= $id ?>)">Save Student</button>
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

                    <div id="crop-modal" tabindex="-1" aria-hidden="true" class="modal fade" data-backdrop="static">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Crop Image</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <img id="image" class="img-fluid" />
                                </div>
                                <div class="modal-footer">
                                    <button id="crop-button" type="button" class="custom-btn px-5 pe-5">Crop</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                    <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                    <script src="./assets/js/fileChooser.js?v=<?= Project::getVersion() ?>"></script>
                </body>
                </body>


                </html>
<?php
            }
        }
    }
}
