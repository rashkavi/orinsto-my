<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            $user = SessionUser::getCurrentUserObject();
            $updatedBy = (int)$user["user_id"];

            // ── Input collection ──────────────────────────────────────────────
            $contractId     = isset($_POST["contract_id"])         ? (int)$_POST["contract_id"]                : 0;
            $contractTitle  = trim($_POST["contract_title"]        ?? "");
            $startDate      = trim($_POST["contract_start_date"]   ?? "");
            $endDate        = trim($_POST["contract_end_date"]     ?? "");
            $contractStatus = trim($_POST["contract_status"]       ?? "");
            $paymentCycle   = trim($_POST["payment_cycle"]         ?? "");
            $taxApplicable  = ($_POST["tax_applicable"]            ?? "0") == "1" ? "YES" : "NO";
            $contractNote   = trim($_POST["contract_note"]         ?? "");
            $subjects       = json_decode($_POST["subjects"]       ?? "[]", true);

            $response      = new stdClass();
            $errors        = [];
            $subjectErrors = [];

            // ── Header validation ─────────────────────────────────────────────
            if ($contractId <= 0) {
                $errors["contract_id"] = "Invalid contract";
            }

            // Verify the contract actually exists
            if ($contractId > 0) {
                $contractCheck = Database::search("SELECT `lecturer_contract_id` FROM `lecturer_contracts` WHERE `lecturer_contract_id`='" . $contractId . "'");
                if ($contractCheck->num_rows == 0) {
                    $errors["contract_id"] = "Contract not found";
                }
            }

            if ($contractTitle === "") {
                $errors["contract_title"] = "Contract title is required";
            }

            if ($startDate === "") {
                $errors["contract_start_date"] = "Contract start date is required";
            }

            if ($endDate === "") {
                $errors["contract_end_date"] = "Contract end date is required";
            }

            if ($startDate !== "" && $endDate !== "" && strtotime($endDate) < strtotime($startDate)) {
                $errors["contract_end_date"] = "End date must be after start date";
            }

            $allowedStatuses = ["draft", "active", "pending_approval", "inactive"];
            if (!in_array($contractStatus, $allowedStatuses)) {
                $errors["contract_status"] = "Contract status is required";
            }

            $paymentCycleMap = [
                "monthly"      => 1,
                "after_session" => 2,
                "after_module" => 3,
                "custom"       => 4
            ];

            if (!isset($paymentCycleMap[$paymentCycle])) {
                $errors["payment_cycle"] = "Payment cycle is required";
            }

            // ── Subject validation ────────────────────────────────────────────
            if (!is_array($subjects) || count($subjects) == 0) {
                $subjectErrors[] = [
                    "index"    => 1,
                    "input"    => ".subject-select",
                    "feedback" => ".subject-feedback",
                    "message"  => "At least one subject is required"
                ];
            }

            $allowedTeachingModes = ["physical", "online", "hybrid"];

            foreach ($subjects as $subject) {
                $index      = (int)($subject["index"]              ?? 1);
                $facultyId  = (int)($subject["faculty_id"]         ?? 0);
                $programmeId = (int)($subject["programme_id"]       ?? 0);
                $intakeId   = (int)($subject["intake_id"]          ?? 0);
                $levelId    = (int)($subject["level_id"]           ?? 0);
                $subjectId  = (int)($subject["subject_id"]         ?? 0);
                $teachingMode       = strtolower(trim($subject["teaching_mode"]       ?? ""));
                $lecturePaymentType = trim($subject["lecture_payment_type"]           ?? "");
                $lectureRate        = (float)($subject["lecture_rate"]               ?? 0);

                if ($facultyId <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".faculty-select",
                        "feedback" => ".faculty-feedback",
                        "message" => "Faculty is required"
                    ];
                }
                if ($programmeId <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".programme-select",
                        "feedback" => ".programme-feedback",
                        "message" => "Programme is required"
                    ];
                }
                if ($intakeId <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".intake-select",
                        "feedback" => ".intake-feedback",
                        "message" => "Intake is required"
                    ];
                }
                if ($levelId <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".level-select",
                        "feedback" => ".level-feedback",
                        "message" => "Level is required"
                    ];
                }
                if ($subjectId <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".subject-select",
                        "feedback" => ".subject-feedback",
                        "message" => "Subject is required"
                    ];
                }
                if (!in_array($teachingMode, $allowedTeachingModes)) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".teaching-mode-select",
                        "feedback" => ".teaching-mode-feedback",
                        "message" => "Teaching mode is required"
                    ];
                }
                if (empty($lecturePaymentType) || (int)$lecturePaymentType <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".lecture-payment-type",
                        "feedback" => ".lecture-payment-type-feedback",
                        "message" => "Lecture payment type is required"
                    ];
                }
                if ($lectureRate <= 0) {
                    $subjectErrors[] = [
                        "index" => $index,
                        "input" => ".lecture-rate",
                        "feedback" => ".lecture-rate-feedback",
                        "message" => "Lecture rate is required"
                    ];
                }
            }

            if (!empty($errors) || !empty($subjectErrors)) {
                echo json_encode([
                    "status"         => "validation_error",
                    "errors"         => $errors,
                    "subject_errors" => $subjectErrors
                ]);
                exit;
            }

            // ── Agreement document upload (optional replacement) ───────────────
            $agreementFileName = null;
            $replaceDocument   = false;

            if (isset($_FILES["agreement_document"]) && $_FILES["agreement_document"]["error"] === UPLOAD_ERR_OK) {
                $uploadDir = "../assets/uploads/lecturer-contracts/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $extension        = strtolower(pathinfo($_FILES["agreement_document"]["name"], PATHINFO_EXTENSION));
                $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

                if (!in_array($extension, $allowedExtensions)) {
                    echo json_encode([
                        "status" => "validation_error",
                        "errors" => ["agreement_document" => "Invalid agreement document type"]
                    ]);
                    exit;
                }

                $agreementFileName = uniqid("contract_", true) . "." . $extension;
                move_uploaded_file($_FILES["agreement_document"]["tmp_name"], $uploadDir . $agreementFileName);
                $replaceDocument = true;
            }

            // ── Escape strings ────────────────────────────────────────────────
            $contractTitleEsc  = addslashes($contractTitle);
            $contractNoteEsc   = addslashes($contractNote);
            $paymentCycleId    = (int)$paymentCycleMap[$paymentCycle];
            date_default_timezone_set('Asia/Colombo');

            // ── Update lecturer_contracts ─────────────────────────────────────
            $docClause = $replaceDocument
                ? ", `agreement_document`='" . addslashes($agreementFileName) . "'"
                : "";

            Database::iud("
                UPDATE `lecturer_contracts` SET
                    `contract_title`                 = '$contractTitleEsc',
                    `contract_start_date`            = '$startDate',
                    `contract_end_date`              = '$endDate',
                    `payment_cycle_payment_cycle_id` = '$paymentCycleId',
                    `tax_applicable`                 = '$taxApplicable',
                    `contract_note`                  = '$contractNoteEsc'
                    $docClause
                WHERE `lecturer_contract_id` = '$contractId'
            ");

            // ── Sync subjects ─────────────────────────────────────────────────
            // Collect IDs of subjects still present (existing ones sent back by JS)
            $keptLecturerIds = [];
            foreach ($subjects as $subject) {
                $existingLecturerId = (int)($subject["existing_lecturer_id"] ?? 0);
                if ($existingLecturerId > 0) {
                    $keptLecturerIds[] = $existingLecturerId;
                }
            }

            // Delete lecturer rows that were removed in the UI
            $existingRows = Database::search("SELECT `lecturers_id` as `lecturer_id` FROM `lecturers` WHERE `lecturer_contracts_lecturer_contract_id`='$contractId'");
            while ($row = $existingRows->fetch_assoc()) {
                $rowId = (int)$row['lecturer_id'];
                if (!in_array($rowId, $keptLecturerIds)) {
                    Database::iud("DELETE FROM `lecturers` WHERE `lecturers_id`='$rowId'");
                }
            }

            // Update existing rows / insert new rows
            foreach ($subjects as $subject) {
                $existingLecturerId = (int)($subject["existing_lecturer_id"] ?? 0);
                $subjectId          = (int)$subject["subject_id"];
                $teachingMode       = strtoupper($subject["teaching_mode"]);
                $lecturePaymentTypeId = (int)$subject["lecture_payment_type"];
                $lectureRate        = (float)$subject["lecture_rate"];
                $assignmentFee      = (float)($subject["per_assignment_fee"]   ?? 0);
                $examFee            = (float)($subject["per_examination_fee"]  ?? 0);
                $paymentNote        = addslashes(trim($subject["payment_note"] ?? ""));
                $minPayableHours    = (float)($subject["minimum_payable_hours"] ?? 0);

                if ($existingLecturerId > 0) {
                    // UPDATE existing lecturer row
                    Database::iud("
                        UPDATE `lecturers` SET
                            `subjects_subject_id`                           = '$subjectId',
                            `lecturering_mode`                              = '$teachingMode',
                            `lecture_payment _type_lecture_payment _type_id`= '$lecturePaymentTypeId',
                            `lecture_rate`                                  = '$lectureRate',
                            `per_assignment_fee`                            = '$assignmentFee',
                            `per_examination_fee`                           = '$examFee',
                            `payment_note`                                  = '$paymentNote',
                            `minimum_payable_hours`                         = '$minPayableHours'
                        WHERE `lecturers_id` = '$existingLecturerId'
                          AND `lecturer_contracts_lecturer_contract_id` = '$contractId'
                    ");
                } else {
                    // INSERT new lecturer row (subject added during edit)
                    // Retrieve the lecturer's user_id from the contract
                    $contractOwner = Database::search("SELECT `lecturers_user_id` FROM `lecturer_contracts` WHERE `lecturer_contract_id`='$contractId'");
                    $ownerRow      = $contractOwner->fetch_assoc();
                    $lecturerId    = (int)$ownerRow['lecturers_user_id'];

                    Database::iud("
                        INSERT INTO `lecturers`
                        (
                            `users_user_id`,
                            `subjects_subject_id`,
                            `added_by`,
                            `added_at`,
                            `status_status_id`,
                            `per_assignment_fee`,
                            `per_examination_fee`,
                            `lecture_rate`,
                            `lecture_payment _type_lecture_payment _type_id`,
                            `payment_note`,
                            `minimum_payable_hours`,
                            `lecturering_mode`,
                            `lecturer_contracts_lecturer_contract_id`
                        )
                        VALUES
                        (
                            '$lecturerId',
                            '$subjectId',
                            '$updatedBy',
                            NOW(),
                            '1',
                            '$assignmentFee',
                            '$examFee',
                            '$lectureRate',
                            '$lecturePaymentTypeId',
                            '$paymentNote',
                            '$minPayableHours',
                            '$teachingMode',
                            '$contractId'
                        )
                    ");
                }
            }

            echo json_encode([
                "status"  => "success",
                "message" => "Lecturer contract updated successfully"
            ]);
            exit;
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo json_encode($status);
