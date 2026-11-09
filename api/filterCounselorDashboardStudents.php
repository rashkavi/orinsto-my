<?php
require_once '../includes/UserClass.php';
require_once '../includes/UserPermissionsClass.php';
require_once "../includes/connection.php";

ob_start();
header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

$sessionUserRole = SessionUser::getCurrentUserRole();
$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$counselorUserId = (int) $sessionUserObject['user_id'];
if (SessionUser::ifUserIsPro($sessionUserRole) && !empty($_GET['counselor_id'])) {
    $counselorUserId = (int) $_GET['counselor_id'];
}

try {
    $filter     = $_GET['filter'] ?? 'month';       // all | today | month | custom
    $rangeFrom  = $_GET['range_from'] ?? '';
    $rangeTo    = $_GET['range_to'] ?? '';
    $searchText = trim($_GET['search'] ?? '');
    $page       = max(1, (int) ($_GET['page'] ?? 1));
    $perPage    = max(1, (int) ($_GET['per_page'] ?? 10));

    // Effective assigned date = latest "Counselor changed" log for this
    // intake, falling back to `addmission_date` when the counselor was never
    // reassigned. See CounselorAssignmentHelper.php.
    $assignedDateExpr = counselorAssignedDateExpr();
    $assignedDateJoin = counselorAssignedDateJoinSql();

    $where = "WHERE `students_has_intakes`.`counselor_user_id` = '" . Database::escape($counselorUserId) . "'";

    switch ($filter) {
        case 'today':
            $where .= " AND DATE({$assignedDateExpr}) = CURDATE()";
            break;
        case 'month':
            $where .= " AND YEAR({$assignedDateExpr}) = YEAR(CURDATE())
                        AND MONTH({$assignedDateExpr}) = MONTH(CURDATE())";
            break;
        case 'custom':
            if ($rangeFrom !== '' && $rangeTo !== '') {
                $where .= " AND DATE({$assignedDateExpr}) BETWEEN '"
                    . Database::escape($rangeFrom) . "' AND '" . Database::escape($rangeTo) . "'";
            }
            break;
        case 'all':
        default:
            // no date restriction
            break;
    }

    if ($searchText !== '') {
        $searchEscaped = Database::escape($searchText);
        $where .= " AND (
            `login_details`.`first_name` LIKE '%{$searchEscaped}%'
            OR `login_details`.`last_name` LIKE '%{$searchEscaped}%'
            OR `login_details`.`email` LIKE '%{$searchEscaped}%'
            OR `students`.`mobile` LIKE '%{$searchEscaped}%'
        )";
    }

    $baseFrom = "
        FROM `students_has_intakes`
        {$assignedDateJoin}
        INNER JOIN `students` ON `students`.`student_id` = `students_has_intakes`.`students_student_id`
        INNER JOIN `login_details` ON `login_details`.`login_details_id` = `students`.`login_details_login_details_id`
        INNER JOIN `title` ON `title`.`title_id` = `students`.`title_title_id`
        INNER JOIN `status` ON `status`.`status_id` = `login_details`.`status_status_id`
        {$where}
    ";

    $countResult = Database::search("SELECT COUNT(*) AS total {$baseFrom}");
    $totalRows = (int) $countResult->fetch_assoc()['total'];
    $totalPages = $totalRows > 0 ? (int) ceil($totalRows / $perPage) : 1;
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $listQuery = "
        SELECT
            `students`.`student_id` AS sId,
            `students_has_intakes`.`student_id` AS regId,
            `students_has_intakes`. `students_has_intakes_id`,
            `login_details`.`first_name`,
            `login_details`.`last_name`,
            `login_details`.`email`,
            `students`.`mobile`,
            `students`.`profile_img`,
            `title`.`title`,
            `status`.`status`,
            {$assignedDateExpr} AS assigned_date
        {$baseFrom}
        ORDER BY assigned_date DESC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $listResult = Database::search($listQuery);

    $rows = [];
    while ($data = $listResult->fetch_assoc()) {
        $rows[] = [
            "sId" => (int) $data['sId'],
            "shiId" => (int) $data['students_has_intakes_id'],
            "regId" => $data['regId'],
            "name" => $data['title'] . '. ' . $data['first_name'] . ' ' . $data['last_name'],
            "initials" => strtoupper(substr($data['first_name'], 0, 1) . substr($data['last_name'], 0, 1)),
            "profile_img" => $data['profile_img'],
            "email" => $data['email'],
            "mobile" => $data['mobile'],
            "assigned_date" => date("Y-m-d h:i A", strtotime($data['assigned_date'])),
            "status" => $data['status'],
        ];
    }

    ob_end_clean();
    echo json_encode([
        "success" => true,
        "rows" => $rows,
        "pagination" => [
            "page" => $page,
            "per_page" => $perPage,
            "total_rows" => $totalRows,
            "total_pages" => $totalPages,
        ],
    ]);
} catch (Exception $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Failed to load student list"]);
}

function counselorAssignedDateJoinSql($alias = 'students_has_intakes')
{
    return "
        LEFT JOIN (
            SELECT
                `students_has_intakes_students_has_intakes_id`,
                MAX(`date_updated`) AS `last_counselor_change`
            FROM `students_has_intakes_logs`
            WHERE `log` LIKE 'Counselor changed%'
            GROUP BY `students_has_intakes_students_has_intakes_id`
        ) `counselor_change_log`
            ON `counselor_change_log`.`students_has_intakes_students_has_intakes_id` = `{$alias}`.`students_has_intakes_id`
    ";
}

function counselorAssignedDateExpr($alias = 'students_has_intakes')
{
    return "COALESCE(`counselor_change_log`.`last_counselor_change`, `{$alias}`.`addmission_date`)";
}
