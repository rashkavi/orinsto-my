<?php

/**
 * api/getBatchActivityActiveStudents.php
 * ------------------------------------------
 * Backs the "Active Students" table on batch-activity-report.php:
 * students_has_intakes.status_status_id = 1 (STATUS_ACTIVE, matching the
 * constant name in checkOverduePaymentsAndUpdateStudentStatus.php) within
 * the selected intake, with their current outstanding balance (any awaiting
 * payment, regardless of due date — unlike the overdue table, these
 * students aren't necessarily late yet) and a simple paid/has-balance label.
 *
 * ASSUMPTION: no column spec was given for this table — see the header
 * comment on batch-activity-report.php. Swap columns/query if you want
 * something else (attendance %, last login, etc).
 */

require_once './../includes/UserClass.php';
require_once './../includes/UserPermissionsClass.php';
require_once "./../includes/connection.php";

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

const STATUS_ACTIVE = 1; // matches checkOverduePaymentsAndUpdateStudentStatus.php

$intakeId = isset($_GET['intake-id']) ? (int)$_GET['intake-id'] : 0;
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage  = isset($_GET['per-page']) ? max(1, (int)$_GET['per-page']) : 25;
$search   = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($intakeId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid intake-id.']);
    exit;
}

try {
    $searchClause = '';
    if ($search !== '') {
        $searchEscaped = Database::escape($search);
        $searchClause = "
            AND (
                CONCAT(ld.`first_name`, ' ', ld.`last_name`) LIKE '%{$searchEscaped}%'
                OR shi.`student_id` LIKE '%{$searchEscaped}%'
            )
        ";
    }

    $baseCountQuery = "
        SELECT COUNT(*) AS total
        FROM `students_has_intakes` shi
        INNER JOIN `students` st ON shi.`students_student_id` = st.`student_id`
        INNER JOIN `users` u ON st.`users_user_id` = u.`user_id`
        WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
          AND shi.`status_status_id` = '" . STATUS_ACTIVE . "'
          {$searchClause}
    ";
    $totalRecords = (int)Database::search($baseCountQuery)->fetch_assoc()['total'];
    $totalPages = max(1, (int)ceil($totalRecords / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $studentsQuery = "
        SELECT
            shi.`students_has_intakes_id`,
            st.`student_id`,
            shi.`student_id` AS `student_id_number`,
            ld.first_name,
            ld.last_name
        FROM `students_has_intakes` shi
        INNER JOIN `students` st ON shi.`students_student_id` = st.`student_id`
        INNER JOIN `login_details` ld ON st.`login_details_login_details_id` = ld.`login_details_id`
        WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
          AND shi.`status_status_id` = '" . STATUS_ACTIVE . "'
          {$searchClause}
        ORDER BY shi.student_id ASC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $studentsRs = Database::search($studentsQuery);

    $records = [];
    while ($row = $studentsRs->fetch_assoc()) {
        $studentsHasIntakesId = (int)$row['students_has_intakes_id'];
        $balance = computeCurrentBalance($studentsHasIntakesId);
        $studentName = trim($row['first_name'] . ' ' . $row['last_name']);

        $records[] = [
            'student_name'        => htmlspecialchars($studentName),
            'student_id_number'   => htmlspecialchars($row['student_id_number']),
            'balance_formatted'   => number_format($balance, 2),
            'has_balance'         => $balance > 0,
            'payment_status_label' => $balance > 0 ? 'Has Balance' : 'Paid in Full',
        ];
    }

    echo json_encode([
        'status' => 'success',
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'page' => $page,
        'records' => $records,
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to load active students: ' . $e->getMessage()]);
}

/**
 * Same shape as computeOverdueBalance() in getBatchActivityDuePayments.php,
 * but WITHOUT the `due_date < CURDATE()` filter — these students aren't
 * necessarily late, this is just their current outstanding balance across
 * any awaiting-payment course_payments row.
 */
function computeCurrentBalance(int $studentsHasIntakesId): float
{
    $paymentsQuery = "
        SELECT
            `cp`.`amount`,
            `cp`.`discount`,
            `cp`.`discount_type_discount_type_id`,
            IFNULL(pp.panelty_amount, 0) AS panelty_amount,
            IFNULL(cpp.paid_amount, 0) AS paid_amount,
            IFNULL(cpp.total_discount, 0) AS total_discount,
            IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
        FROM `course_payments` AS `cp`
        INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi`
            ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
        LEFT JOIN (
            SELECT course_payments_course_payments_id, SUM(amount) AS panelty_amount
            FROM panelty_payments
            GROUP BY course_payments_course_payments_id
        ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
        LEFT JOIN (
            SELECT
                course_payments_course_payments_id,
                SUM(paid_amount) AS paid_amount,
                SUM(discount_amount) AS total_discount,
                SUM(panelty_amount) AS total_sub_panelty_amount
            FROM course_payment_has_payments
            WHERE payment_status_payment_status_id IN (2,3)
            GROUP BY course_payments_course_payments_id
        ) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
        WHERE `pphsi`.`students_has_intakes_students_has_intakes_id` = '{$studentsHasIntakesId}'
          AND `cp`.`payment_status_payment_status_id` = 1
        GROUP BY `cp`.`course_payments_id`
    ";
    $paymentsRs = Database::search($paymentsQuery);

    $totalBalance = 0.0;
    while ($payment = $paymentsRs->fetch_assoc()) {
        $discountAmount = $payment['discount'];
        if ($payment['discount_type_discount_type_id'] == 3) {
            $discountAmount = $payment['amount'] / 100 * $payment['discount'];
        }
        $payableAmount = $payment['amount']
            - $discountAmount
            - $payment['total_discount']
            + $payment['panelty_amount']
            + $payment['total_sub_panelty_amount'];
        $balance = $payableAmount - $payment['paid_amount'];
        if ($balance > 0) {
            $totalBalance += $balance;
        }
    }
    return $totalBalance;
}
