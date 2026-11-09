<?php
// api/programmeDocuments.php
//   GET  ?programme-id=X                        -> returns all document types + which are assigned
//   POST programme_id, document_types (JSON)    -> saves the assignment (adds ticked, removes unticked)
ob_start();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/classes/StudentDocumentConfig.php");

$response = new stdClass();

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            // ---------- LOAD ----------
            $programmeId = isset($_GET['programme-id']) ? (int)$_GET['programme-id'] : 0;

            if (empty($programmeId)) {
                http_response_code(400);
                $response->error = 'Invalid request';
            } else {
                $programme = getManageableProgramme($programmeId, $sessionUserRole, $sessionUserObject, $response);

                if ($programme) {
                    $assigned = getAssignedDocumentTypeIds($programmeId);

                    $documents = [];
                    $typesRs = Database::search("SELECT * FROM `students_identity_documents_type` 
                    ORDER BY `students_identity_documents_type_id` ASC");
                    while ($type = $typesRs->fetch_assoc()) {
                        $typeId = (int)$type['students_identity_documents_type_id'];

                        // Skip types the upload form has no config for; ticking them would do nothing
                        if (!getStudentDocumentTypeConfig($typeId)) {
                            continue;
                        }

                        $documents[] = [
                            'type_id' => $typeId,
                            'type_name' => $type['students_identity_documents_type'],
                            'assigned' => isset($assigned[$typeId]),
                        ];
                    }

                    $response->programme = $programme['programme'];
                    $response->documents = $documents;
                }
            }
        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // ---------- SAVE ----------
            $programmeId = isset($_POST['programme_id']) ? (int)$_POST['programme_id'] : 0;
            $selectedRaw = isset($_POST['document_types']) ? json_decode($_POST['document_types'], true) : null;

            if (empty($programmeId)) {
                $response->error = 'Programme is required';
            } else if (!is_array($selectedRaw)) {
                $response->error = 'Invalid document list';
            } else {
                $programme = getManageableProgramme($programmeId, $sessionUserRole, $sessionUserObject, $response);

                if ($programme) {
                    $selected = [];
                    foreach ($selectedRaw as $t) {
                        $t = (int)$t;
                        if ($t > 0) {
                            $selected[$t] = true;
                        }
                    }

                    $current = getAssignedDocumentTypeIds($programmeId);

                    // Only valid, supported document types are processed; anything else posted is ignored
                    $typesRs = Database::search("SELECT `students_identity_documents_type_id` FROM `students_identity_documents_type`");
                    while ($type = $typesRs->fetch_assoc()) {
                        $typeId = (int)$type['students_identity_documents_type_id'];

                        if (!getStudentDocumentTypeConfig($typeId)) {
                            continue;
                        }

                        $want = isset($selected[$typeId]);
                        $have = isset($current[$typeId]);

                        if ($want && !$have) {
                            Database::iud("INSERT INTO `programmes_has_identity_documents` (`programmes_programme_id`, `students_identity_documents_type_id`)
                                VALUES ('" . $programmeId . "', '" . $typeId . "')");
                        } else if (!$want && $have) {
                            Database::iud("DELETE FROM `programmes_has_identity_documents`
                                WHERE `programmes_programme_id` = '" . $programmeId . "'
                                AND `students_identity_documents_type_id` = '" . $typeId . "'");
                        }
                    }

                    $response->done = 'Programme documents updated successfully';
                }
            }
        } else {
            http_response_code(400);
            $response->error = 'Invalid request method';
        }
    } else {
        $response->error = 'Authentication Failed!';
    }
} else {
    $response->error = 'Authentication Failed!';
}

ob_get_clean();
echo json_encode($response);

// Returns the programme row if it exists and the user may manage it; otherwise sets $response->error and returns null.
function getManageableProgramme($programmeId, $sessionUserRole, $sessionUserObject, $response)
{
    $programmeRs = Database::search("SELECT `programme_id`, `programme`, `faculties_faculty_id` FROM `programmes` WHERE `programme_id` = '" . (int)$programmeId . "'");

    if ($programmeRs->num_rows == 0) {
        $response->error = 'Programme does not exist';
        return null;
    }

    $programme = $programmeRs->fetch_assoc();
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('faculty', 10, $programme['faculties_faculty_id']))) {
        $response->error = 'You do not have permission to manage documents for this programme';
        return null;
    }

    return $programme;
}

// [type_id => true] for the types currently assigned to the programme.
function getAssignedDocumentTypeIds($programmeId)
{
    $assigned = [];
    $rs = Database::search("SELECT `students_identity_documents_type_id` FROM `programmes_has_identity_documents`
        WHERE `programmes_programme_id` = '" . (int)$programmeId . "'");
    while ($row = $rs->fetch_assoc()) {
        $assigned[(int)$row['students_identity_documents_type_id']] = true;
    }
    return $assigned;
}
