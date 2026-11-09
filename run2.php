<?php
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

$typs = Database::search("SELECT * FROM `students_identity_documents_type`");
while($typ = $typs->fetch_assoc()) {
    echo $typ['students_identity_documents_type'] . "<br>";
}

?>