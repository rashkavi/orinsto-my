<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $intake = $decoded['intake'];
                    $rnumber = $decoded['rnumber'];
                    $name = $decoded['name'];
                    $cnumber = $decoded['cnumber'];
                    $complain = $decoded['complain'];
                    $agent = $decoded['agent'];
                    if (empty($intake)) {
                        $status->intake =  "Intake is Required!";
                    }
                    if (empty($rnumber)) {
                        $status->rnumber =  "Registration Number is Required!";
                    }
                    if (empty($name)) {
                        $status->name =  "Name is Required!";
                    }
                    if (empty($cnumber)) {
                        $status->cnumber =  "Contact Number is Required!";
                    }
                    if (empty($complain)) {
                        $status->complain =  "Complain is Required!";
                    }
                    if ($agent == "0") {
                        $status->agent =  "Assigned Counselor is Required!";
                    }

                    $requiredProperties = [
                        'intake',
                        'rnumber',
                        'name',
                        'cnumber',
                        'complain',
                        'agent'
                    ];

                    $allFieldsPresent = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allFieldsPresent = false;
                            break;
                        }
                    }

                    if ($allFieldsPresent) {
                        $d = new DateTime();
                        $tz = new DateTimeZone("Asia/Colombo");
                        $d->setTimezone($tz);
                        $date = $d->format("Y-m-d H:i:s");

                        Database::iud("INSERT INTO `complains` (`intake`, `registration_no`, `name`, `contact`, `complain`, `users_user_id`, `datetime`, `added_by`) 
                    VALUES ('" . $intake . "', '" . $rnumber . "', '" . $name . "', '" . $cnumber . "', '" . $complain . "', '" . $agent . "', '" . $date . "', '" . $sessionUserObject["user_id"] . "')");
                        
                        $status->done = 'Complain Added Successfully';
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid request method';
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
