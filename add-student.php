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

        if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('students', 2))) {
            header("Location: ./students");
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
                <title>Add Student - Admin Dashboard | <?= Project::getBaseName(); ?></title>

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
                            load_side_menu::load("students");
                            ?>
                        </div>
                        <div id="app-wrapper" class="app-wrapper p-0 m-0">
                            <div class="row p-0 m-0 g-0">
                                <div class="col-12 p-0 m-0 g-0">
                                    <?php
                                    include "./includes/header.php";
                                    selectItem::setSelected("students");
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
                                                            <button class="custom-btn w-100" data-i18n="save-student" onclick="addStudent(this)">Save Student</button>
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
                                                                            <input type="text" class="form-control custom-input mt-2" id="full-name" maxlength="150">
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
                                                            <input type="text" class="form-control custom-input mt-2" id="email" maxlength="100">
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
                                                            <input type="text" class="form-control custom-input mt-2" id="address" maxlength="100">
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


                                                <div class="col-12 p-0 g-0 mt-4">
                                                    <span class="sub-topic">Physical Documents Received</span>
                                                    <div class="row mt-3" id="physical-documents-wrapper">
                                                        <?php
                                                        $docTypes = Database::search("SELECT * FROM `students_identity_documents_type` 
                                                        WHERE `students_identity_documents_type_id` IN (1,2,4) 
                                                        ORDER BY `students_identity_documents_type_id` ASC");
                                                        while ($type = $docTypes->fetch_assoc()) {
                                                            $typeId = $type['students_identity_documents_type_id'];
                                                        ?>
                                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                <div class="form-check bl-solid p-3 d-flex align-items-start gap-2">
                                                                    <input class="form-check-input mt-1 physical-doc-checkbox-new" type="checkbox"
                                                                        value="<?= $typeId ?>" id="physical-doc-new-<?= $typeId ?>">
                                                                    <label class="form-check-label w-100 text-14 main-text" for="physical-doc-new-<?= $typeId ?>">
                                                                        <?= htmlspecialchars($type['students_identity_documents_type']) ?>
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        <?php } ?>
                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-3">
                                                    <span class="sub-topic">Required Documents</span>

                                                    <div class="row mt-3" id="student-documents-wrapper">

                                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                            <div class="student-doc-upload-box" data-doc-key="birth_certificate">
                                                                <input type="hidden" id="keep_birth_certificate" value="1">
                                                                <div class="doc-upload-header">
                                                                    <span class="doc-title">Birth Certificate</span>
                                                                </div>
                                                                <div class="doc-drop-area">
                                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                    <span class="doc-drop-text">Drag & Drop file here</span>
                                                                    <span class="doc-or-text">OR</span>
                                                                    <button type="button" class="custom-btn px-3 doc-browse-btn">Browse File</button>
                                                                    <input type="file" class="d-none student-doc-input" id="doc-birth-certificate" data-doc-key="birth_certificate" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                </div>
                                                                <div class="doc-preview mt-2"></div>
                                                                <span class="text-dark-red text-12 doc-feedback" id="doc-birth-certificate-feedback"></span>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                            <div class="student-doc-upload-box" data-doc-key="nic_passport">
                                                                <input type="hidden" id="keep_nic_passport" value="1">
                                                                <div class="doc-upload-header">
                                                                    <span class="doc-title">NIC / Passport</span>
                                                                </div>
                                                                <div class="doc-drop-area">
                                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                    <span class="doc-drop-text">Drag & Drop file here</span>
                                                                    <span class="doc-or-text">OR</span>
                                                                    <button type="button" class="custom-btn px-3 doc-browse-btn">Browse File</button>
                                                                    <input type="file" class="d-none student-doc-input" id="doc-nic-passport" data-doc-key="nic_passport" accept=".pdf,.jpg,.jpeg,.png">
                                                                </div>
                                                                <div class="doc-preview mt-2"></div>
                                                                <span class="text-dark-red text-12 doc-feedback" id="doc-nic-passport-feedback"></span>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                            <div class="student-doc-upload-box" data-doc-key="academic_documents">
                                                                <input type="hidden" id="keep_academic_documents" value="1">
                                                                <div class="doc-upload-header">
                                                                    <span class="doc-title">Academic Documents</span>
                                                                </div>
                                                                <div class="doc-drop-area">
                                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                    <span class="doc-drop-text">Drag & Drop files here</span>
                                                                    <span class="doc-or-text">OR</span>
                                                                    <button type="button" class="custom-btn px-3 doc-browse-btn">Browse Files</button>
                                                                    <input type="file" class="d-none student-doc-input" id="doc-academic-documents" data-doc-key="academic_documents" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                </div>
                                                                <div class="doc-preview mt-2"></div>
                                                                <span class="text-dark-red text-12 doc-feedback" id="doc-academic-documents-feedback"></span>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                            <div class="student-doc-upload-box" data-doc-key="certificates">
                                                                <div class="doc-upload-header">
                                                                    <span class="doc-title">Certificates</span>
                                                                </div>
                                                                <div class="doc-drop-area">
                                                                    <img src="assets/imgs/icons/cloud.png" alt="cloud" width="42">
                                                                    <span class="doc-drop-text">Drag & Drop files here</span>
                                                                    <span class="doc-or-text">OR</span>
                                                                    <button type="button" class="custom-btn px-3 doc-browse-btn">Browse Files</button>
                                                                    <input type="file" class="d-none student-doc-input" id="doc-certificates" data-doc-key="certificates" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                </div>
                                                                <div class="doc-preview mt-2"></div>
                                                                <span class="text-dark-red text-12 doc-feedback" id="doc-certificates-feedback"></span>
                                                            </div>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-12 p-0 g-0 mt-5">
                                                    <div class="row gx-4 gy-3">
                                                        <span class="sub-topic">Advanced Profile Details</span>
                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Faculty</label>
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
                                                                    <option value="<?php echo $data["faculty_id"]; ?>"><?php echo $data["faculty"]; ?></option>
                                                                <?php
                                                                }
                                                                ?>
                                                            </select>
                                                            <span class="text-dark-red text-12" id="faculty-feedback"></span>
                                                        </div>
                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Programme</label>
                                                            <?php
                                                            $programmesResult = Database::search("SELECT * FROM `programmes` ORDER BY `programme_id` ASC");
                                                            $programmes = [];
                                                            while ($data = $programmesResult->fetch_assoc()) {
                                                                $programmes[] = $data;
                                                            }
                                                            ?>
                                                            <select id="programme-selector" disabled class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span class="text-dark-red text-12" id="programme-feedback"></span>

                                                        </div>
                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Intake</label>
                                                            <?php
                                                            $intakesResult = Database::search("SELECT * FROM `intakes` ORDER BY `intake_id` ASC");
                                                            $intakes = [];
                                                            while ($data = $intakesResult->fetch_assoc()) {
                                                                $intakes[] = $data;
                                                            }
                                                            ?>
                                                            <select id="intake-selector" disabled class="form-select custom-select mt-2">
                                                                <option value="0">- Select -</option>
                                                            </select>
                                                            <span class="text-dark-red text-12" id="intake-feedback"></span>
                                                        </div>

                                                        <div class="col-12 col-md-3 d-flex flex-column">
                                                            <label class="text-14 main-text" for="">Assign Counselor</label>
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
                                                    <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                        <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                            <button class="custom-btn w-100" data-i18n="save-student" onclick="addStudent(this);">Save Student</button>
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
                <script>
                    // Wire-up notes:
                    // 1. Include this script alongside your other page scripts.
                    // 2. On the student profile page, call loadPhysicalDocuments(<studentId>)
                    //    once on load, e.g. add it to the page's existing onload chain:


                    function loadPhysicalDocuments(studentId) {
                        fetch("api/getStudentPhysicalDocuments.php?student-id=" + studentId)
                            .then((res) => res.json())
                            .then((response) => {
                                if (response.error) {
                                    showToast("error", response.error);
                                    return;
                                }
                                renderPhysicalDocuments(studentId, response.documents);
                            })
                            .catch(() => {
                                showToast("error", "Could not load physical documents.");
                            });
                    }

                    function renderPhysicalDocuments(studentId, documents) {
                        const wrapper = document.getElementById("physical-documents-list");
                        wrapper.innerHTML = "";

                        documents.forEach((doc) => {
                            const col = document.createElement("div");
                            col.className = "col-12 col-md-6 col-lg-4 mb-3 physical-doc-item";

                            const checkedAttr = doc.is_received ? "checked" : "";
                            const metaText = (doc.is_received && doc.marked_by) ?
                                "Received " + (doc.received_date ? formatPhysicalDocDate(doc.received_date) : "") + " by " + doc.marked_by :
                                "Not yet received";

                            col.innerHTML = `
            <div class="form-check bl-solid p-3 d-flex align-items-start gap-2">
                <input class="form-check-input mt-1 physical-doc-checkbox" type="checkbox"
                    id="physical-doc-${doc.type_id}" data-type-id="${doc.type_id}" data-student-id="${studentId}" ${checkedAttr}>
                <label class="form-check-label w-100" for="physical-doc-${doc.type_id}">
                    <span class="text-14 main-text d-block">${doc.type_name}</span>
                    <span class="text-12 sub-text physical-doc-meta">${metaText}</span>
                </label>
            </div>
        `;

                            wrapper.appendChild(col);
                        });

                        document.querySelectorAll(".physical-doc-checkbox").forEach((checkbox) => {
                            checkbox.addEventListener("change", function() {
                                togglePhysicalDocument(this);
                            });
                        });
                    }

                    function togglePhysicalDocument(checkbox) {
                        const studentId = checkbox.dataset.studentId;
                        const typeId = checkbox.dataset.typeId;
                        const isReceived = checkbox.checked ? 1 : 0;

                        checkbox.disabled = true;

                        const form = new FormData();
                        form.append("student_id", studentId);
                        form.append("type_id", typeId);
                        form.append("is_received", isReceived);

                        fetch("api/togglePhysicalDocument.php", {
                                method: "POST",
                                body: form,
                            })
                            .then((res) => res.json())
                            .then((response) => {
                                checkbox.disabled = false;
                                if (response.done) {
                                    loadPhysicalDocuments(studentId);
                                } else if (response.error) {
                                    checkbox.checked = !checkbox.checked;
                                    showToast("error", response.error);
                                }
                            })
                            .catch(() => {
                                checkbox.disabled = false;
                                checkbox.checked = !checkbox.checked;
                                showToast("error", "An error occured. Please try again later.");
                            });
                    }

                    function formatPhysicalDocDate(dateStr) {
                        const d = new Date(dateStr.replace(" ", "T"));
                        return d.toLocaleDateString("en-GB", {
                            day: "2-digit",
                            month: "short",
                            year: "numeric"
                        });
                    }
                </script>
            </body>

            </html>
<?php
        }
    }
}
?>