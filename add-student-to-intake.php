<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";
require_once "./includes/classes/StudentDocumentConfig.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['intake-id']) && !empty($_GET['intake-id']))) {
            header("Location: ./faculties");
        } else {

            $id = $_GET['intake-id'];
            $intake = Database::search("SELECT * FROM `intakes`
                                   
                                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
                                        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
                                        WHERE `intake_id` = '" . $id . "'");
            if ($intake->num_rows == 0) {
                header("Location: ./intake-details");
            } else {
                $intakeData = $intake->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 2, $id))) {
                    header("Location: ./intake-details.php?intake-id=" . $id);
                } else {
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
                        <title>Add Student to Intake - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileInit.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeAddStudent(),initializeStudentDocumentUploads();">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("course-overview");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("course-overview");
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
                                                    <div class="row p-0 m-0 g-0 align-items-center">
                                                        <div class="col-12 col-md-8">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg">Add Student</span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties'>Faculties</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./faculties?id=<?php echo $intakeData['faculties_faculty_id'] ?>'>...</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none" href="./intake-details?intake-id=<?= $id ?>"><?= $intakeData["intake"] ?></a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $intakeData["intake"] ?></a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-12 col-lg-4 mt-3 mt-lg-0">
                                                            <div class="row justify-content-end gx-3">
                                                                <div class="col-6 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" onclick="addStudentToIntake(this,<?= $id ?>);">Add Student</button>
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
                                                                    <span class="image-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-9 col-xl-9 col-xxl-10 px-0 px-md-4 pe-0 pe-md-0 pe-lg-0 d-block">
                                                                    <div class="row m-0 p-0">
                                                                        <div class="col-12 mt-3 m-md-0 m-lg-0 p-0">
                                                                            <div class="row gx-4 gy-4">
                                                                                <span class="sub-topic">Personal Details</span>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Select Title</label>
                                                                                    <select class="form-select custom-select mt-2" aria-label="Default select example" id="title-selector">
                                                                                        <option selected value="0">- Select Title -</option>
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

                                                                                <div class="col-12 col-lg-7 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Full Name</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" id="full-name">
                                                                                    <span id="fullname-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-5 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Name With Initials</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" id="name-with-initials">
                                                                                    <span id="nwi-feedback" class="text-dark-red text-12"></span>
                                                                                </div>


                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Select Gender</label>
                                                                                    <select class="form-select custom-select mt-2" aria-label="Default select example" id="gender-selector">
                                                                                        <option selected value="0">- Select Gender -</option>
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
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text not-required" for="">Select Marital Status<Title></Title></label>
                                                                                    <select class="form-select custom-select mt-2" aria-label="Default select example" id="marital-status-selector">
                                                                                        <option selected value="0">- Select Marital Status -</option>
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
                                                                                    <label class="text-14 main-text d-inline-flex align-items-center not-required gap-1" for="">
                                                                                        NIC
                                                                                        <span id="nic-info-wrapper"></span>
                                                                                    </label>
                                                                                    <input type="text" class="form-control custom-input mt-2" id="nic" maxlength="12">
                                                                                    <span id="nic-feedback" class="text-dark-red text-12"></span>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text not-required" for="">Passport Number</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" id="passport">
                                                                                    <span id="passport-feedback" class="text-dark-red text-12"></span>
                                                                                </div>

                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text not-required" for="">Date of Birth</label>
                                                                                    <input type="date" class="form-control custom-input mt-2" id="dob">
                                                                                    <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Nationality</label>
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
                                                                                    <label class="text-14 main-text required" for="">Country of Birth</label>
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
                                                                                    <label class="text-14 main-text required" for="">Country of Residence</label>
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
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Assign Counselor</label>
                                                                                    <select name="" id="user" class="form-select custom-select mt-2">
                                                                                        <option value="0" disabled>- Select -</option>
                                                                                        <?php
                                                                                        $query = "SELECT * FROM `users`
                                                                                                INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id` 
                                                                                                WHERE `user_roles_user_role_id` IN (11,12) AND `status_status_id`='1'";

                                                                                        $users = Database::search($query);

                                                                                        while ($user = $users->fetch_assoc()) {

                                                                                        ?>
                                                                                            <option value="<?php echo $user["user_id"]; ?>"><?php echo $user["title"] . '.' . $user["user_fname"] . ' ' . $user["user_lname"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                    <span id="user-feedback" class="text-dark-red text-12"></span>
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
                                                                    <input type="text" class="form-control custom-input mt-2" id="email">
                                                                    <span id="email-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Mobile</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="mobile">
                                                                    <span id="mobile-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Whatsapp</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="whatsapp">
                                                                    <span id="whatsapp-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Address Line 1</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="address">
                                                                    <span id="address-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Address Line 2</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="address2">
                                                                    <span id="address2-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Province</label>
                                                                    <select name="" id="province" class="form-select custom-select mt-2" onchange="loadDistrict();">
                                                                        <option value="0">-Select Province-</option>
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
                                                                        <option value="0">-Select District-</option>
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
                                                                        <option value="0">-Select City-</option>
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
                                                                    <input type="text" class="form-control custom-input mt-2" id="postal-code">
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 p-0 g-0 mt-4">
                                                            <span class="sub-topic">Physical Documents Received</span>
                                                            <div class="row mt-3" id="physical-documents-wrapper">
                                                                <?php
                                                                $docTypes = Database::search("SELECT * FROM `students_identity_documents_type` 
                                                                INNER JOIN `programmes_has_identity_documents` ON `programmes_has_identity_documents`.`students_identity_documents_type_id`=`students_identity_documents_type`.`students_identity_documents_type_id`
                                                                WHERE `programmes_programme_id`='" . (int)$intakeData['programme_id'] . "'
                                                                ORDER BY `students_identity_documents_type`.`students_identity_documents_type_id` ASC");
                                                                while ($type = $docTypes->fetch_assoc()) {
                                                                    $typeId = $type['students_identity_documents_type_id'];
                                                                    $typeName = $type['students_identity_documents_type'];
                                                                    // Types flagged date_required = 1 (e.g. Agreement) show a signing date when ticked
                                                                    $needsSigningDate = !empty($type['date_required']);
                                                                    // Form field sent to the API, e.g. "Agreement" -> agreement_date
                                                                    $dateFieldName = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($typeName)), '_') . '_date';
                                                                ?>
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="form-check bl-solid p-3 d-flex align-items-start gap-2">
                                                                            <input class="form-check-input mt-1 physical-doc-checkbox-new" type="checkbox"
                                                                                value="<?= $typeId ?>" id="physical-doc-new-<?= $typeId ?>"
                                                                                <?php if ($needsSigningDate) { ?>
                                                                                data-date-wrapper="physical-doc-new-<?= $typeId ?>-date-wrapper"
                                                                                onchange="togglePhysicalDocDate(this)"
                                                                                <?php } ?>>
                                                                            <label class="form-check-label w-100 text-14 main-text" for="physical-doc-new-<?= $typeId ?>">
                                                                                <?= htmlspecialchars($typeName) ?>
                                                                            </label>
                                                                        </div>
                                                                        <?php if ($needsSigningDate) { ?>
                                                                            <div class="d-none flex-column mt-2" id="physical-doc-new-<?= $typeId ?>-date-wrapper">
                                                                                <label class="text-14 main-text required" for="physical-doc-new-<?= $typeId ?>-date"><?= htmlspecialchars($typeName) ?> Signing Date</label>
                                                                                <input type="date" class="form-control custom-input mt-2 physical-doc-date"
                                                                                    id="physical-doc-new-<?= $typeId ?>-date" data-field="<?= $dateFieldName ?>">
                                                                                <span class="text-dark-red text-12" id="doc-<?= str_replace('_', '-', $dateFieldName) ?>-feedback"></span>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </div>
                                                                <?php } ?>
                                                                <script>
                                                                    // Show / hide the signing date when a date-required document is ticked
                                                                    function togglePhysicalDocDate(checkbox) {
                                                                        const wrapper = document.getElementById(checkbox.dataset.dateWrapper);
                                                                        if (!wrapper) return;
                                                                        wrapper.classList.toggle('d-none', !checkbox.checked);
                                                                        wrapper.classList.toggle('d-flex', checkbox.checked);
                                                                        if (!checkbox.checked) {
                                                                            const input = wrapper.querySelector('input');
                                                                            if (input) input.value = '';
                                                                        }
                                                                    }
                                                                </script>
                                                            </div>
                                                        </div>

                                                        <?php
                                                        // ($intakeData is already loaded by the page's intake query, which joins `programmes`.)
                                                        $studentDocumentUploads = getProgrammeStudentDocumentConfigs($intakeData['programme_id']);

                                                        // Agreement upload box (type id 10): add it if the programme uses the Agreement type

                                                        ?>
                                                        <script>
                                                            // Read by studentDocuments JS (see note: the JS file must not redeclare this with const)
                                                            window.REQUIRED_STUDENT_DOCUMENTS = <?= json_encode(array_column($studentDocumentUploads, "key")) ?>;
                                                        </script>
                                                        <div class="col-12 p-0 g-0 mt-3">
                                                            <span class="sub-topic">Required Documents</span>

                                                            <div class="row mt-3" id="student-documents-wrapper">
                                                                <?php if (empty($studentDocumentUploads)) { ?>
                                                                    <div class="col-12">
                                                                        <span class="sub-text text-14">No required documents are configured for this programme.</span>
                                                                    </div>
                                                                <?php } ?>

                                                                <?php foreach ($studentDocumentUploads as $docUpload) {
                                                                    $docKey = $docUpload["key"];
                                                                    $docDomId = str_replace("_", "-", $docKey); // birth_certificate -> birth-certificate
                                                                    $isMultiple = $docUpload["multiple"];
                                                                ?>
                                                                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                        <div class="student-doc-upload-box" data-doc-key="<?= $docKey ?>">
                                                                            <?php if ($docUpload["keep"]) { ?>
                                                                                <input type="hidden" id="keep_<?= $docKey ?>" value="1">
                                                                            <?php } ?>
                                                                            <div class="doc-upload-header">
                                                                                <span class="doc-title"><?= htmlspecialchars($docUpload["title"]) ?></span>
                                                                            </div>
                                                                            <div class="doc-drop-area">
                                                                                <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                                <span class="doc-drop-text">Drag & Drop <?= $isMultiple ? "files" : "file" ?> here</span>
                                                                                <span class="doc-or-text">OR</span>
                                                                                <button type="button" class="custom-btn px-3 doc-browse-btn"><?= $isMultiple ? "Browse Files" : "Browse File" ?></button>
                                                                                <input type="file" class="d-none student-doc-input" id="doc-<?= $docDomId ?>" data-doc-key="<?= $docKey ?>" <?= $isMultiple ? "multiple" : "" ?> accept="<?= $docUpload["accept"] ?>">
                                                                            </div>
                                                                            <div class="doc-preview mt-2"></div>
                                                                            <span class="text-dark-red text-12 doc-feedback" id="doc-<?= $docDomId ?>-feedback"></span>
                                                                        </div>
                                                                    </div>
                                                                <?php } ?>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 p-0 g-0 mt-5">
                                                            <div class="row gx-4 gy-3">
                                                                <span class="sub-topic">Gurdian Details</span>
                                                                <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Guardian Relationship</label>
                                                                    <select name="" id="grelationship-selector" class="form-select custom-select mt-2">
                                                                        <option value="0">- Select Relationship -</option>
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
                                                                        <option value="0">- Select Title -</option>
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
                                                                    <input type="text" class="form-control custom-input mt-2" id="guardian-fname">
                                                                    <span id="gfname-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Last Name</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="guardian-lname">
                                                                    <span id="glname-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Email</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="guardian-email">
                                                                    <span id="gemail-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Mobile</label>
                                                                    <input type="text" class="form-control custom-input mt-2" id="guardian-mobile">
                                                                    <span id="gmobile-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                            </div>
                                                        </div>

                                                        <div class="col-12 p-0 g-0 mt-5">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="custom-btn w-100" onclick="addStudentToIntake(this,<?= $id ?>);">Add Student</button>
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
                        <script src="./assets/js/InfoTooltipManager.js?v=<?= Project::getVersion() ?>"></script>
                    </body>

                    </html>
<?php

                }
            }
        }
    }
}
?>