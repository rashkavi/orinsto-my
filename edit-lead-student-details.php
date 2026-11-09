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
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id']))) {
            header("Location: ./students");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            $hasPermission = false;
            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('leads') || UserPermissions::hasPermission('students', 1) || UserPermissions::hasPermission('students', 10))) {
                header("Location: ./students");
            } else {
                $id = $_GET['student-id'];

                $student = Database::search("SELECT 
            `students`.*,
            `login_details`.*,
            `title`.*,
            `programmes`.*,
            `faculties`.*,
            `students_has_intakes`.*,
            `students_has_intakes`.`student_id` AS `siid`,
                `students`.`student_id` AS `sId` FROM `students` 
                INNER JOIN `login_details` ON `students`.`login_details_login_details_id`=`login_details`.`login_details_id`
                INNER JOIN `title` ON `students`.`title_title_id`=`title`.`title_id`
                INNER JOIN `students_has_intakes` ON `students`.`student_id`=`students_has_intakes`.`students_student_id`
                INNER JOIN `leads` ON `students_has_intakes`.`leads_lead_id`=`leads`.`lead_id`
                INNER JOIN `programmes` ON `leads`.`programmes_programme_id`=`programmes`.`programme_id`
                INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id`=`faculties`.`faculty_id`
                WHERE `students_has_intakes`.`students_has_intakes_id`='" . $id . "'");

                if ($student->num_rows == 0) {
                    header("Location: ./students");
                } else {
                    $sdata = $student->fetch_assoc();

                    $sAddress = Database::search("SELECT * FROM `student_has_address` 
                    INNER JOIN `city` ON `student_has_address`.`city_city_id`=`city`.`city_id`
                    INNER JOIN `district` ON `city`.`district_district_id`=`district`.`district_id`
                    INNER JOIN `province` ON `district`.`province_province_id`=`province`.`province_id`
                    WHERE `students_student_id`='" . $sdata['sId'] . "'");
                    $sAddressData = $sAddress->fetch_assoc();

                    $sGuardian = Database::search("SELECT * FROM `guardian_details` 
                    WHERE `students_student_id`='" . $sdata['sId'] . "'");
                    $sGuardianData = $sGuardian->fetch_assoc();

                    $hasAddress = $sAddress->num_rows == 1 ? true : false;
                    $hasGuardian = $sGuardian->num_rows == 1 ? true : false;

                    $fieldsDisabled = false;

                    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('leads') || UserPermissions::hasPermission('students', 10))) {
                        $fieldsDisabled = true;
                    }

                    $countries = array();
                    $countries_rs = Database::search("SELECT * FROM `countries`");
                    while ($data = $countries_rs->fetch_assoc()) {
                        array_push($countries, [
                            'id' => $data['country_id'],
                            'name' => $data['country_name']
                        ]);
                    }

                    $existingDocuments = [
                        "birth_certificate" => [],
                        "nic_passport" => [],
                        "academic_documents" => [],
                        "certificates" => [],
                        "payment_slips" => [],
                    ];

                    $documentTypeMap = [
                        1 => [
                            "key" => "birth_certificate",
                            "folder" => "birth-certificates",
                        ],
                        2 => [
                            "key" => "nic_passport",
                            "folder" => "nic-documents",
                        ],
                        3 => [
                            "key" => "academic_documents",
                            "folder" => "academic-documents",
                        ],
                        4 => [
                            "key" => "certificates",
                            "folder" => "certificates",
                        ],
                        5 => [
                            "key" => "payment_slip",
                            "folder" => "payment-slips",
                        ],
                    ];

                    $existingDocRs = Database::search("
                        SELECT *
                        FROM `students_identity_documents`
                        WHERE `students_student_id` = '" . (int)$sdata['sId'] . "'
                        ORDER BY `students_identity_documents_type_id` ASC, `date_added` DESC
                    ");

                    while ($doc = $existingDocRs->fetch_assoc()) {
                        $typeId = (int)$doc["students_identity_documents_type_id"];

                        if (!isset($documentTypeMap[$typeId])) {
                            continue;
                        }

                        $docKey = $documentTypeMap[$typeId]["key"];
                        $folder = $documentTypeMap[$typeId]["folder"];

                        $documentUrl = "assets/uploads/student-identity-documents/" . $folder . "/" . $doc["document"];
                        if (!file_exists("./" . $documentUrl)) {
                            $documentUrl = Project::getBasePortalUrl() . "/assets/uploads/student-identity-documents/" . $folder . "/" . $doc["document"];
                        }

                        $existingDocuments[$docKey][] = [
                            "id" => $doc["students_identity_document_id"],
                            "name" => $doc["document"],
                            "url" => $documentUrl,
                            "date_added" => $doc["date_added"],
                            "access_remove" => true
                        ];
                    }

                    $isUploadRegistrationPaymentSlip = Database::search("SELECT `course_payment_slips`.*,`course_payments`.*,`course_payment_has_payments`.`date_paid` FROM `course_payment_slips`
                    INNER JOIN `course_payment_has_payments` ON `course_payment_slips`.`course_payment_has_payment_id`=`course_payment_has_payments`.`course_payment_has_payment_id`
                    INNER JOIN `course_payments` ON `course_payment_has_payments`.`course_payments_course_payments_id`=`course_payments`.`course_payments_id`
                    WHERE `students_has_intakes_students_has_intakes_id`='" . $sdata['students_has_intakes_id'] . "' AND `payment_type_payment_type_id`='18'");

                    $isCompleteRegistrationPayment = false;

                    if ($isUploadRegistrationPaymentSlip->num_rows > 0) {
                        while ($paymentSlip = $isUploadRegistrationPaymentSlip->fetch_assoc()) {
                            $isCompleteRegistrationPayment = true;
                            $documentUrl = "assets/uploads/payment-slips/" . $paymentSlip["slip_name"];
                            if (!file_exists("./" . $documentUrl)) {
                                $documentUrl = Project::getBasePortalUrl() . "/assets/uploads/payment-slips/" . $paymentSlip["slip_name"];
                            }

                            $existingDocuments["payment_slip"][] = [
                                "id" => $paymentSlip["course_payment_slip_id"],
                                "name" => $paymentSlip["slip_name"],
                                "url" => $documentUrl,
                                "date_added" => $paymentSlip["date_paid"],
                                "access_remove" => false
                            ];
                        }
                    }


                    $existingDocumentsJson = json_encode($existingDocuments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>Student Details - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                    </head>

                    <body class="overflow-hidden" onload="initializeUpdateStudentDocumentUploads();">
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
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?php echo $sdata['title'] . "." . $sdata['first_name'] . " " . $sdata['last_name']; ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./inquiries.php">All Leads</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./lead-view.php?lead-id=<?= $sdata['leads_lead_id'] ?>">Lead View</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="" href="./lead-registered-student-details.php?lead-id=<?= $sdata['leads_lead_id'] ?>">Lead Registered Student</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" data-i18n="">Edit Student Details</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>

                                                        <div class="col-12 col-md-12 col-lg-2 mt-3 mt-lg-0">
                                                            <div class="row gx-3 justify-content-end">
                                                                <?php

                                                                if (SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('leads') || UserPermissions::hasPermission('leads', 10)) {
                                                                ?>
                                                                    <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                        <button class="custom-btn col-12" onclick="editLeadStudent(this,'<?php echo $id ?>');">Save Changes</button>
                                                                    </div>
                                                                <?php
                                                                }
                                                                ?>
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
                                                                        <div class="row m-0 p-0 position-relative justify-content-center align-content-center" id="user-profile-image-container">
                                                                            <?php
                                                                            if (!empty($sdata["profile_img"]) && file_exists('./assets/imgs/students/' . $sdata["profile_img"])) {
                                                                            ?>
                                                                                <img src="<?php echo './assets/imgs/students/' . $sdata["profile_img"]; ?>" alt="student profile image" id="student-profile-image" class="w-100 h-auto mx-2 my-0 p-0">
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <img src="./assets/imgs/common/empty-img.png" alt="user profile image" id="student-profile-image" class="w-100 h-auto mx-2 my-0 p-0">
                                                                            <?php
                                                                            }


                                                                            ?>
                                                                            <div class="upload-image text-white p-2 bottom-0 start-0" onclick="uploadImage('file-input','student-profile-image');">
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

                                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">First Name</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="first-name" maxlength="45" value="<?php echo $sdata['first_name']; ?>">
                                                                                    <span id="fname-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Last Name</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="last-name" maxlength="45" value="<?php echo $sdata['last_name']; ?>">
                                                                                    <span id="lname-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-7 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Full Name</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="full-name" maxlength="150" value="<?php echo $sdata['full_name'] ?>">
                                                                                    <span id="fullname-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-5 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Name With Initials</label>
                                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="name-with-initials" maxlength="45" value="<?php echo $sdata['name_with_initials']; ?>">
                                                                                    <span id="nwi-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Select Title</label>
                                                                                    <select class="form-select custom-select mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> aria-label="Default select example" id="title-selector">
                                                                                        <?php
                                                                                        $titleResult = Database::search("SELECT * FROM `title`");
                                                                                        $titles = [];
                                                                                        while ($data = $titleResult->fetch_assoc()) {
                                                                                            $titles[] = $data;
                                                                                        }
                                                                                        foreach ($titles as $title) {
                                                                                            if ($title['title_id'] == $sdata['title_id']) {
                                                                                        ?>
                                                                                                <option selected value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <option value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                                        <?php
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                    <span id="title-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Select Gender</label>
                                                                                    <select class="form-select custom-select mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> aria-label="Default select example" id="gender-selector">
                                                                                        <?php
                                                                                        $genders = Database::search("SELECT * FROM `gender`");
                                                                                        while ($data = $genders->fetch_assoc()) {
                                                                                            if ($data['gender_id'] == $sdata['gender_gender_id']) {
                                                                                        ?>
                                                                                                <option selected value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <option value="<?php echo $data["gender_id"]; ?>"><?php echo $data["gender"]; ?></option>
                                                                                        <?php
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                    <span id="gender-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Select Marital Status</label>
                                                                                    <select class="form-select custom-select mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> aria-label="Default select example" id="marital-status-selector">
                                                                                        <?php
                                                                                        $marital_status = Database::search("SELECT * FROM `marital_status`");
                                                                                        while ($data = $marital_status->fetch_assoc()) {
                                                                                            if ($data['marital_status_id'] == $sdata['marital_status_marital_status_id']) {
                                                                                        ?>
                                                                                                <option selected value="<?php echo $data["marital_status_id"]; ?>"><?php echo $data["marital_status"]; ?></option>
                                                                                            <?php
                                                                                            } else {
                                                                                            ?>
                                                                                                <option value="<?php echo $data["marital_status_id"]; ?>"><?php echo $data["marital_status"]; ?></option>
                                                                                        <?php
                                                                                            }
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                    <span id="marital-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text d-inline-flex align-items-center" for="">
                                                                                        NIC
                                                                                        <span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="National Id Card Number">
                                                                                            <iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon>
                                                                                        </span>
                                                                                    </label>
                                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="nic" maxlength="12" value="<?php echo $sdata['nic']; ?>" onkeypress="checkValidChar(event)">
                                                                                    <span id="nic-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Date of Birth</label>
                                                                                    <input type="date" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="dob" value="<?php echo $sdata['date_of_birth']; ?>">
                                                                                    <span id="dob-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Nationality</label>
                                                                                    <select class="form-select custom-select mt-2" id="nationality" <?= $fieldsDisabled ? "disabled" : "" ?>>
                                                                                        <?php
                                                                                        foreach ($countries as $country) {
                                                                                        ?>
                                                                                            <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == $sdata['nationality'] ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                    <span id="nationality-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Country of Birth</label>
                                                                                    <select class="form-select custom-select mt-2" id="country-of-birth" <?= $fieldsDisabled ? "disabled" : "" ?>>
                                                                                        <?php
                                                                                        foreach ($countries as $country) {
                                                                                        ?>
                                                                                            <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == $sdata['country_of_birth'] ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select>
                                                                                    <span id="cob-feedback" class="text-dark-red text-12"></span>
                                                                                </div>
                                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                                    <label class="text-14 main-text required" for="">Country of Residence</label>
                                                                                    <select class="form-select custom-select mt-2" id="country-of-residence" onchange="changeCountryOfResidence(this.value)" <?= $fieldsDisabled ? "disabled" : "" ?>>
                                                                                        <?php
                                                                                        foreach ($countries as $country) {
                                                                                        ?>
                                                                                            <option value="<?php echo $country["id"]; ?>" <?= $country["id"] == $sdata['country_of_residence'] ? "selected" : "" ?>><?php echo $country["name"]; ?></option>
                                                                                        <?php
                                                                                        }
                                                                                        ?>
                                                                                    </select> <span id="cor-feedback" class="text-dark-red text-12"></span>
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
                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="email" maxlength="100" value="<?php echo $sdata['email']; ?>">
                                                                    <span id="email-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Mobile</label>
                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="mobile" value="<?php echo $sdata['mobile']; ?>">
                                                                    <span id="mobile-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text required" for="">Whatsapp</label>
                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="whatsapp" value="<?php echo $sdata['whatsapp']; ?>">
                                                                    <span id="whatsapp-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Address Line 1</label>
                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="address" maxlength="100" value="<?php echo $hasAddress ? $sAddressData['address_line_1'] : ''; ?>">
                                                                    <span id="address-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Address Line 2</label>
                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="address2" maxlength="100" value="<?php echo $hasAddress ? $sAddressData['address_line_2'] : ''; ?>">
                                                                    <span id="address2-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Province</label>
                                                                    <select name="" id="province" class="form-select custom-select mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> onchange="loadDistrict();">
                                                                        <option value="0">-Select Province-</option>
                                                                        <?php
                                                                        $province = Database::search("SELECT * FROM `province`");
                                                                        while ($data = $province->fetch_assoc()) {
                                                                            if ($hasAddress && $sAddressData['province_id'] == $data['province_id']) {
                                                                        ?>
                                                                                <option selected value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                                            <?php
                                                                            } else {
                                                                            ?>
                                                                                <option value="<?php echo $data["province_id"]; ?>"><?php echo $data["province"]; ?></option>
                                                                        <?php
                                                                            }
                                                                        }
                                                                        ?>
                                                                    </select>
                                                                    <span id="province-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">District</label>
                                                                    <select name="" id="district" class="form-select custom-select mt-2" disabled onchange="loadCities();">
                                                                        <?php if ($hasAddress): ?>
                                                                            <option value="<?= $sAddressData['district_id'] ?>">
                                                                                <?= $sAddressData['district'] ?>
                                                                            </option>
                                                                        <?php else: ?>
                                                                            <option value="0">-Select District-</option>
                                                                        <?php endif; ?>
                                                                    </select>
                                                                    <span id="district-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">City</label>
                                                                    <select name="" id="city" class="form-select custom-select mt-2" disabled>
                                                                        <?php if ($hasAddress): ?>
                                                                            <option value="<?= $sAddressData['city_id'] ?>">
                                                                                <?= $sAddressData['city'] ?>
                                                                            </option>
                                                                        <?php else: ?>
                                                                            <option value="0">-Select City-</option>
                                                                        <?php endif; ?>
                                                                    </select>
                                                                    <span id="city-feedback" class="text-dark-red text-12"></span>
                                                                </div>
                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Postal Code</label>
                                                                    <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="postal-code" maxlength="5" value="<?php echo $hasAddress ? $sAddressData['postal_code'] : ''; ?>">
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Documents -->
                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <span class="sub-topic">Academic Verification Documents</span>

                                                        <div class="row mt-5" id="student-documents-wrapper">

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

                                                            <div class="col-12 col-md-6 col-lg-4 mb-3">
                                                                <div class="student-doc-upload-box" data-doc-key="payment_slip">
                                                                    <div class="doc-upload-header">
                                                                        <span class="doc-title">Registration Payment Slip</span>
                                                                    </div>

                                                                    <div class="doc-drop-area <?= $isCompleteRegistrationPayment ? 'd-none' : ''  ?>">
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
                                                                        <option value="<?php echo $data["faculty_id"]; ?>" <?= $sdata["faculty_id"] == $data["faculty_id"] ? 'selected' : ''; ?>><?php echo $data["faculty"]; ?></option>
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
                                                                <select id="programme-selector" <?= !empty($sdata["programme_id"]) ? '' : 'disabled'  ?> class="form-select custom-select mt-2" onchange="loadIntakes();">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    if (!empty($sdata["programme_id"])) {
                                                                    ?>
                                                                        <option value="<?php echo $sdata["programme_id"]; ?>" selected><?php echo $sdata["programme"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="text-dark-red text-12" id="programme-feedback"></span>
                                                            </div>
                                                            <div class="col-12 col-md-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Intake</label>

                                                                <select id="intake-selector" <?= !empty($sdata["programme_id"]) ? '' : 'disabled'  ?> class="form-select custom-select mt-2">
                                                                    <option value="0">- Select -</option>
                                                                    <?php
                                                                    $intakesResult = Database::search("SELECT * FROM `intakes` WHERE `programmes_programme_id` = '" . $sdata["programme_id"] . "' ORDER BY `intake_id` ASC");
                                                                    while ($data = $intakesResult->fetch_assoc()) {
                                                                    ?>
                                                                        <option value="<?php echo $data["intake_id"]; ?>" <?= $sdata["intakes_intake_id"] == $data["intake_id"] ? 'selected' : ''; ?>><?php echo $data["intake"]; ?></option>
                                                                    <?php
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span class="text-dark-red text-12" id="intake-feedback"></span>
                                                            </div>
                                                            <?php
                                                            if ($sdata["intakes_intake_id"] != null) {
                                                            ?>
                                                                <div class="col-12 col-md-3 d-flex flex-column">
                                                                    <label class="text-14 main-text" for="">Registration Number</label>
                                                                    <input type="text" class="form-control custom-input  mt-2" readonly value="<?= $sdata['siid'] ?>">
                                                                </div>
                                                            <?php
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 p-0 g-0 mt-5">
                                                        <div class="row gx-4 gy-3">
                                                            <span class="sub-topic">Gurdian Details</span>
                                                            <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Guardian Relationship</label>
                                                                <select name="" id="grelationship-selector" class="form-select custom-select mt-2" <?= $fieldsDisabled ? "disabled" : "" ?>>
                                                                    <option value="0">- Select Relationship -</option>
                                                                    <?php
                                                                    $guardianRelationships = Database::search("SELECT * FROM `guardian_relation`");
                                                                    while ($data = $guardianRelationships->fetch_assoc()) {
                                                                        if ($hasGuardian && $sGuardianData["guardian_relation_guardian_relation_id"] == $data["guardian_relation_id"]) {
                                                                    ?>
                                                                            <option selected value="<?php echo $data["guardian_relation_id"]; ?>"><?php echo $data["guardian_relation"]; ?></option>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <option value="<?php echo $data["guardian_relation_id"]; ?>"><?php echo $data["guardian_relation"]; ?></option>
                                                                    <?php
                                                                        }
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="grelationship-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Title</label>
                                                                <select name="" id="gtitle-selector" class="form-select custom-select mt-2" <?= $fieldsDisabled ? "disabled" : "" ?>>
                                                                    <option value="0">- Select Title -</option>
                                                                    <?php
                                                                    foreach ($titles as $title) {
                                                                        if ($hasGuardian && $sGuardianData['title_title_id'] == $title['title_id']) {
                                                                    ?>
                                                                            <option selected value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                        <?php
                                                                        } else {
                                                                        ?>
                                                                            <option value="<?php echo $title["title_id"]; ?>"><?php echo $title["title"]; ?></option>
                                                                    <?php
                                                                        }
                                                                    }
                                                                    ?>
                                                                </select>
                                                                <span id="gtitle-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-xxl-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">First Name</label>
                                                                <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="guardian-fname" maxlength="45" value="<?php echo $hasGuardian ? $sGuardianData['first_name'] : ''; ?>">
                                                                <span id="gfname-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Last Name</label>
                                                                <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="guardian-lname" maxlength="45" value="<?php echo $hasGuardian ? $sGuardianData['last_name'] : ''; ?>">
                                                                <span id="glname-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Email</label>
                                                                <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="guardian-email" maxlength="100" value="<?php echo $hasGuardian ? $sGuardianData['email'] : ''; ?>">
                                                                <span id="gemail-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                <label class="text-14 main-text" for="">Mobile</label>
                                                                <input type="text" class="form-control custom-input mt-2" <?= $fieldsDisabled ? "disabled" : "" ?> id="guardian-mobile" maxlength="10" value="<?php echo $hasGuardian ? $sGuardianData['mobile'] : ''; ?>">
                                                                <span id="gmobile-feedback" class="text-dark-red text-12"></span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <?php
                                                    if (SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 10)) {
                                                    ?>
                                                        <div class="col-12 p-0 g-0 mt-5">
                                                            <div class="row p-0 m-0 gx-4 gy-3 justify-content-end">
                                                                <div class="col-12 col-md-6 col-lg-4 p-0 px-md-3 pe-md-0 m-0">
                                                                    <button class="custom-btn w-100" onclick="editLeadStudent(this,'<?php echo $id ?>');">Save Changes</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php
                                                    }

                                                    ?>

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

                        <script>
                            window.EXISTING_STUDENT_DOCUMENTS = <?= $existingDocumentsJson ?>;
                        </script>
                        <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                        <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                        <script src="./assets/js/fileChooser.js"></script>
                    </body>

                    </html>
<?php

                }
            }
        }
    }
}
?>