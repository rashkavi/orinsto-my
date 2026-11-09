<?php
/**
 * api/staffPayments.php
 *   list         JSON body {action:'list', run_id?, status?, method?, date_from?, date_to?, search?, page?, per_page?}
 *                status: PENDING | DUE_TODAY | OVERDUE | UPCOMING | NOT_SET | PAID
 *   get          JSON body {action:'get', payment_id}
 *   set_pay_day  JSON body {action:'set_pay_day', payroll_profile_id, pay_day:1-31, pay_timing:'SAME_MONTH'|'NEXT_MONTH'}
 *   add_receipt  multipart  action=add_receipt, payroll_run_employee_id, paid_amount, paid_date,
 *                           payment_method, reference_no?, note?, confirm=1, receipt=<file>
 * Response: {done:..., ...} or {error:'...', fields:{field:'message'}}
 */
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/classes/StaffPayments.php");

header('Content-Type: application/json');

try {
    if (!SessionUser::hasValidSession() || SessionUser::getCurrentUserRole() == null) {
        throw new StaffPaymentException('Authentication Failed!');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        throw new StaffPaymentException('Invalid request method');
    }
    $role = SessionUser::getCurrentUserRole();
    $user = SessionUser::getCurrentUserObject();
    UserPermissions::setUserPermissions($user['user_permissions']);

    // multipart (file upload) or JSON
    $in = isset($_POST['action']) ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);
    $action = (string)($in['action'] ?? '');

    // set_pay_day is an HR/users action (own permission check); everything else is finance.
    if ($action !== 'set_pay_day' && !StaffPayments::canView($role)) {
        throw new StaffPaymentException('You do not have permission to view staff payments.');
    }

    switch ($action) {
        case 'list':
            $page = max(1, (int)($in['page'] ?? 1));
            $perPage = (int)($in['per_page'] ?? 10);
            if (!in_array($perPage, [10, 25, 50, 100, 250], true)) {
                $perPage = 10;
            }
            $statusFilter = in_array(($in['status'] ?? ''), StaffPayments::DUE_STATUSES, true) ? $in['status'] : '';
            $validDate = function ($v) {
                $d = DateTime::createFromFormat('Y-m-d', (string)$v);
                return ($d && $d->format('Y-m-d') === $v) ? $v : '';
            };
            $res = StaffPayments::listPayments([
                'run_id'    => (int)($in['run_id'] ?? 0),
                'status'    => $statusFilter,
                'method'    => mb_substr((string)($in['method'] ?? ''), 0, 50),
                'date_from' => $validDate($in['date_from'] ?? ''),
                'date_to'   => $validDate($in['date_to'] ?? ''),
                'search'    => mb_substr((string)($in['search'] ?? ''), 0, 100),
            ], $page, $perPage);

            $canAdd = StaffPayments::canAddReceipt($role);
            $status->rows = array_map(function ($r) use ($canAdd) {
                return [
                    'id'           => (int)$r['payroll_run_employee_id'],
                    'run_id'       => (int)$r['payroll_run_id'],
                    'month_label'  => date('F', mktime(0, 0, 0, (int)$r['payroll_month'], 1)) . ' ' . $r['payroll_year'],
                    'period_start' => substr((string)$r['payroll_period_start'], 0, 10),
                    'employee'     => trim(($r['title'] ? $r['title'] . '. ' : '') . $r['user_fname'] . ' ' . $r['user_lname']),
                    'initials'     => strtoupper(substr($r['user_fname'], 0, 1) . substr($r['user_lname'], 0, 1)),
                    'user_id'      => (int)$r['user_id'],
                    'email'        => $r['user_email'],
                    'gross'        => (float)$r['gross_salary'],
                    'deductions'   => (float)$r['total_deductions'],
                    'net'          => (float)$r['net_salary'],
                    'paysheet_status' => $r['employee_payroll_status'],
                    // payroll profile (how this staff member is paid)
                    'profile_method'     => $r['profile_payment_method'],
                    'profile_method_key' => StaffPayments::methodKeyFromProfile($r['profile_payment_method']),
                    'bank_name'          => $r['bank_name'],
                    'bank_account_no'    => $r['bank_account_no'],
                    'bank_branch'        => $r['bank_branch'],
                    'pay_day_label'      => StaffPayments::payDayLabel($r['salary_pay_day'], $r['salary_pay_timing']),
                    'due_date'           => $r['due_date'],
                    'due_status'         => $r['due_status'],
                    'is_paid'      => !empty($r['payment_id']),
                    'payment'      => empty($r['payment_id']) ? null : [
                        'id'           => (int)$r['payment_id'],
                        'amount'       => (float)$r['paid_amount'],
                        'date'         => $r['paid_date'],
                        'method'       => StaffPayments::PAYMENT_METHODS[$r['payment_method']] ?? $r['payment_method'],
                        'reference'    => $r['reference_no'],
                        'file_name'    => $r['receipt_original_name'],
                        'note'         => $r['note'],
                        'confirmed_by' => $r['confirmed_by'],
                        'confirmed_at' => $r['confirmed_at'],
                    ],
                    'can_add_receipt' => $canAdd && empty($r['payment_id']),
                ];
            }, $res['rows']);
            $t = $res['totals'];
            $status->totals = [
                'rows'             => (int)$t['total_rows'],
                'net'              => (float)$t['total_net'],
                'paid'             => (float)$t['total_paid'],
                'paid_count'       => (int)$t['paid_count'],
                'pending_count'    => (int)$t['pending_count'],
                'pending_amount'   => (float)$t['pending_amount'],
                'due_today_count'  => (int)$t['due_today_count'],
                'due_today_amount' => (float)$t['due_today_amount'],
                'overdue_count'    => (int)$t['overdue_count'],
                'overdue_amount'   => (float)$t['overdue_amount'],
            ];
            $status->today = $res['today'];
            $status->page = $page;
            $status->pages = max(1, (int)ceil($t['total_rows'] / $perPage));
            $status->done = true;
            break;

        case 'set_pay_day':
            // Called from the user payroll profile page.
            if (!StaffPayments::canEditPayDay($role)) {
                throw new StaffPaymentException('You do not have permission to change the salary pay date.');
            }
            StaffPayments::setPayDay((int)($in['payroll_profile_id'] ?? 0), (string)($in['pay_day'] ?? ''), (string)($in['pay_timing'] ?? ''));
            $status->label = StaffPayments::payDayLabel((int)$in['pay_day'], $in['pay_timing']);
            $status->done = 'Salary pay date updated.';
            break;

        case 'add_receipt':
            if (!StaffPayments::canAddReceipt($role)) {
                throw new StaffPaymentException('You do not have permission to add salary payment receipts.');
            }
            $status->payment_id = StaffPayments::addReceipt($in, $_FILES['receipt'] ?? null, (int)$user['user_id']);
            $status->done = 'Payment confirmed and receipt submitted.';
            break;

        default:
            throw new StaffPaymentException('Unknown action.');
    }
} catch (StaffPaymentException $e) {
    $status->error = $e->getMessage();
    if ($e->fields) {
        $status->fields = $e->fields;
    }
} catch (Throwable $e) {
    error_log('[staffPayments] ' . $e->getMessage());
    $status->error = 'Something went wrong. Nothing was saved. Please try again.';
}

echo json_encode($status);
