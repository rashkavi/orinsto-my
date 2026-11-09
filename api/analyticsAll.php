<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/Project.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if (SessionUser::ifUserIsPro($sessionUserRole)) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $monthIdArray = $decoded['monthIdArray'];
                    $arrayLength = count($monthIdArray);
                    $index = 0;
                    $dataArray = [];
                    while ($index < $arrayLength) {
                        $currentValue = $monthIdArray[$index];
                        if ($currentValue < 10) {
                            $currentValue = "0" . $currentValue;
                        }
                        $date =  date("Y") . "-" . $currentValue;
                        $data = Database::search("SELECT COUNT(`lead_id`) AS 'cid' FROM `leads` WHERE `date_added` LIKE '" . $date . "%'");
                        if ($data->num_rows > 0) {
                            $row = $data->fetch_assoc();
                            array_push($dataArray, $row['cid']);
                        } else {
                            array_push($dataArray, "0");
                        }
                        $index++;
                    }
                    echo (json_encode($dataArray));
                    exit();
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
