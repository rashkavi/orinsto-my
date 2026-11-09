<?php

/**
 * api/getBatchActivityDuePayments.php
 * -------------------------------------
 * Backs the "Deactivated Students — Payment Due" table on
 * batch-activity-report.php: students in the selected intake who are
 * CURRENTLY still inactive because of an overdue payment — the exact
 * population checkOverduePaymentsAndUpdateStudentStatus.php flags
 * (students_has_intakes.status_status_id = STATUS_OVERDUE), with:
 *   - their latest students_has_intakes_logs entry as the "reason" +
 *     "deactivated on" date
 *   - their live outstanding balance, recomputed with the SAME formula
 *     the deactivation script uses (course_payments awaiting payment,
 *     due_date in the past, balance = payable - paid where paid sums
 *     payment_status_payment_status_id IN (2,3))
 *
 * FLAG: this file's "paid" status set (2,3) matches
 * checkOverduePaymentsAndUpdateStudentStatus.php, NOT the (8 = approved)
 * convention used in BatchDashboardSummary. Confirm which is correct for
 * this platform before shipping.
 *
 * ASSUMPTION: students table columns — adjust the SELECT below if your
 * students table names id/number/first/last name fields differently.
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

const STATUS_OVERDUE = 2; // matches checkOverduePaymentsAndUpdateStudentStatus.php

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
        $searchEscaped = Database::escape($search); // ASSUMPTION: Database::escape() exists — swap for the equivalent you use elsewhere if not.
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
       INNER JOIN login_details ld ON st.`login_details_login_details_id` = ld.`login_details_id`
        WHERE shi.`intakes_intake_id` = '" . $intakeId . "'
          AND shi.`status_status_id` = '" . STATUS_OVERDUE . "'
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
          AND shi.`status_status_id` = '2'
          {$searchClause}
        ORDER BY shi.student_id ASC
        LIMIT {$perPage} OFFSET {$offset}
    ";
    $studentsRs = Database::search($studentsQuery);

    $records = [];
    while ($row = $studentsRs->fetch_assoc()) {
        $studentsHasIntakesId = (int)$row['students_has_intakes_id'];

        $totalBalance = computeOverdueBalance($studentsHasIntakesId);

        $logQuery = "
            SELECT `log`, `date_updated`
            FROM `students_has_intakes_logs`
            WHERE `students_has_intakes_students_has_intakes_id` = '{$studentsHasIntakesId}'
            ORDER BY `date_updated` DESC
            LIMIT 1
        ";
        $logRow = Database::search($logQuery)->fetch_assoc();
        $reasonFull = $logRow['log'] ?? 'No log entry found.';
        $deactivatedOn = $logRow['date_updated'] ?? null;

        $studentName = trim($row['first_name'] . ' ' . $row['last_name']);

        $records[] = [
            'student_name'      => htmlspecialchars($studentName),
            'student_id_number' => htmlspecialchars($row['student_id_number']),
            'balance_formatted' => number_format($totalBalance, 2),
            'reason_full'       => htmlspecialchars($reasonFull),
            'reason_short'      => htmlspecialchars(mb_strimwidth($reasonFull, 0, 60, '...')),
            'deactivated_on'    => $deactivatedOn ? date('Y-m-d', strtotime($deactivatedOn)) : '-',
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
    echo json_encode(['status' => 'error', 'message' => 'Failed to load due payments: ' . $e->getMessage()]);
}

/**
 * Same balance formula as checkOverduePaymentsAndUpdateStudentStatus.php —
 * kept as a function here (and duplicated in
 * generateBatchActivityReportPdf.php) since there's no shared helper class
 * for it in what's been shared so far. Worth promoting to a static method
 * on a shared class if this logic needs to stay in sync in more than two
 * places.
 */
function computeOverdueBalance(int $studentsHasIntakesId): float
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
          AND `cp`.`due_date` < CURDATE()
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