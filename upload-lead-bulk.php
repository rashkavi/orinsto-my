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
                <title>Upload Lead Bulk - Admin Dashboard | <?= Project::getBaseName() ?></title>

                <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">

                <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
                <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
            </head>

            <body class="overflow-hidden">
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

                                <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100 d-none" id="real-list">
                                    <div class="row p-4 m-0 g-0" id="accordionParent">
                                        <div class="col-12 px-4 py-3 component-theme mb-4">
                                            <div class="row p-0 m-0 g-0">
                                                <div class="col-12">
                                                    <div class="row">
                                                        <span class="main-topic lh-lg">Upload Lead Bulk</span>
                                                        <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                            <ol class="breadcrumb m-0">
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./students'>New Leads</a></li>
                                                                <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer">Upload lead bulk</a></li>
                                                            </ol>
                                                        </nav>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-12 component-theme p-4">
                                            <div class="row p-0 m-0">
                                                <div class="col-12 bl-solid p-0 m-0 d-flex flex-column">
                                                    <div class="p-2 d-flex flex-column align-items-center justify-content-center w-100 h-auto cursor-pointer mt-2" id="drop-section">
                                                        <img src="assets/imgs/icons/cloud.png" alt="cloud">
                                                        <span>Drag & Drop your files here</span>
                                                        <span>OR</span>
                                                        <div class="col-12 mt-3">
                                                            <div class="row gap-3 align-items-center justify-content-center">
                                                                <button class="custom-btn px-4 col-4" onclick="downloadLeadFormatCSV()">Download CSV Format</button>
                                                                <button class="custom-btn px-4 col-4" id="file-selector">Browse Files</button>
                                                            </div>
                                                        </div>

                                                        <input type="file" class="d-none" id="file-selector-input" accept=".csv">

                                                    </div>
                                                    <div class="col-12 p-4 d-none" id="list-section-container">
                                                        <div class="row">
                                                            <div class="col-12">
                                                                <div class="row" id="list-section">

                                                                </div>
                                                            </div>
                                                            <div class="col-12 mt-3">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox"
                                                                        id="preventDuplicateCheckbox">
                                                                    <label class="form-check-label" for="preventDuplicateCheckbox">
                                                                        Prevent duplicate leads (based on email or mobile number)
                                                                    </label>
                                                                </div>
                                                            </div>
                                                            <div class="col-12">
                                                                <div class="row justify-content-end">
                                                                    <div class="col-12 col-md-2">
                                                                        <button class="custom-btn w-100" type="button" onclick="uploadLeadBulk(this);">
                                                                            Upload Bulk
                                                                        </button>
                                                                    </div>

                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="text-12 text-dark-red p-2" id="csv-file-feedback"></span>
                                                </div>

                                                <div class="container p-0 mt-4">
                                                    <div class="bulk-instructions-header">
                                                        <h4 class="sub-topic-color">Lead Upload Instructions</h4>
                                                        <p class="bulk-instructions-subtitle">Follow these guidelines to ensure successful student data import</p>
                                                    </div>

                                                    <div class="bulk-instructions-note p-3">
                                                        <div class="bulk-instructions-note-title text-16 fw-semibold mb-1">
                                                            Important Upload Guidelines
                                                        </div>

                                                        <p class="text-14 mt-1">
                                                            <strong class="text-danger">All fields must be completed accurately.</strong></br>
                                                            Ensure your CSV file adheres to the required field structure, value types, and date formats provided in the instructions.
                                                        </p>

                                                        <p class="text-14 mt-2">
                                                            During validation, the system will automatically detect missing information, invalid formats, and improperly structured records.
                                                            Rows containing errors will not be processed, so please review your file thoroughly before uploading.
                                                        </p>
                                                    </div>
                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Text Field Requirements</h2>
                                                        <p class="bulk-instructions-section-content">Please follow the character limits when entering lead details:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li><strong>Full Name:</strong> Maximum 100 characters (Required)</li>
                                                            <li><strong>Address:</strong> Maximum 150 characters</li>
                                                            <li><strong>School:</strong> Maximum 100 characters</li>
                                                            <li><strong>Grade:</strong> Maximum 20 characters</li>
                                                            <li><strong>Note:</strong> Maximum 255 characters</li>
                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Notes:</strong><br>
                                                            - Full Name and Mobile Number are mandatory fields.<br>
                                                            - Avoid unnecessary special characters and symbols.<br>
                                                            - Exceeding character limits will generate row-level errors.<br>
                                                        </p>
                                                    </div>


                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Contact Information</h2>
                                                        <p class="bulk-instructions-section-content">Ensure valid contact details are provided:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li><strong>Email:</strong> Must be in valid format (example@email.com)</li>
                                                            <li><strong>Mobile:</strong> Required and must be a valid phone number</li>
                                                            <li><strong>WhatsApp:</strong> Optional but must be valid if provided</li>
                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Notes:</strong><br>
                                                            - Invalid phone numbers will cause validation errors.<br>
                                                            - Duplicate or incorrectly formatted numbers may be rejected.<br>
                                                        </p>
                                                    </div>


                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Reference IDs</h2>
                                                        <p class="bulk-instructions-section-content">Use correct numeric IDs for Gender, Programme, and Lead Source:</p>

                                                        <div class="bulk-instructions-grid-container">


                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Counselor / SRO IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $sources = Database::search("SELECT * FROM users 
                                                                     WHERE `user_roles_user_role_id` IN (7,8,11,12,21) 
                                                                        AND `status_status_id`='1'");
                                                                    while ($source = $sources->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $source["user_id"]; ?> - <?= $source["user_fname"] . " " . $source["user_lname"]; ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            </div>

                                                            <!-- Programme -->
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Programme IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $programmes = Database::search("SELECT * FROM programmes ORDER BY programme_id ASC");
                                                                    while ($programme = $programmes->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $programme["programme_id"]; ?> - <?= $programme["programme"]; ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            </div>

                                                            <!-- Lead Source -->
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Lead Source IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $sources = Database::search("SELECT * FROM lead_source ORDER BY lead_source_id ASC");
                                                                    while ($source = $sources->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $source["lead_source_id"]; ?> - <?= $source["lead_source"]; ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            </div>

                                                            <!-- Lead Source -->
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Database Institutes</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $sources = Database::search("SELECT * FROM institutes ORDER BY institute_id ASC");
                                                                    while ($source = $sources->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $source["institute_id"]; ?> - <?= $source["institute"]; ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            </div>
                                                            <!-- Lead Source -->
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Database Institutes has Branches</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <?php
                                                                    $sources = Database::search("SELECT * FROM institute_has_branches 
                                                                    INNER JOIN `institutes` ON `institute_has_branches`.`institutes_institute_id`=`institutes`.`institute_id`
                                                                    ORDER BY institute_id ASC");
                                                                    while ($source = $sources->fetch_assoc()) {
                                                                    ?>
                                                                        <li><?= $source["institute_has_branch_id"]; ?> - <?= $source["institute"] . " " . $source["institute_has_branch"]; ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            </div>

                                                            <!-- Gender -->
                                                            <div class="bulk-instructions-grid-item">
                                                                <div class="bulk-instructions-grid-title">Gender IDs</div>
                                                                <ul class="bulk-instructions-list">
                                                                    <li>1 - Male</li>
                                                                    <li>2 - Female</li>
                                                                </ul>
                                                            </div>

                                                        </div>

                                                        <p class="bulk-instructions-section-content text-danger mt-2">
                                                            <strong>Notes:</strong><br>
                                                            - Only numeric IDs should be entered in the CSV (e.g., 1, 2, 3).<br>
                                                            - Do NOT enter names like "Male" or "Facebook".<br>
                                                            - Invalid IDs will cause row rejection.<br>
                                                        </p>
                                                    </div>


                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Date of Birth Format</h2>
                                                        <p class="bulk-instructions-section-content">Accepted formats:</p>

                                                        <div class="bulk-instructions-grid-container">
                                                            <div class="bulk-instructions-grid-item">
                                                                <ul class="bulk-instructions-list">
                                                                    <li><strong>2025/11/02</strong> (YYYY/MM/DD)</li>
                                                                    <li><strong>2025/11/2</strong> (YYYY/M/D)</li>
                                                                    <li><strong>02/11/2025</strong> (DD/MM/YYYY)</li>
                                                                    <li><strong>2/11/2025</strong> (D/M/YYYY)</li>
                                                                </ul>
                                                            </div>
                                                        </div>

                                                        <p class="bulk-instructions-section-content text-danger mt-2">
                                                            <strong>Notes:</strong><br>
                                                            - Use "/" as separator.<br>
                                                            - Year must be 4 digits.<br>
                                                            - Invalid dates will cause row-level errors.<br>
                                                        </p>
                                                    </div>

                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Email Address Requirements</h2>
                                                        <p class="bulk-instructions-section-content">
                                                            Please ensure that all student and guardian email addresses follow the correct format. Invalid email formats will cause row-level validation errors during bulk upload.
                                                        </p>

                                                        <ul class="bulk-instructions-list">
                                                            <li>Email must contain an <strong>@</strong> symbol (e.g., example@gmail.com)</li>
                                                            <li>Email must include a valid domain name (e.g., gmail.com, yahoo.com, outlook.com)</li>
                                                            <li>No spaces or illegal characters allowed</li>
                                                            <li>Maximum length: <strong>100 characters</strong></li>
                                                        </ul>


                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Notes:</strong><br>
                                                            - Email address must be unique for each student.<br>
                                                            - Exceeding character limits or using invalid characters will generate a row-level validation error.
                                                        </p>
                                                    </div>

                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">Mobile Number & WhatsApp Requirements</h2>
                                                        <p class="bulk-instructions-section-content">Allowed phone number formats:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li><strong>Sri Lankan Mobile Format:</strong> Must start with <strong>07</strong> and contain <strong>10 digits</strong>
                                                                <br>Example: 0712345678
                                                            </li>

                                                            <li><strong>International Format (without +):</strong> 8–15 digits, starts with 1–9
                                                                <br>Example: 769679778 → will be converted automatically to <strong>0769679778</strong>
                                                            </li>

                                                            <li><strong>International Format (with +):</strong> + followed by country code and number
                                                                <br>Example: +94712345678
                                                            </li>

                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger"><strong>Notes:</strong><br>
                                                            - Only mobile numbers are allowed — no landline numbers.<br>
                                                            - Minimum digits: <strong>8</strong>, Maximum digits: <strong>15</strong>.<br>
                                                            - Mobile number & WhatsApp must be unique for each student.
                                                        </p>
                                                    </div>


                                                    <div class="bulk-instructions-section">
                                                        <h2 class="bulk-instructions-section-title">CSV Column Order</h2>
                                                        <p class="bulk-instructions-section-content">Ensure your CSV follows this exact column order:</p>

                                                        <ul class="bulk-instructions-list">
                                                            <li>1. Full Name</li>
                                                            <li>2. Date of Birth</li>
                                                            <li>3. Gender (ID)</li>
                                                            <li>4. Address</li>
                                                            <li>5. Email</li>
                                                            <li>6. Mobile</li>
                                                            <li>7. WhatsApp</li>
                                                            <li>8. School</li>
                                                            <li>9. Grade</li>
                                                            <li>10. College</li>
                                                            <li>11. Programme (ID)</li>
                                                            <li>12. Lead Source (ID)</li>
                                                            <li>13. Database Institute (ID)</li>
                                                            <li>14. Database Institute Branch (ID)</li>
                                                            <li>15. Note</li>
                                                            <li>16. Assigned Counsilor ID</li>
                                                        </ul>

                                                        <p class="bulk-instructions-section-content text-danger">
                                                            <strong>Important:</strong><br>
                                                            - Do NOT change column order.<br>
                                                            - Missing columns will break upload processing.<br>
                                                        </p>
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

                <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/chart.js?v=<?= Project::getVersion() ?>"></script>
                <script src="./assets/js/bulkFileChooser.js?v=<?= Project::getVersion() ?>"></script>
            </body>

            </html>
<?php

        }
    }
} ?>