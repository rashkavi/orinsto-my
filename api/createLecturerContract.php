<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/classes/StudentIdGenerator.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/student-mail-body.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
            $user = SessionUser::getCurrentUserObject();
            $addedBy = (int)$user["user_id"];

            $lecturerId = isset($_POST["lecturer_id"]) ? (int)$_POST["lecturer_id"] : 0;
            $contractTitle = trim($_POST["contract_title"] ?? "");
            $startDate = trim($_POST["contract_start_date"] ?? "");
            $endDate = trim($_POST["contract_end_date"] ?? "");
            $paymentCycle = trim($_POST["payment_cycle"] ?? "");
            $taxApplicable = ($_POST["tax_applicable"] ?? "0") == "1" ? "YES" : "NO";
            $contractNote = trim($_POST["contract_note"] ?? "");
            $subjects = json_decode($_POST["subjects"] ?? "[]", true);

            $response = new stdClass();
            $errors = [];
            $subjectErrors = [];

            if ($lecturerId <= 0) {
                $errors["lecturer_id"] = "Invalid lecturer";
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

            $paymentCycleMap = [
                "monthly" => 1,
                "after_session" => 2,
                "after_module" => 3,
                "custom" => 4
            ];

            if (!isset($paymentCycleMap[$paymentCycle])) {
                $errors["payment_cycle"] = "Payment cycle is required";
            }

            if (!is_array($subjects) || count($subjects) == 0) {
                $subjectErrors[] = [
                    "index" => 1,
                    "input" => ".subject-select",
                    "feedback" => ".subject-feedback",
                    "message" => "At least one subject is required"
                ];
            }

            $lecturePaymentTypeMap = [
                "per_hour" => 1,
                "per_day" => 2,
                "per_project" => 3,
                "fixed" => 4
            ];

            $allowedTeachingModes = ["physical", "online", "hybrid"];

            foreach ($subjects as $subject) {
                $index = (int)($subject["index"] ?? 1);

                $facultyId = (int)($subject["faculty_id"] ?? 0);
                $programmeId = (int)($subject["programme_id"] ?? 0);
                $intakeId = (int)($subject["intake_id"] ?? 0);
                $levelId = (int)($subject["level_id"] ?? 0);
                $subjectId = (int)($subject["subject_id"] ?? 0);
                $teachingMode = strtolower(trim($subject["teaching_mode"] ?? ""));
                $lecturePaymentType = trim($subject["lecture_payment_type"] ?? "");
                $lectureRate = (float)($subject["lecture_rate"] ?? 0);

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

                if (empty($lecturePaymentType)) {
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
                    "status" => "validation_error",
                    "errors" => $errors,
                    "subject_errors" => $subjectErrors
                ]);
                exit;
            }

            $agreementFileName = null;

            if (isset($_FILES["agreement_document"]) && $_FILES["agreement_document"]["error"] === UPLOAD_ERR_OK) {
                $uploadDir = "../assets/uploads/lecturer-contracts/";

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $extension = strtolower(pathinfo($_FILES["agreement_document"]["name"], PATHINFO_EXTENSION));
                $allowedExtensions = ["pdf", "jpg", "jpeg", "png", "doc", "docx"];

                if (!in_array($extension, $allowedExtensions)) {
                    echo json_encode([
                        "status" => "validation_error",
                        "errors" => [
                            "agreement_document" => "Invalid agreement document type"
                        ]
                    ]);
                    exit;
                }

                $agreementFileName = uniqid("contract_", true) . "." . $extension;
                move_uploaded_file($_FILES["agreement_document"]["tmp_name"], $uploadDir . $agreementFileName);
            }

            $contractTitleEsc = addslashes($contractTitle);
            $contractNoteEsc = addslashes($contractNote);
            $paymentCycleId = (int)$paymentCycleMap[$paymentCycle];
            date_default_timezone_set('Asia/Colombo');

            Database::iud("
                    INSERT INTO `lecturer_contracts`
                    (
                        `contract_title`,
                        `contract_start_date`,
                        `contract_end_date`,
                        `payment_cycle_payment_cycle_id`,
                        `tax_applicable`,
                        `agreement_document`,
                        `contract_note`,
                        `lecturers_user_id`,
                        `users_user_id`,
                        `date_added`
                    )
                    VALUES
                    (
                        '$contractTitleEsc',
                        '$startDate',
                        '$endDate',
                        '$paymentCycleId',
                        '$taxApplicable',
                        " . ($agreementFileName ? "'" . addslashes($agreementFileName) . "'" : "NULL") . ",
                        '$contractNoteEsc',
                        '$lecturerId',
                        '$addedBy',
                        NOW()
                    )
                ");

            $contractIdResult = Database::search("SELECT LAST_INSERT_ID() AS id");
            $contractId = (int)$contractIdResult->fetch_assoc()["id"];

            foreach ($subjects as $subject) {
                $subjectId = (int)$subject["subject_id"];
                $teachingMode = strtoupper($subject["teaching_mode"]);
                $lecturePaymentTypeId = (int)$subject["lecture_payment_type"];
                $lectureRate = (float)$subject["lecture_rate"];
                $assignmentFee = (float)($subject["per_assignment_fee"] ?? 0);
                $examFee = (float)($subject["per_examination_fee"] ?? 0);
                $paymentNote = addslashes(trim($subject["payment_note"] ?? ""));

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
            `lecturering_mode`,
            `lecturer_contracts_lecturer_contract_id`
        )
        VALUES
        (
            '$lecturerId',
            '$subjectId',
            '$addedBy',
            NOW(),
            '1',
            '$assignmentFee',
            '$examFee',
            '$lectureRate',
            '$lecturePaymentTypeId',
            '$paymentNote',
            '$teachingMode',
            '$contractId'
        )
    ");
            }

            echo json_encode([
                "status" => "success",
                "message" => "Lecturer contract created successfully"
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

echo (json_encode($status));
