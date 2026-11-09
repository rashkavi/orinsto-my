<?php
// Single source of truth for student identity-document upload types.
// Include with require_once AFTER connection.php from:
//   - add-student / edit-student pages
//   - api/addStudentToIntake.php and the edit-student API
// The array key is `students_identity_documents_type`.`students_identity_documents_type_id`.
//
// "key"      = data-doc-key on the page AND the $_FILES field name
// "accept"   = allowed extensions; the server derives its allowed list from this,
//              so the browser file picker and the server validation can never disagree
// "folder"   = sub-folder under assets/uploads/student-identity-documents/
// Add a new document type here (plus its row in students_identity_documents_type).

function getAllStudentDocumentTypeConfigs()
{
    return [
        1 => [
            "type_id" => 1,
            "key" => "birth_certificate",
            "title" => "Birth Certificate",
            "folder" => "birth-certificates",
            "prefix" => "BIRTH_CERTIFICATE",
            "multiple" => false,
            "keep" => true,
            "accept" => ".pdf,.jpg,.jpeg,.png,.doc,.docx",
        ],
        2 => [
            "type_id" => 2,
            "key" => "nic_passport",
            "title" => "NIC / Passport",
            "folder" => "nic-documents",
            "prefix" => "NIC_PASSPORT",
            "multiple" => false,
            "keep" => true,
            "accept" => ".pdf,.jpg,.jpeg,.png",
        ],
        3 => [
            "type_id" => 3,
            "key" => "ol_result_sheet",
            "title" => "O/L Results Sheet",
            // legacy folder: files uploaded before the rename still resolve
            "folder" => "academic-documents",
            "prefix" => "OL_RESULT_SHEET",
            "multiple" => true,
            "keep" => true,
            "accept" => ".pdf,.jpg,.jpeg,.png,.doc,.docx",
        ],
        4 => [
            "type_id" => 4,
            "key" => "certificates",
            "title" => "Certificates",
            "folder" => "certificates",
            "prefix" => "CERTIFICATE",
            "multiple" => true,
            "keep" => false,
            "accept" => ".pdf,.jpg,.jpeg,.png,.doc,.docx",
        ],
        5 => [
            "type_id" => 5,
            "key" => "grama_niladhari",
            "title" => "Grama Niladhari Registration Letter",
            "folder" => "grama-niladhari-letters",
            "prefix" => "GN_LETTER",
            "multiple" => true,
            "keep" => false,
            "accept" => ".pdf,.jpg,.jpeg,.png,.doc,.docx",
        ],
        6 => [
            "type_id" => 6,
            "key" => "guardian_nic",
            "title" => "Guardian NIC Photocopy",
            "folder" => "guardian-nic",
            "prefix" => "GUARDIAN_NIC",
            "multiple" => true,
            "keep" => false,
            "accept" => ".pdf,.jpg,.jpeg,.png,.doc,.docx",
        ],
        7 => [
            "type_id" => 7,
            "key" => "nic_size_photos",
            "title" => "Two NIC-Size Student Photos",
            "folder" => "student-photos",
            "prefix" => "NIC_SIZE_PHOTO",
            "multiple" => true,
            "keep" => false,
            "accept" => ".jpg,.jpeg,.png",
        ],
        8 => [
            "type_id" => 8,
            "key" => "student_photo",
            "title" => "Student Photo –PDF",
            "folder" => "student-photo-pdf",
            "prefix" => "STUDENT_PHOTO_PDF",
            "multiple" => false,
            "keep" => false,
            "accept" => ".pdf",
        ],
        9 => [
            "type_id" => 9,
            "key" => "academic_documents",
            "title" => "Academic Documents",
            "folder" => "other-academic-documents",
            "prefix" => "ACADEMIC_DOCUMENT",
            "multiple" => true,
            "keep" => false,
            "accept" => ".pdf",
        ],
        10 => [
            "type_id" => 10,
            "key" => "agreements",
            "title" => "Agreements",
            "folder" => "agreements",
            "prefix" => "AGREEMENT",
            "multiple" => false,
            "keep" => false,
            "accept" => ".jpg,.jpeg,.png,.pdf",
        ],
    ];
}

// One type's config by type id, or null if unknown.
function getStudentDocumentTypeConfig($typeId)
{
    $all = getAllStudentDocumentTypeConfigs();
    $typeId = (int)$typeId;
    return isset($all[$typeId]) ? $all[$typeId] : null;
}

// Configs keyed by field key (= $_FILES field name). Used by the API validators.
function getStudentDocumentFieldConfig()
{
    $byKey = [];
    foreach (getAllStudentDocumentTypeConfigs() as $config) {
        $byKey[$config["key"]] = $config;
    }
    return $byKey;
}

// ["pdf","jpg",...] derived from the "accept" string.
function getStudentDocumentAllowedExtensions(array $config)
{
    $extensions = [];
    foreach (explode(",", $config["accept"]) as $ext) {
        $ext = strtolower(ltrim(trim($ext), "."));
        if ($ext !== "") {
            $extensions[] = $ext;
        }
    }
    return $extensions;
}

// Document configs required by a programme, in type-id order.
function getProgrammeStudentDocumentConfigs($programmeId)
{
    $rs = Database::search("SELECT `students_identity_documents_type_id` FROM `programmes_has_identity_documents`
        WHERE `programmes_programme_id` = '" . (int)$programmeId . "'
        ORDER BY `students_identity_documents_type_id` ASC");

    $configs = [];
    while ($row = $rs->fetch_assoc()) {
        $config = getStudentDocumentTypeConfig($row["students_identity_documents_type_id"]);
        if ($config) {
            $configs[] = $config;
        }
    }
    return $configs;
}
