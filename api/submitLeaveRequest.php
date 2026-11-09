<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/DateTimeClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $leaveType = isset($_POST['leave_type']) ? trim($_POST['leave_type']) : '';
            $dayType   = isset($_POST['day_type']) ? trim($_POST['day_type']) : '';
            $fromDate  = isset($_POST['from_date']) ? trim($_POST['from_date']) : '';
            $toDate    = isset($_POST['to_date']) ? trim($_POST['to_date']) : '';
            $reason    = isset($_POST['reason']) ? trim($_POST['reason']) : '';

            if (empty($leaveType)) {
                $status->leave_type = "Leave type is required";
            }

            if (empty($dayType)) {
                $status->day_type = "Day type is required";
            }

            $today = date('Y-m-d');

            if (empty($fromDate)) {
                $status->from_date = "From date is required";
            } elseif ($fromDate < $today) {
                $status->from_date = "From date must be today or a future date";
            }

            if (empty($toDate)) {
                $status->to_date = "To date is required";
            } elseif ($toDate < $today) {
                $status->to_date = "To date must be today or a future date";
            }

            if (!empty($fromDate) && !empty($toDate)) {
                if ($fromDate > $toDate) {
                    $status->to_date = "To date must be greater than or equal to from date";
                }
            }

            if (!empty($fromDate) && !empty($toDate)) {
                if (strtotime($fromDate) > strtotime($toDate)) {
                    $status->to_date = "To date must be greater than or equal to from date";
                }
            }

            if (empty($reason)) {
                $status->reason = "Reason is required";
            } else if (strlen($reason) > 1000) {
                $status->reason = "Reason cannot exceed 1000 characters";
            }

            $attachment = '';

            if (isset($_FILES["attachment"]) && $_FILES["attachment"]["error"] === 0) {

                $allowedTypes = [
                    "application/pdf" => ".pdf",
                    "image/jpg" => ".jpg",
                    "image/jpeg" => ".jpeg",
                    "image/png" => ".png",
                    "application/msword" => ".doc",
                    "application/vnd.openxmlformats-officedocument.wordprocessingml.document" => ".docx"
                ];

                $fileType = $_FILES["attachment"]["type"];
                $fileSize = $_FILES["attachment"]["size"];
                $tmpName  = $_FILES["attachment"]["tmp_name"];
                $fileName = $_FILES["attachment"]["name"];

                if (!array_key_exists($fileType, $allowedTypes)) {
                    $status->attachment = "Invalid file type";
                } elseif ($fileSize > 5 * 1024 * 1024) {
                    $status->attachment = "File must be less than 5MB";
                } elseif (strlen($fileName) > 80) {
                    $status->attachment = "File name too long";
                } else {
                    $attachment = uniqid("leave_", true) . $allowedTypes[$fileType];
                }
            }

            $requiredProperties = [
                'leave_type',
                'day_type',
                'from_date',
                'to_date',
                'reason',
                'attachment'
            ];
            $allPropertiesExist = true;
            foreach ($requiredProperties as $property) {
                if (property_exists($status, $property)) {
                    $allPropertiesExist = false;
                    break; // Exit loop on the first missing property
                }
            }

            if ($allPropertiesExist) {
                $timezone = new DateTimeZone("Asia/Colombo");
                $dateTime = new DateTime("now", $timezone);
                $addTime = $dateTime->format('Y-m-d H:i:s');

                $totalDays = round(abs(strtotime($fromDate) - strtotime($toDate)) / 86400) + 1;

                $insert_col = "`users_user_id`,`leave_types_leave_type_id`,
                `from_date`,`to_date`,`leave_day_type_leave_day_type_id`,
                `total_days`,`reason`,`leave_request_status_leave_request_status_id`,`requested_at`";

                $insert_val = "'" . $sessionUserObject['user_id'] . "',
                '" . $leaveType . "',
                '" . $fromDate . "',
                '" . $toDate . "',
                '" . $dayType . "',
                '" . $totalDays . "',
                '" . $reason . "',
                '1',
                '" . $addTime . "'";

                if (!empty($attachment)) {
                    $insert_col .= ",`attachment`";
                    $insert_val .= ",'" . $attachment . "'";
                }

                Database::iud("INSERT INTO `leave_requests` (" . $insert_col . ") VALUES (" . $insert_val . ")");

                if (!empty($attachment)) {
                    move_uploaded_file($_FILES["attachment"]["tmp_name"], "../assets/uploads/leave-request-attachments/" . $attachment);
                }

                $status->done = 'Leave request submitted successfully';
            }
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
