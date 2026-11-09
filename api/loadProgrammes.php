<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        $faculty = isset($_GET['id']) ? trim($_GET['id']) : '';
        if ($faculty == 0) {
            $status->faculty = "Please select faculty";
        }

        if (!property_exists($status, 'faculty')) {
?>
            <option value="0" selected>- Select -</option>
            <?php
            $query = "SELECT * FROM `programmes` WHERE `faculties_faculty_id`='" . $faculty . "'";
            if (SessionUser::ifUserIsCoordinator($sessionUserRole)) {
                $query .= " AND programme_id IN (SELECT programmes_programme_id FROM users_has_permission_to_programmes
                                                    WHERE users_has_permission_to_programmes.users_user_id = '" . $sessionUserObject['user_id'] . "' 
                                                    AND users_has_permission_to_programmes.status_status_id = 1 AND `permission_to_permission_to_id`='72') ";
            }
            $programmes = Database::search($query);
            while ($data = $programmes->fetch_assoc()) {
            ?>
                <option value="<?php echo $data["programme_id"]; ?>"><?php echo $data["programme"]; ?></option>
<?php
            }
        }
    } else {
        http_response_code(400);
        $status->error = 'Invalid request method';
    }
} else {
    $status->error = 'Authentication Failed!';
}

if (property_exists($status, 'error') || property_exists($status, 'faculty')) {
    echo (json_encode($status));
}
