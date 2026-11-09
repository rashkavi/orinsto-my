<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id']) && !empty($_GET['id'])) {

            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || UserPermissions::hasPermission('users', 25))) {
                $status->error = 'Permission Denied!';
            } else {

                $STATUS_PENDING = 1;
                $STATUS_APPROVED = 2;
                $STATUS_REJECTED = 3;
                $STATUS_CANCELLED = 4;

                $currentYear = date("Y");


                $leaveTypeUsage = Database::search("
    SELECT 
                        lt.leave_type,
                        lt.max_days_per_year,

                        SUM(CASE 
                            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_APPROVED 
                            THEN lr.total_days ELSE 0 
                        END) AS used_days,

                        SUM(CASE 
                            WHEN lr.leave_request_status_leave_request_status_id = $STATUS_PENDING 
                            THEN lr.total_days ELSE 0 
                        END) AS pending_days

                    FROM leave_types AS lt

                    LEFT JOIN leave_requests AS lr 
                        ON lr.leave_types_leave_type_id = lt.leave_type_id
                        AND YEAR(lr.from_date) = '$currentYear'
                        AND lr.users_user_id = '" . $_GET['id'] . "'

                    WHERE lt.status_status_id = 1
                   
                    GROUP BY lt.leave_type_id
                    ORDER BY lt.leave_type ASC
                ");

?>
                <table class="table bl-solid m-0 p-0 g-0 align-middle">
                    <thead>
                        <tr class="br-t-5">
                            <th class="px-4 py-3 bg-table-header main-text fw-medium">Leave Type</th>
                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Allocated</th>
                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Used</th>
                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Pending</th>
                            <th class="py-3 bg-table-header text-14 main-text fw-medium text-center">Available</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($leaveTypeUsage->num_rows > 0) { ?>
                            <?php while ($type = $leaveTypeUsage->fetch_assoc()) {
                                $allocated = (float)$type['max_days_per_year'];
                                $used = (float)$type['used_days'];
                                $pending = (float)$type['pending_days'];
                                $available = $allocated > 0 ? $allocated - $used - $pending : 0;

                                if ($available < 0) {
                                    $available = 0;
                                }
                            ?>
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="text-14 main-text"><?= $type['leave_type'] ?></span>
                                    </td>
                                    <td class="py-3 text-center"><?= $allocated ?></td>
                                    <td class="py-3 text-center"><?= $used ?></td>
                                    <td class="py-3 text-center"><?= $pending ?></td>
                                    <td class="py-3 text-center">
                                        <span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2">
                                            <?= $available ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
 <div class="d-flex flex-grow-1 align-items-center justify-content-center">
                                                                                        <div class="no-data-state">
                                                                                            <div class="no-data-icon-wrap">
                                                                                                <iconify-icon icon="ph:empty-bold"></iconify-icon>
                                                                                            </div>
                                                                                            <p class="no-data-text">No data</p>
                                                                                        </div>
                                                                                    </div>                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>

                </table>
<?php
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

if (property_exists($status, 'error')) {
    echo (json_encode($status));
}
