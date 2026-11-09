<?php
ob_start();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

$response = new stdClass();

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {

        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['student-id']) && !empty($_GET['student-id'])) {
            $studentId = intval($_GET['student-id']);

            $studentCheck = Database::search("SELECT `student_id` FROM `students` WHERE `student_id` = '" . $studentId . "'");

            if ($studentCheck->num_rows == 0) {
                $response->error = 'Student does not exist';
            } else {
                // All known document types, in the order they were defined.
                $types = Database::search("SELECT * FROM `students_identity_documents_type` ORDER BY `students_identity_documents_type_id` ASC");

                // Any physical-receipt records already on file for this student.
                $records = Database::search("SELECT `shpd`.*, CONCAT(`title`.`title`,'.',`u`.`user_fname`,' ',`u`.`user_lname`) AS 'marked_by_name'
                    FROM `students_has_physical_documents` `shpd`
                    LEFT JOIN `users` `u` ON `shpd`.`users_user_id` = `u`.`user_id`
                    LEFT JOIN `title` ON `u`.`title_title_id` = `title`.`title_id`
                    WHERE `shpd`.`students_student_id` = '" . $studentId . "'");

                $recordsByType = [];
                while ($row = $records->fetch_assoc()) {
                    $recordsByType[$row['students_identity_documents_type_id']] = $row;
                }

                $result = [];
                while ($type = $types->fetch_assoc()) {
                    $typeId = $type['students_identity_documents_type_id'];
                    $record = isset($recordsByType[$typeId]) ? $recordsByType[$typeId] : null;

                    $result[] = [
                        'type_id'       => (int)$typeId,
                        'type_name'     => $type['students_identity_documents_type'],
                        'is_received'   => $record ? (bool)$record['is_received'] : false,
                        'received_date' => $record ? $record['received_date'] : null,
                        'marked_by'     => $record ? $record['marked_by_name'] : null,
                        'notes'         => $record ? $record['notes'] : null,
                    ];
                }

                $response->documents = $result;
            }
        } else {
            http_response_code(400);
            $response->error = 'Invalid request';
        }
    } else {
        $response->error = 'Authentication Failed!';
    }
} else {
    $response->error = 'Authentication Failed!';
}

ob_get_clean();
echo json_encode($response);
