<?php

/**
 * StaffPayments
 * ------------------------------------------------------------------
 * Salary payments for paysheets in payroll runs approved by finance.
 *
 * Which paysheets are listed (change the constants to adjust):
 *   payroll_runs.payroll_status               IN RUN_STATUSES
 *   payroll_run_employees.employee_payroll_status IN EMPLOYEE_STATUSES
 *
 * Adding a receipt (confirm amount + date + upload) inserts one row in
 * payroll_run_employee_payments and moves the paysheet APPROVED → PAID,
 * in a single transaction. One receipt per paysheet (UNIQUE key).
 *
 * Used by: staff-payments.php, api/staffPayments.php, api/staffPaymentReceipt.php
 */

require_once __DIR__ . '/../connection.php';

class StaffPaymentException extends Exception
{
    /** field => message, for showing errors under the matching input */
    public $fields = [];
}

class StaffPayments
{
    // Runs finance has approved (plus later stages, so paid history stays visible)
    const RUN_STATUSES = ['APPROVED', 'PAID', 'LOCKED'];
    // Paysheets that are payable (APPROVED) or already paid
    const EMPLOYEE_STATUSES = ['VERIFIED', 'PAID'];

    const PAYMENT_METHODS = [
        'BANK_TRANSFER' => 'Bank Transfer',
        'CASH'          => 'Cash',
        'CARD'          => 'Card',
        'CHEQUE'        => 'Cheque',
        'OTHER'         => 'Other',
    ];

    const MAX_FILE_BYTES = 5 * 1024 * 1024;
    const ALLOWED_MIME = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    /** Folder for receipts (has .htaccess deny; files are served by api/staffPaymentReceipt.php). */
    public static function receiptDir()
    {
        return __DIR__ . '../../../assets/uploads/staff-payment-receipts/';
    }

    /* ------------------------------------------------------------ DB helpers */

    public static function conn()
    {
        if (!Database::$connection) {
            Database::search("SELECT 1");
        }
        return Database::$connection;
    }

    public static function rows($sql, $types = '', array $params = [])
    {
        $stmt = self::run($sql, $types, $params);
        $res = $stmt->get_result();
        $out = [];
        while ($res && ($r = $res->fetch_assoc())) {
            $out[] = $r;
        }
        $stmt->close();
        return $out;
    }

    public static function row($sql, $types = '', array $params = [])
    {
        $r = self::rows($sql, $types, $params);
        return $r ? $r[0] : null;
    }

    public static function exec($sql, $types = '', array $params = [])
    {
        $stmt = self::run($sql, $types, $params);
        $out = ['affected' => $stmt->affected_rows, 'insert_id' => $stmt->insert_id];
        $stmt->close();
        return $out;
    }

    private static function run($sql, $types, array $params)
    {
        $stmt = self::conn()->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . self::conn()->error);
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $no = $stmt->errno;
            $er = $stmt->error;
            $stmt->close();
            throw new Exception('Query failed: ' . $er, $no);
        }
        return $stmt;
    }

    public static function now()
    {
        return (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    }

    public static function today()
    {
        return (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d');
    }

    private static function inList(array $vals)
    {
        return implode(',', array_fill(0, count($vals), '?'));
    }

    /* ----------------------------------------------------------- permissions */

    /** Same rule finance payroll pages use. */
    public static function canView($role)
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAdmin($role)
            || UserPermissions::validPermisisonType('payments');
    }

    /**
     * Who may confirm a payment and submit a receipt.
     * payments (1) is the permission already used for Banks / Lecturer Payments;
     * change it here if you want a dedicated permission id.
     */
    public static function canAddReceipt($role)
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAdmin($role)
            || UserPermissions::hasPermission('payments', 1);
    }

    /* ------------------------------------------------------------- listing */

    /** Due-status values used by the filter and the Status column. */
    const DUE_STATUSES = ['PENDING', 'DUE_TODAY', 'OVERDUE', 'UPCOMING', 'NOT_SET', 'PAID'];

    /**
     * Scheduled salary pay date for a paysheet, from the employee's active
     * payroll profile (salary_pay_day + salary_pay_timing). Day 31 in a
     * 30-day month becomes the last day of that month. NULL = not set.
     */
    private static function dueDateSql()
    {
        $base = "DATE_ADD(MAKEDATE(`pr`.`payroll_year`, 1),
                    INTERVAL (`pr`.`payroll_month` - 1 + IF(`pp`.`salary_pay_timing` = 'SAME_MONTH', 0, 1)) MONTH)";
        return "CASE WHEN `pp`.`salary_pay_day` IS NULL OR `pp`.`salary_pay_day` = 0 THEN NULL
                     ELSE DATE_ADD($base, INTERVAL LEAST(`pp`.`salary_pay_day`, DAY(LAST_DAY($base))) - 1 DAY) END";
    }

    /**
     * Inner query: one row per eligible paysheet with its due date + due status.
     * Returns [sql, types, params]. Outer queries filter/sort on the derived columns.
     */
    private static function innerQuery(array $f, $today)
    {
        $due = self::dueDateSql();
        $sql = "SELECT `pre`.`payroll_run_employee_id`, `pre`.`net_salary`, `pre`.`gross_salary`, `pre`.`total_deductions`,
                       `pre`.`employee_payroll_status`,
                       `pr`.`payroll_run_id`, `pr`.`payroll_month`, `pr`.`payroll_year`, `pr`.`payroll_status`,
                       `pr`.`payroll_period_start`, `pr`.`payroll_period_end`,
                       `u`.`user_id`, `u`.`user_fname`, `u`.`user_lname`, `u`.`user_email`, `u`.`profile_img`, `t`.`title`,
                       `pp`.`payment_method` AS `profile_payment_method`, `pp`.`bank_name`, `pp`.`bank_account_no`, `pp`.`bank_branch`,
                       `pp`.`salary_pay_day`, `pp`.`salary_pay_timing`,
                       `pay`.`payroll_run_employee_payment_id` AS `payment_id`, `pay`.`paid_amount`, `pay`.`paid_date`,
                       `pay`.`payment_method`, `pay`.`reference_no`, `pay`.`receipt_original_name`, `pay`.`note`,
                       `pay`.`date_added` AS `confirmed_at`,
                       CONCAT(`cu`.`user_fname`, ' ', `cu`.`user_lname`) AS `confirmed_by`,
                       ($due) AS `due_date`,
                       CASE
                         WHEN `pay`.`payroll_run_employee_payment_id` IS NOT NULL THEN 'PAID'
                         WHEN ($due) IS NULL THEN 'NOT_SET'
                         WHEN ($due) = ? THEN 'DUE_TODAY'
                         WHEN ($due) < ? THEN 'OVERDUE'
                         ELSE 'UPCOMING'
                       END AS `due_status`
                FROM `payroll_run_employees` AS `pre`
                INNER JOIN `payroll_runs` AS `pr` ON `pre`.`payroll_runs_payroll_run_id` = `pr`.`payroll_run_id`
                INNER JOIN `users` AS `u` ON `pre`.`users_user_id` = `u`.`user_id`
                LEFT JOIN `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
                LEFT JOIN `payroll_profiles` AS `pp` ON `pp`.`payroll_profile_id` = (
                    SELECT MAX(`pp2`.`payroll_profile_id`) FROM `payroll_profiles` AS `pp2`
                    WHERE `pp2`.`users_user_id` = `u`.`user_id` AND `pp2`.`status_status_id` = 1
                )
                LEFT JOIN `payroll_run_employee_payments` AS `pay`
                       ON `pay`.`payroll_run_employees_payroll_run_employee_id` = `pre`.`payroll_run_employee_id`
                LEFT JOIN `users` AS `cu` ON `pay`.`users_user_id` = `cu`.`user_id`
                WHERE `pr`.`payroll_status` IN (" . self::inList(self::RUN_STATUSES) . ")
                  AND `pre`.`employee_payroll_status` IN (" . self::inList(self::EMPLOYEE_STATUSES) . ")";
        $types = 'ss' . str_repeat('s', count(self::RUN_STATUSES) + count(self::EMPLOYEE_STATUSES));
        $params = array_merge([$today, $today], self::RUN_STATUSES, self::EMPLOYEE_STATUSES);

        if (!empty($f['run_id'])) {
            $sql .= " AND `pr`.`payroll_run_id` = ?";
            $types .= 'i';
            $params[] = (int)$f['run_id'];
        }
        if (!empty($f['method'])) {
            $sql .= " AND `pp`.`payment_method` = ?";
            $types .= 's';
            $params[] = (string)$f['method'];
        }
        $search = trim((string)($f['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (CONCAT(`u`.`user_fname`, ' ', `u`.`user_lname`) LIKE ? OR `u`.`user_email` LIKE ?
                           OR CAST(`u`.`user_id` AS CHAR) = ? OR `pay`.`reference_no` LIKE ?
                           OR `pp`.`bank_account_no` LIKE ?)";
            $types .= 'sssss';
            array_push($params, $like, $like, $search, $like, $like);
        }
        return [$sql, $types, $params];
    }

    /** Filters applied on the derived columns (status + pay-date range). */
    private static function outerWhere(array $f)
    {
        $where = [];
        $types = '';
        $params = [];
        $status = $f['status'] ?? '';
        if ($status === 'PENDING') {
            $where[] = "`x`.`due_status` <> 'PAID'";
        } elseif (in_array($status, self::DUE_STATUSES, true)) {
            $where[] = "`x`.`due_status` = ?";
            $types .= 's';
            $params[] = $status;
        }
        // Pay date range: scheduled date for unpaid rows, actual paid date for paid rows.
        if (!empty($f['date_from'])) {
            $where[] = "COALESCE(`x`.`paid_date`, `x`.`due_date`) >= ?";
            $types .= 's';
            $params[] = $f['date_from'];
        }
        if (!empty($f['date_to'])) {
            $where[] = "COALESCE(`x`.`paid_date`, `x`.`due_date`) <= ?";
            $types .= 's';
            $params[] = $f['date_to'];
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $types, $params];
    }

    public static function listPayments(array $f, $page, $perPage)
    {
        $today = self::today();
        list($inner, $it, $ip) = self::innerQuery($f, $today);
        list($where, $wt, $wp) = self::outerWhere($f);
        $types = $it . $wt;
        $params = array_merge($ip, $wp);

        $totals = self::row(
            "SELECT COUNT(*) AS `total_rows`,
                    COALESCE(SUM(`x`.`net_salary`), 0) AS `total_net`,
                    COALESCE(SUM(`x`.`paid_amount`), 0) AS `total_paid`,
                    SUM(`x`.`due_status` <> 'PAID') AS `pending_count`,
                    COALESCE(SUM(CASE WHEN `x`.`due_status` <> 'PAID' THEN `x`.`net_salary` ELSE 0 END), 0) AS `pending_amount`,
                    SUM(`x`.`due_status` = 'DUE_TODAY') AS `due_today_count`,
                    COALESCE(SUM(CASE WHEN `x`.`due_status` = 'DUE_TODAY' THEN `x`.`net_salary` ELSE 0 END), 0) AS `due_today_amount`,
                    SUM(`x`.`due_status` = 'OVERDUE') AS `overdue_count`,
                    COALESCE(SUM(CASE WHEN `x`.`due_status` = 'OVERDUE' THEN `x`.`net_salary` ELSE 0 END), 0) AS `overdue_amount`,
                    SUM(`x`.`due_status` = 'PAID') AS `paid_count`
             FROM ($inner) AS `x` $where",
            $types,
            $params
        );

        // Date order: due today → overdue (oldest first) → upcoming (soonest first)
        // → pay date not set → paid (latest payment first).
        $offset = ($page - 1) * $perPage;
        $rows = self::rows(
            "SELECT `x`.* FROM ($inner) AS `x` $where
             ORDER BY FIELD(`x`.`due_status`, 'DUE_TODAY', 'OVERDUE', 'UPCOMING', 'NOT_SET', 'PAID'),
                      CASE WHEN `x`.`due_status` = 'PAID' THEN NULL ELSE `x`.`due_date` END ASC,
                      `x`.`paid_date` DESC,
                      `x`.`user_fname` ASC
             LIMIT ? OFFSET ?",
            $types . 'ii',
            array_merge($params, [(int)$perPage, (int)$offset])
        );

        return ['rows' => $rows, 'totals' => $totals, 'today' => $today];
    }

    /** Distinct payment methods stored on payroll profiles (for the filter). */
    public static function profilePaymentMethods()
    {
        try {
            return array_column(self::rows(
                "SELECT DISTINCT `payment_method` FROM `payroll_profiles`
                 WHERE `payment_method` IS NOT NULL AND `payment_method` <> '' ORDER BY `payment_method`"
            ), 'payment_method');
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Map a payroll-profile payment method (Bank / Cash / Card …) to a receipt method key. */
    public static function methodKeyFromProfile($profileMethod)
    {
        $m = strtolower((string)$profileMethod);
        if (strpos($m, 'cash') !== false) return 'CASH';
        if (strpos($m, 'card') !== false) return 'CARD';
        if (strpos($m, 'cheque') !== false || strpos($m, 'check') !== false) return 'CHEQUE';
        if (strpos($m, 'bank') !== false || strpos($m, 'transfer') !== false) return 'BANK_TRANSFER';
        return 'BANK_TRANSFER';
    }

    /** "25th of the following month" style label for a profile's pay date. */
    public static function payDayLabel($day, $timing)
    {
        if (empty($day)) {
            return 'Not set';
        }
        $d = (int)$day;
        $suffix = ($d % 100 >= 11 && $d % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$d % 10] ?? 'th');
        $dayText = $d >= 31 ? 'Last day' : $d . $suffix;
        return $dayText . ' of the ' . ($timing === 'SAME_MONTH' ? 'same month' : 'following month');
    }

    /* --------------------------------------------- payroll profile pay date */

    public static function canEditPayDay($role)
    {
        return SessionUser::ifUserIsPro($role) || UserPermissions::hasPermission('users', 10);
    }

    public static function setPayDay($profileId, $day, $timing)
    {
        if (!ctype_digit((string)$day) || (int)$day < 1 || (int)$day > 31) {
            throw new StaffPaymentException('Pay day must be a day of the month between 1 and 31.');
        }
        if (!in_array($timing, ['SAME_MONTH', 'NEXT_MONTH'], true)) {
            throw new StaffPaymentException('Select when the salary is paid.');
        }
        $p = self::row(
            "SELECT `payroll_profile_id` FROM `payroll_profiles` WHERE `payroll_profile_id` = ? AND `status_status_id` = 1",
            'i',
            [(int)$profileId]
        );
        if (!$p) {
            throw new StaffPaymentException('Active payroll profile not found.');
        }
        self::exec(
            "UPDATE `payroll_profiles` SET `salary_pay_day` = ?, `salary_pay_timing` = ? WHERE `payroll_profile_id` = ?",
            'isi',
            [(int)$day, $timing, (int)$profileId]
        );
    }

    /** Payroll runs that appear in the filter. */
    public static function eligibleRuns()
    {
        return self::rows(
            "SELECT `payroll_run_id`, `payroll_month`, `payroll_year`, `payroll_status`
             FROM `payroll_runs`
             WHERE `payroll_status` IN (" . self::inList(self::RUN_STATUSES) . ")
             ORDER BY `payroll_year` DESC, `payroll_month` DESC",
            str_repeat('s', count(self::RUN_STATUSES)),
            self::RUN_STATUSES
        );
    }

    public static function getPayment($paymentId)
    {
        return self::row(
            "SELECT `pay`.*, `pre`.`net_salary`, `pr`.`payroll_month`, `pr`.`payroll_year`,
                    `u`.`user_fname`, `u`.`user_lname`, `t`.`title`,
                    CONCAT(`cu`.`user_fname`, ' ', `cu`.`user_lname`) AS `confirmed_by`
             FROM `payroll_run_employee_payments` AS `pay`
             INNER JOIN `payroll_run_employees` AS `pre` ON `pay`.`payroll_run_employees_payroll_run_employee_id` = `pre`.`payroll_run_employee_id`
             INNER JOIN `payroll_runs` AS `pr` ON `pre`.`payroll_runs_payroll_run_id` = `pr`.`payroll_run_id`
             INNER JOIN `users` AS `u` ON `pre`.`users_user_id` = `u`.`user_id`
             LEFT JOIN `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
             LEFT JOIN `users` AS `cu` ON `pay`.`users_user_id` = `cu`.`user_id`
             WHERE `pay`.`payroll_run_employee_payment_id` = ?",
            'i',
            [(int)$paymentId]
        );
    }

    /* --------------------------------------------------------- add receipt */

    /**
     * Validate input + file, then in ONE transaction:
     *   lock paysheet → check eligible & not already receipted →
     *   insert payment → APPROVED→PAID → move file.
     * Any failure rolls everything back (and removes a moved file).
     */
    public static function addReceipt(array $in, $file, $userId)
    {
        $preId = isset($in['payroll_run_employee_id']) && ctype_digit((string)$in['payroll_run_employee_id'])
            ? (int)$in['payroll_run_employee_id'] : 0;
        if ($preId <= 0) {
            throw new StaffPaymentException('Invalid paysheet.');
        }

        $errors = [];

        // Amount: positive, max 2 decimals
        $amountRaw = trim((string)($in['paid_amount'] ?? ''));
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amountRaw) || (float)$amountRaw <= 0) {
            $errors['paid_amount'] = 'Enter the paid amount (a positive number, up to 2 decimals).';
        }
        $amount = round((float)$amountRaw, 2);

        // Date: valid, not in the future
        $paidDate = trim((string)($in['paid_date'] ?? ''));
        $d = DateTime::createFromFormat('Y-m-d', $paidDate);
        if (!$d || $d->format('Y-m-d') !== $paidDate) {
            $errors['paid_date'] = 'Select the date the salary was paid.';
        } elseif ($paidDate > self::today()) {
            $errors['paid_date'] = 'Paid date cannot be in the future.';
        }

        $method = (string)($in['payment_method'] ?? '');
        if (!isset(self::PAYMENT_METHODS[$method])) {
            $errors['payment_method'] = 'Select a payment method.';
        }

        $reference = trim((string)($in['reference_no'] ?? ''));
        if (mb_strlen($reference) > 100) {
            $errors['reference_no'] = 'Reference number must be 100 characters or fewer.';
        }
        $note = trim((string)($in['note'] ?? ''));
        if (mb_strlen($note) > 500) {
            $errors['note'] = 'Note must be 500 characters or fewer.';
        }

        if (($in['confirm'] ?? '') !== '1') {
            $errors['confirm'] = 'Please confirm the payment details.';
        }

        // Receipt file
        $mime = null;
        if (!$file || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors['receipt'] = 'Please upload the payment receipt.';
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors['receipt'] = $file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
                ? 'Receipt file is too large (max 5 MB).' : 'The receipt could not be uploaded. Please try again.';
        } elseif ($file['size'] > self::MAX_FILE_BYTES) {
            $errors['receipt'] = 'Receipt file is too large (max 5 MB).';
        } else {
            // Detect the real type from the file content, not the browser's claim.
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset(self::ALLOWED_MIME[$mime])) {
                $errors['receipt'] = 'Receipt must be a PDF, JPG, PNG or WEBP file.';
            }
        }

        if ($errors) {
            $e = new StaffPaymentException(reset($errors));
            $e->fields = $errors;
            throw $e;
        }

        $storedName = 'salary_' . $preId . '_' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED_MIME[$mime];
        $originalName = mb_substr(basename((string)$file['name']), 0, 255);
        $dest = self::receiptDir() . $storedName;

        $conn = self::conn();
        $conn->begin_transaction();
        $moved = false;
        try {
            $p = self::row(
                "SELECT `pre`.`payroll_run_employee_id`, `pre`.`net_salary`, `pre`.`employee_payroll_status`,
                        `pr`.`payroll_status`, `pr`.`payroll_period_start`
                 FROM `payroll_run_employees` AS `pre`
                 INNER JOIN `payroll_runs` AS `pr` ON `pre`.`payroll_runs_payroll_run_id` = `pr`.`payroll_run_id`
                 WHERE `pre`.`payroll_run_employee_id` = ?
                 FOR UPDATE",
                'i',
                [$preId]
            );
            if (!$p) {
                throw new StaffPaymentException('Paysheet not found.');
            }
            if (!in_array($p['payroll_status'], self::RUN_STATUSES, true)) {
                throw new StaffPaymentException('This payroll run has not been approved by finance.');
            }
            if (!in_array($p['employee_payroll_status'], self::EMPLOYEE_STATUSES, true)) {
                throw new StaffPaymentException('This paysheet is ' . $p['employee_payroll_status'] . ' and cannot be paid.');
            }
            if (self::row(
                "SELECT 1 FROM `payroll_run_employee_payments` WHERE `payroll_run_employees_payroll_run_employee_id` = ?",
                'i',
                [$preId]
            )) {
                throw new StaffPaymentException('A receipt has already been submitted for this paysheet.');
            }

            $net = round((float)$p['net_salary'], 2);
            if ($amount > $net) {
                $e = new StaffPaymentException('Paid amount cannot be more than the net salary (' . number_format($net, 2) . ').');
                $e->fields = ['paid_amount' => $e->getMessage()];
                throw $e;
            }
            if (abs($amount - $net) > 0.009 && $note === '') {
                $e = new StaffPaymentException('The paid amount is different from the net salary. Please add a note explaining why.');
                $e->fields = ['note' => $e->getMessage()];
                throw $e;
            }
            if (!empty($p['payroll_period_start']) && $paidDate < substr($p['payroll_period_start'], 0, 10)) {
                $e = new StaffPaymentException('Paid date cannot be before the payroll period start (' . substr($p['payroll_period_start'], 0, 10) . ').');
                $e->fields = ['paid_date' => $e->getMessage()];
                throw $e;
            }

            $ins = self::exec(
                "INSERT INTO `payroll_run_employee_payments`
                    (`payroll_run_employees_payroll_run_employee_id`, `paid_amount`, `paid_date`, `payment_method`,
                     `reference_no`, `receipt_file`, `receipt_original_name`, `note`, `users_user_id`, `date_added`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                'idssssssis',
                [
                    $preId, $amount, $paidDate, $method,
                    $reference !== '' ? $reference : null, $storedName, $originalName,
                    $note !== '' ? $note : null, (int)$userId, self::now(),
                ]
            );

            // Paysheet is now paid (no-op if finance had already marked it PAID).
            self::exec(
                "UPDATE `payroll_run_employees` SET `employee_payroll_status` = 'PAID'
                 WHERE `payroll_run_employee_id` = ? AND `employee_payroll_status` = 'APPROVED'",
                'i',
                [$preId]
            );

            if (!is_dir(self::receiptDir()) && !mkdir(self::receiptDir(), 0755, true)) {
                throw new Exception('Receipt folder could not be created.');
            }
            $moved = is_uploaded_file($file['tmp_name'])
                ? move_uploaded_file($file['tmp_name'], $dest)
                : (getenv('STAFF_PAYMENTS_TEST') === '1' && rename($file['tmp_name'], $dest)); // CLI tests only
            if (!$moved) {
                throw new Exception('Receipt file could not be saved.');
            }

            $conn->commit();
            return (int)$ins['insert_id'];
        } catch (Throwable $e) {
            $conn->rollback();
            if ($moved && is_file($dest)) {
                @unlink($dest);
            }
            if ((int)$e->getCode() === 1062) {
                throw new StaffPaymentException('A receipt has already been submitted for this paysheet.');
            }
            throw $e;
        }
    }
}
