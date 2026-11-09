<?php
$status = new stdClass();

require_once("../includes/UserClass.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/connection.php");

if (!SessionUser::hasValidSession()) {
    $status->error = "Authentication Failed!";
    echo json_encode($status);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
$sessionUserRole = SessionUser::getCurrentUserRole();

UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('leads', 2))) {
    $status->error = "You are not authorized to add leads";
    echo json_encode($status);
    exit;
}

$mobiles = [];

$rs = Database::search("
    SELECT `contact`
    FROM `leads`
    WHERE `contact` IS NOT NULL
      AND `contact` != ''
");

while ($data = $rs->fetch_assoc()) {
    $mobiles[] = trim($data["contact"]);
}

$status->done = true;
$status->mobiles = $mobiles;

echo json_encode($status);
