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
        header("Location: ./dashboard");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['contract-id']) && !empty($_GET['contract-id']))) {
            header("Location: ./users");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('users', 1) || UserPermissions::hasPermission('users', 10))) {
                header("Location: ./users");
            } else {
                require_once "./includes/connection.php";
                $id = $_GET['contract-id'];
                $user = Database::search("SELECT 
                    *, COUNT(`lecturers`.`lecturer_contracts_lecturer_contract_id`) AS contract_count FROM `lecturer_contracts` 
                    INNER JOIN `payment_cycle` ON `lecturer_contracts`.`payment_cycle_payment_cycle_id`=`payment_cycle`.`payment_cycle_id`
                    INNER JOIN `lecturers` ON `lecturer_contracts`.`lecturer_contract_id`=`lecturers`.`lecturer_contracts_lecturer_contract_id`
                    INNER JOIN `users` ON `lecturer_contracts`.`users_user_id`=`users`.`user_id`
                    INNER JOIN `title` ON `users`.`title_title_id`=`title`.`title_id`
                        WHERE `lecturer_contracts`.`lecturer_contract_id`='" . $id . "'");
                if ($user->num_rows == 0) {
                    header("Location: ./users");
                } else {
                    $contractData = $user->fetch_assoc();
?>
                    <!DOCTYPE html>
                    <html lang="en">

                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <meta name="robots" content="noindex, nofollow">
                        <title>View Lecturer Contract - Admin Dashboard | <?= Project::getBaseName() ?></title>

                        <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                        <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                        <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                        <link rel="stylesheet" href="./assets/plugins/richtexteditor/rte_theme_default.css" />

                        <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                        <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                        <script type="text/javascript" src="./assets/plugins/richtexteditor/rte.js"></script>
                        <script type="text/javascript" src='./assets/plugins/richtexteditor/plugins/all_plugins.js'></script>
                        <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                        <style>
                            .subject-accordion-header {
                                display: flex;
                                align-items: stretch;
                                border: 1px solid var(--line-color);
                                background-color: var(--custom-white);
                            }

                            .subject-accordion-toggle {
                                flex: 1;
                                min-height: 48px;
                                background-color: var(--custom-white) !important;
                                box-shadow: none !important;
                            }

                            .subject-accordion-toggle::after {
                                margin-left: 0;
                                margin-right: 18px;
                                order: -1;
                            }

                            .subject-header-content {
                                display: flex;
                                flex-direction: column;
                                align-items: flex-start;
                                line-height: 1.3;
                            }

                            .subject-header-delete-btn {
                                width: 48px;
                                border: none;
                                border-left: 1px solid var(--line-color);
                                background-color: var(--custom-white);
                                color: var(--dark-red);
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            }

                            .subject-header-delete-btn:hover {
                                background-color: var(--light-red);
                                color: var(--dark-red);
                            }
                        </style>
                    </head>

                    <body class="overflow-hidden">
                        <div class="notifications"></div>
                        <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                            <div id="appContainer" class="row p-0">
                                <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                    <?php
                                    include "includes/side-menu.php";
                                    load_side_menu::load("lecturers");
                                    ?>
                                </div>
                                <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                    <div class="row p-0 m-0 g-0">
                                        <div class="col-12 p-0 m-0 g-0">
                                            <?php
                                            include "./includes/header.php";
                                            selectItem::setSelected("lecturers");
                                            ?>
                                        </div>
                                        <div class="container p-4 m-0" id="skeleton-list">
                                            <?php
                                            include "./includes/components/skeleton-header.php";
                                            include "./includes/components/skeleton-add-edit.php"; ?>
                                        </div>
                                        <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                            <div class="row p-4 m-0 g-0">
                                                <div class="col-12 px-4 py-3 component-theme mb-4">
                                                    <div class="row p-0 m-0 g-0">
                                                        <div class="col-12 col-md-10">
                                                            <div class="row">
                                                                <span class="main-topic lh-lg"><?= $contractData['title'] . "." . $contractData['user_fname'] . " " . $contractData['user_lname']; ?></span>
                                                                <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                    <ol class="breadcrumb m-0">
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href="./lecturers">Lecturers</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">Contarcts</a></li>
                                                                        <li class="breadcrumb-item"><a class="sub-text text-decoration-none">View Contarct</a></li>
                                                                    </ol>
                                                                </nav>
                                                            </div>
                                                        </div>
                                                        <!-- <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                            <button class="custom-btn w-100" type="button" onclick="createLecturerContract(this, <?= $id ?>)">
                                                                Save Contract
                                                            </button>
                                                        </div> -->
                                                    </div>
                                                </div>
                                                <div class="col-12 p-4 component-theme">
                                                    <div class="row p-0 m-0 align-content-center">
                                                        <div class="col-12 p-0 g-0">

                                                            <div class="row gx-4 gy-3">

                                                                <!-- CONTRACT HEADER -->
                                                                <div class="col-12 mt-4">
                                                                    <span class="sub-topic">Contract Header</span>
                                                                    <p class="text-14 sub-text mb-0 mt-1">
                                                                        Define the lecturer contract period and main contract information.
                                                                    </p>
                                                                </div>

                                                                <div class="col-12 d-flex flex-column">
                                                                    <label class="text-14 main-text required">Contract Title</label>
                                                                    <input type="text"
                                                                        class="form-control custom-input mt-2"
                                                                        id="contract-title"
                                                                        maxlength="100"
                                                                        placeholder="Example: Lecturer Service Agreement - 2026" value="<?= $contractData['contract_title'] ?>">
                                                                    <span id="contract-title-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required">Contract Start Date</label>
                                                                    <input type="date"
                                                                        class="form-control custom-input mt-2"
                                                                        id="contract-start-date" value="<?= $contractData['contract_start_date'] ?>">
                                                                    <span id="contract-start-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required">Contract End Date</label>
                                                                    <input type="date"
                                                                        class="form-control custom-input mt-2"
                                                                        id="contract-end-date" value="<?= $contractData['contract_end_date'] ?>">
                                                                    <span id="contract-end-date-feedback" class="text-dark-red text-12"></span>
                                                                </div>


                                                                <!-- CONTRACT TERMS -->
                                                                <div class="col-12 mt-5">
                                                                    <span class="sub-topic">Contract Terms</span>
                                                                    <p class="text-14 sub-text mb-0 mt-1">
                                                                        Define common payment and agreement conditions for this contract.
                                                                    </p>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text required">Payment Cycle</label>
                                                                    <select id="payment-cycle" class="form-select custom-select mt-2">
                                                                        <option value="monthly" <?= $contractData['payment_cycle_payment_cycle_id'] == 1 ? 'selected' : '' ?>>Monthly</option>
                                                                        <option value="after_session" <?= $contractData['payment_cycle_payment_cycle_id'] == 2 ? 'selected' : '' ?>>After Each Session</option>
                                                                        <option value="after_module" <?= $contractData['payment_cycle_payment_cycle_id'] == 3 ? 'selected' : '' ?>>After Module Completion</option>
                                                                        <option value="custom" <?= $contractData['payment_cycle_payment_cycle_id'] == 4 ? 'selected' : '' ?>>Custom</option>
                                                                    </select>
                                                                    <span id="payment-cycle-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text not-required">Tax Applicable</label>
                                                                    <select id="tax-applicable" class="form-select custom-select mt-2">
                                                                        <option value="0" <?= $contractData['tax_applicable'] == 0 ? 'selected' : '' ?>>No</option>
                                                                        <option value="1" <?= $contractData['tax_applicable'] == 1 ? 'selected' : '' ?>>Yes</option>
                                                                    </select>
                                                                    <span id="tax-applicable-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 col-md-4 d-flex flex-column">
                                                                    <label class="text-14 main-text not-required">Agreement Document</label>
                                                                    <input type="file"
                                                                        class="form-control custom-input mt-2"
                                                                        id="agreement-document"
                                                                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                                                    <span id="agreement-document-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <div class="col-12 d-flex flex-column">
                                                                    <label class="text-14 main-text">Contract Note</label>
                                                                    <textarea id="contract-note"
                                                                        class="form-control custom-input mt-2"
                                                                        rows="4"
                                                                        placeholder="Add internal notes, special terms, or payment remarks..."><?= $contractData['contract_note'] ?></textarea>
                                                                    <span id="contract-note-feedback" class="text-dark-red text-12"></span>
                                                                </div>

                                                                <!-- SUBJECTS -->

                                                                <div class="col-12 mt-5 d-flex justify-content-between align-items-center">
                                                                    <div>
                                                                        <span class="sub-topic">Subject Payment Terms</span>
                                                                        <p class="text-14 sub-text mb-0 mt-1">
                                                                            Add subject-wise teaching mode, lecture, assignment and examination payment rules.
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <div class="col-12 mt-3">
                                                                    <div class="accordion" id="lecturerContractSubjectAccordion">

                                                                        <?php
                                                                        $subjects = Database::search("SELECT * FROM `lecturers`
                                                                    INNER JOIN `subjects` ON `lecturers`.`subjects_subject_id` = `subjects`.`subject_id`
                                                                    INNER JOIN `levels` ON `subjects`.`levels_level_id` = `levels`.`level_id`
                                                                    INNER JOIN `intakes` ON `levels`.`intakes_intake_id` = `intakes`.`intake_id`
                                                                    INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                                                                    INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id` 
                                                                    WHERE `lecturer_contracts_lecturer_contract_id`='" . $contractData['lecturer_contract_id'] . "'");

                                                                        for ($i = 0; $i < $subjects->num_rows; $i++) {
                                                                            $subject = $subjects->fetch_assoc();
                                                                        ?>
                                                                            <div class="accordion-item br-0 lecturer-contract-subject-card" data-subject-index="<?= $i + 1 ?>">
                                                                                <h2 class="accordion-header subject-accordion-header" id="subject-heading-1">

                                                                                    <button class="accordion-button br-0 subject-accordion-toggle"
                                                                                        type="button"
                                                                                        data-bs-toggle="collapse"
                                                                                        data-bs-target="#subject-collapse-1"
                                                                                        aria-expanded="true"
                                                                                        aria-controls="subject-collapse-1">

                                                                                        <div class="subject-header-content">
                                                                                            <span class="text-14 fw-semibold main-text subject-card-title">
                                                                                                Subject <?= $i + 1 ?>
                                                                                            </span>

                                                                                            <span class="text-12 sub-text subject-card-summary">
                                                                                                No subject selected yet
                                                                                            </span>
                                                                                        </div>

                                                                                    </button>

                                                                                    <button type="button"
                                                                                        class="subject-header-delete-btn d-none remove-subject-btn"
                                                                                        onclick="removeLecturerContractSubject(this)"
                                                                                        title="Remove Subject">

                                                                                        <iconify-icon icon="solar:trash-bin-trash-bold"
                                                                                            width="16"
                                                                                            height="16"></iconify-icon>
                                                                                    </button>

                                                                                </h2>
                                                                                <div id="subject-collapse-1"
                                                                                    class="accordion-collapse collapse show"
                                                                                    aria-labelledby="subject-heading-1"
                                                                                    data-bs-parent="#lecturerContractSubjectAccordion">

                                                                                    <div class="accordion-body component-theme">
                                                                                        <div class="row gx-3 gy-3">

                                                                                            <div class="col-12">
                                                                                                <span class="text-14 fw-semibold sub-topic-color">
                                                                                                    Subject Path
                                                                                                </span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Faculty</label>
                                                                                                <select class="form-select custom-select mt-2 faculty-select"
                                                                                                    onchange="loadContractProgrammes(this); updateSubjectCardSummary(this);">
                                                                                                    <option value="0" selected disabled>- Select -</option>
                                                                                                    <?php
                                                                                                    $faculties = Database::search("SELECT * FROM faculties ORDER BY faculty ASC");
                                                                                                    while ($faculty = $faculties->fetch_assoc()) {
                                                                                                    ?>
                                                                                                        <option value="<?= $faculty['faculty_id'] ?>" <?= $subject['faculty_id'] == $faculty['faculty_id'] ? 'selected' : '' ?>>
                                                                                                            <?= $faculty['faculty'] ?>
                                                                                                        </option>
                                                                                                    <?php } ?>
                                                                                                </select>
                                                                                                <span class="faculty-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Programme</label>
                                                                                                <select class="form-select custom-select mt-2 programme-select" disabled
                                                                                                    onchange="loadContractIntakes(this); updateSubjectCardSummary(this);">
                                                                                                    <option value="<?= $subject['programme_id'] ?>" selected disabled><?= $subject['programme'] ?></option>
                                                                                                </select>
                                                                                                <span class="programme-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Intake</label>
                                                                                                <select class="form-select custom-select mt-2 intake-select" disabled
                                                                                                    onchange="loadContractLevels(this); updateSubjectCardSummary(this);">
                                                                                                    <option value="<?= $subject['intake_id'] ?>" selected disabled><?= $subject['intake'] ?></option>
                                                                                                </select>
                                                                                                <span class="intake-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Level</label>
                                                                                                <select class="form-select custom-select mt-2 level-select" disabled
                                                                                                    onchange="loadContractSubjects(this); updateSubjectCardSummary(this);">
                                                                                                    <option value="<?= $subject['level_id'] ?>" selected disabled><?= $subject['level'] ?></option>
                                                                                                </select>
                                                                                                <span class="level-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-8 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Subject</label>
                                                                                                <select class="form-select custom-select mt-2 subject-select" disabled
                                                                                                    onchange="updateSubjectCardSummary(this)">
                                                                                                    <option value="<?= $subject['subject_id'] ?>" selected disabled><?= $subject['subject'] ?></option>
                                                                                                </select>
                                                                                                <span class="subject-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-4 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Teaching Mode</label>
                                                                                                <select class="form-select custom-select mt-2 teaching-mode-select"
                                                                                                    onchange="updateSubjectCardSummary(this)">
                                                                                                    <option value="0">- Select -</option>
                                                                                                    <option value="physical" <?= $subject['lecturering_mode'] == 'PHYSICAL' ? 'selected' : '' ?>>Physical</option>
                                                                                                    <option value="online" <?= $subject['lecturering_mode'] == 'ONLINE' ? 'selected' : '' ?>>Online</option>
                                                                                                    <option value="hybrid" <?= $subject['lecturering_mode'] == 'HYBRID' ? 'selected' : '' ?>>Hybrid</option>
                                                                                                </select>
                                                                                                <span class="teaching-mode-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 mt-4">
                                                                                                <span class="text-14 fw-semibold sub-topic-color">
                                                                                                    Lecture Payment Terms
                                                                                                </span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Lecture Payment Type</label>
                                                                                                <select class="form-select custom-select mt-2 lecture-payment-type">
                                                                                                    <option value="0">- Select -</option>
                                                                                                    <option value="1" <?= $subject['lecture_payment _type_lecture_payment _type_id'] == 1 ? 'selected' : '' ?>>Per Hour</option>
                                                                                                    <option value="2" <?= $subject['lecture_payment _type_lecture_payment _type_id'] == 2 ? 'selected' : '' ?>>Per Day</option>
                                                                                                    <option value="3" <?= $subject['lecture_payment _type_lecture_payment _type_id'] == 3 ? 'selected' : '' ?>>Per Project</option>
                                                                                                    <option value="4" <?= $subject['lecture_payment _type_lecture_payment _type_id'] == 4 ? 'selected' : '' ?>>Fixed Amount</option>
                                                                                                </select>
                                                                                                <span class="lecture-payment-type-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                                                <label class="text-14 main-text required">Lecture Rate</label>
                                                                                                <input type="number" class="form-control custom-input mt-2 lecture-rate" min="0" value="<?= $subject['lecture_rate'] ?>">
                                                                                                <span class="lecture-rate-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                                                <label class="text-14 main-text not-required">Minimum Payable Hours</label>
                                                                                                <input type="number" class="form-control custom-input mt-2 minimum-payable-hours" min="0">
                                                                                                <span class="minimum-payable-hours-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 mt-4">
                                                                                                <span class="text-14 fw-semibold sub-topic-color">
                                                                                                    Assignment Payment Terms
                                                                                                </span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                                                <label class="text-14 main-text">Per Assignment Rate</label>
                                                                                                <input type="number" class="form-control custom-input mt-2 assignment-rate" min="0" value="<?= $subject['per_assignment_fee'] ?>">
                                                                                                <span class="assignment-rate-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 mt-4">
                                                                                                <span class="text-14 fw-semibold sub-topic-color">
                                                                                                    Examination Payment Terms
                                                                                                </span>
                                                                                            </div>

                                                                                            <div class="col-12 col-md-6 col-lg-4 d-flex flex-column">
                                                                                                <label class="text-14 main-text">Per Exam Rate</label>
                                                                                                <input type="number" class="form-control custom-input mt-2 exam-rate" min="0" value="<?= $subject['per_examination_fee'] ?>">
                                                                                                <span class="exam-rate-feedback text-dark-red text-12"></span>
                                                                                            </div>

                                                                                            <div class="col-12 d-flex flex-column mt-3">
                                                                                                <label class="text-14 main-text">Subject Payment Note</label>
                                                                                                <textarea class="form-control custom-input mt-2 subject-note"
                                                                                                    rows="3"
                                                                                                    placeholder="Add special subject-wise payment terms..."><?= $subject['payment_note'] ?></textarea>
                                                                                                <span class="subject-note-feedback text-dark-red text-12"></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php
                                                                        }
                                                                        ?>


                                                                    </div>
                                                                </div>

                                                                <!-- SAVE -->
                                                                <!-- <div class="col-12 d-flex justify-content-end mt-4">
                                                                    <button type="button"
                                                                        class="custom-btn px-5"
                                                                        onclick="addLecturerContractSubject()">
                                                                        Add New Subject
                                                                    </button>
                                                                </div> -->
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
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="crop-model-close"></button>
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