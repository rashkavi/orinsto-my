<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/student-mail-body.php");

/**
 * Computes the outstanding payable balance for a course_payments row,
 * using the same formula as the original inline calculation.
 * Expects an assoc array with: amount, discount, discount_type_id (or
 * discount_type_discount_type_id), panelty_amount, paid_amount, total_discount,
 * total_sub_panelty_amount.
 */
function calculatePayableBalance($row)
{
    $discountTypeId = isset($row['discount_type_id']) ? $row['discount_type_id'] : $row['discount_type_discount_type_id'];

    $discountAmount = $row['discount'];
    if ($discountTypeId == 3) {
        $discountAmount = $row['amount'] / 100 * $row['discount'];
    }

    $payableAmount = $row['amount'] - $discountAmount + $row['panelty_amount'] + $row['total_sub_panelty_amount'];
    $balance = $payableAmount - $row['paid_amount'] - $row['total_discount'];

    return $balance;
}

/* ---------------------------------------------------------------------
 * Partial payment → temporary activation
 * An INACTIVE student who pays part of a due can be activated now and
 * deactivated automatically on a chosen date (tomorrow … today + 3 days).
 * Uses students_has_intakes_temp_activations; the daily cron
 * cron/intake-student-auto-deactivation-cron.php does the deactivation.
 * ------------------------------------------------------------------- */
const SPR_DEACTIVATE_MIN_DAYS = 1;
const SPR_DEACTIVATE_MAX_DAYS = 3;

function sprDeactivateRange()
{
    $tz = new DateTimeZone('Asia/Colombo');
    return [
        (new DateTime('now', $tz))->modify('+' . SPR_DEACTIVATE_MIN_DAYS . ' days')->format('Y-m-d'),
        (new DateTime('now', $tz))->modify('+' . SPR_DEACTIVATE_MAX_DAYS . ' days')->format('Y-m-d'),
    ];
}

function sprRoleKey($role)
{
    if (SessionUser::ifUserIsPro($role)) return 'PRO';
    if (SessionUser::ifUserIsAcademicPro($role)) return 'ACADEMIC_PRO';
    return 'OTHER';
}

/** Cancel the student's PENDING auto-deactivation (if any). */
function sprCancelPendingDeactivation($shiId, $now, $why)
{
    Database::iud("UPDATE `students_has_intakes_temp_activations`
        SET `schedule_status` = 'CANCELLED', `processed_at` = '" . $now . "', `process_note` = '" . Database::escape($why) . "'
        WHERE `students_has_intakes_students_has_intakes_id` = '" . (int)$shiId . "' AND `schedule_status` = 'PENDING'");
}

function sprHasPendingDeactivation($shiId)
{
    $r = Database::search("SELECT 1 FROM `students_has_intakes_temp_activations`
        WHERE `students_has_intakes_students_has_intakes_id` = '" . (int)$shiId . "' AND `schedule_status` = 'PENDING' LIMIT 1");
    return $r && $r->num_rows > 0;
}

function sprStudentLog($shiId, $userId, $now, $log, $note)
{
    Database::iud("INSERT INTO `students_has_intakes_logs`
        (`students_has_intakes_students_has_intakes_id`, `users_user_id`, `date_updated`, `log`, `note`)
        VALUES ('" . (int)$shiId . "', '" . (int)$userId . "', '" . $now . "', '" . Database::escape(mb_substr($log, 0, 100)) . "', "
        . ($note === '' ? "NULL" : "'" . Database::escape($note) . "'") . ")");
}

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $payment_id = isset($_POST['payment_id']) ? $_POST['payment_id'] : 0;
            $discount_amount = isset($_POST['discount_amount']) ? $_POST['discount_amount'] : 0;
            $penalty_amount = isset($_POST['penalty_amount']) ? $_POST['penalty_amount'] : 0;
            $method = isset($_POST['method']) ? $_POST['method'] : 0;
            $paid_date = isset($_POST['paid_date']) ? $_POST['paid_date'] : 0;
            $amount = isset($_POST['paid_amount']) ? $_POST['paid_amount'] : 0;
            $note = isset($_POST['note']) ? $_POST['note'] : '';
            $deactivate_date = isset($_POST['deactivate_date']) ? trim((string)$_POST['deactivate_date']) : '';
            if (!is_numeric($discount_amount)) $discount_amount = 0;   // field may not exist on the page
            if (!is_numeric($penalty_amount)) $penalty_amount = 0;
            $payment_slip = '';

            $paymentPlan = Database::search("SELECT
                    `cp`.`course_payments_id`, 
                    `ps`.`payment_status`,
                    `ps`.`payment_status_id`,
                    `pgp`.`payment_type`,
                    `cp`.`amount`,
                    `cp`.`discount`,
                    `cp`.`panelty_payment_option_panelty_payment_option_id`,
                    `cp`.`discount_type_discount_type_id`,
                    `dt`.`discount_type`,
                    `dt`.`discount_type_id`, 
                    `cp`.`due_date`,
                    `cp`.`refference`,
                    `i`.`intake_id`,
                    `p`.`programme_id`,
                    `f`.`faculty_id`,
                    `i`.`intake`,
                    `p`.`programme`,
                    `f`.`faculty`,
                    `t`.`title`,
                    `ld`.`first_name`,
                    `ld`.`last_name`,
                    `ppo`.`panelty_payment_option`,
                    `shi`.`students_has_intakes_id`,
                    `shi`.`status_status_id`,
                    `c`.`code`,
                    `cp`.`payment_plan_has_students_has_intakes_id`,
                IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                IFNULL(cpp.paid_amount, 0) AS paid_amount,
                IFNULL(cpp.total_discount, 0) AS total_discount,
                IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                FROM `course_payments` AS `cp`
                INNER JOIN `payment_status` AS `ps` 
                    ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                INNER JOIN `panelty_payment_option` AS `ppo` 
                    ON `cp`.`panelty_payment_option_panelty_payment_option_id` = `ppo`.`panelty_payment_option_id`
               
                INNER JOIN `discount_type` AS `dt` 
                    ON `cp`.`discount_type_discount_type_id` = `dt`.`discount_type_id`
               INNER JOIN `payment_type` AS `pgp`

                ON `cp`.`payment_type_payment_type_id` = `pgp`.`payment_type_id`
                   -- Resolve students_has_intakes from either path
                                                    LEFT JOIN `students_has_intakes` AS `shi`
                                                        ON `shi`.`students_has_intakes_id` = COALESCE(
                                                            (
                                                                SELECT `pshi_inner`.`students_has_intakes_students_has_intakes_id`
                                                                FROM `payment_plan_has_students_has_intakes` `pshi_inner`
                                                                WHERE `pshi_inner`.`payment_plan_has_students_has_intakes_id` = `cp`.`payment_plan_has_students_has_intakes_id`
                                                                LIMIT 1
                                                            ),
                                                            `cp`.`students_has_intakes_students_has_intakes_id`
                                                        )
                                                    LEFT JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                        ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
               
                LEFT JOIN `payment_category` AS `pc` 
                    ON `pshi`.`payment_category_payment_category_id` = `pc`.`payment_category_id`
                LEFT JOIN `currencies` AS `c`
                    ON `pc`.`currencies_currency_id` = `c`.`currency_id`    
                
                
                INNER JOIN `students` AS `s` 
                    ON `shi`.`students_student_id` = `s`.`student_id`
                INNER JOIN `login_details` AS `ld`
                    ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                INNER JOIN `title` AS `t` 
                    ON `s`.`title_title_id` = `t`.`title_id`
                LEFT JOIN `intakes` AS `i` 
                    ON `shi`.`intakes_intake_id` = `i`.`intake_id`
             
                LEFT JOIN `programmes` AS `p` 
                    ON `i`.`programmes_programme_id` = `p`.`programme_id`
                LEFT JOIN `faculties` AS `f` 
                    ON `p`.`faculties_faculty_id` = `f`.`faculty_id`
               LEFT JOIN (
                SELECT
                    course_payments_course_payments_id,
                    SUM(amount) AS panelty_amount
                FROM panelty_payments
                GROUP BY course_payments_course_payments_id
            ) pp
                ON cp.course_payments_id = pp.course_payments_course_payments_id
        
            LEFT JOIN (
                SELECT
                    course_payments_course_payments_id,
                    SUM(paid_amount) AS paid_amount,
                    SUM(discount_amount) AS total_discount,
                    SUM(panelty_amount) AS total_sub_panelty_amount

                FROM course_payment_has_payments
                WHERE payment_status_payment_status_id IN (2,3)
                GROUP BY course_payments_course_payments_id
            ) cpp
                ON cp.course_payments_id = cpp.course_payments_course_payments_id
                WHERE `cp`.`course_payments_id` = '" . $payment_id . "'
                
                GROUP BY `cp`.`course_payments_id`
                ");

            if ($paymentPlan->num_rows == 0) {
                $status->error = 'Invalid request method';
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0) || UserPermissions::validPermisisonType('course_overview', 0))) {
                    $status->error = 'You are not authorized to this action.';
                } else {

                    $paymentSlips = array();

                    $discountAmount = $paymentData["discount"];
                    if ($paymentData["discount_type_id"] == 3) {
                        $discountAmount = $paymentData["amount"] / 100 * $paymentData["discount"];
                    }
                    $payableAmount = $paymentData["amount"] - $discountAmount + $paymentData["panelty_amount"] + $paymentData["total_sub_panelty_amount"];
                    $balance = $payableAmount - $paymentData["paid_amount"] - $paymentData["total_discount"];

                    if (!empty($penalty_amount)) {
                        $balance = $balance + $penalty_amount;
                    }

                    if (!empty($discount_amount) && $discount_amount > $balance) {
                        $status->discount = 'Discount amount cannot be greater than payable amount';
                    } else {
                        $balance = $balance - $discount_amount;
                    }


                    if ($method == 0) {
                        $status->method = 'Please select payment method';
                    }

                    if (empty($paid_date)) {
                        $status->paid_date = 'Please select paid date';
                    } else if (CheckDateTime::isFutureDate($paid_date)) {
                        $status->paid_date = 'Paid date cannot be in the future';
                    }

                    // ---- Overpayment detection & cascading allocation plan ----
                    // If the paid amount exceeds this payment's balance, the excess is
                    // automatically allocated to the student's next pending payment(s)
                    // within the SAME course sequence (payment_plan_has_students_has_intakes_id),
                    // ordered by due date, cascading through as many as needed.
                    $overpaymentAllocations = array();
                    $currentPaymentAmount = $amount;

                    // Partial payment by an inactive student → needs a deactivate date.
                    $isPartialPayment = is_numeric($amount) && $amount > 0 && round($amount, 2) < round($balance, 2);
                    $tempDeactivateDate = null;
                    if ($isPartialPayment && (int)$paymentData['status_status_id'] === 2) {
                        list($minDeactivate, $maxDeactivate) = sprDeactivateRange();
                        $dd = DateTime::createFromFormat('!Y-m-d', $deactivate_date);
                        if ($deactivate_date === '') {
                            $status->deactivate_date = 'Please select the deactivate date (the student has not paid the full amount).';
                        } else if (!$dd || $dd->format('Y-m-d') !== $deactivate_date) {
                            $status->deactivate_date = 'Deactivate date is not valid.';
                        } else if ($deactivate_date < $minDeactivate || $deactivate_date > $maxDeactivate) {
                            $status->deactivate_date = 'Deactivate date must be between ' . $minDeactivate . ' and ' . $maxDeactivate . '.';
                        } else {
                            $tempDeactivateDate = $deactivate_date;
                        }
                    }

                    if (empty($amount)) {
                        $status->amount = 'Please enter paid amount';
                    } else if (!is_numeric($amount) || $amount <= 0) {
                        $status->amount = 'Please enter a valid paid amount';
                    } else if ($amount > $balance) {
                        $overpaymentAmount = $amount - $balance;
                        $currentPaymentAmount = $balance;

                        $upcomingPayments = Database::search("SELECT
                                `cp`.`course_payments_id`,
                                `cp`.`amount`,
                                `cp`.`discount`,
                                `cp`.`due_date`,
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
                                FROM panelty_payments GROUP BY course_payments_course_payments_id
                            ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                            LEFT JOIN (
                                SELECT course_payments_course_payments_id,
                                    SUM(paid_amount) AS paid_amount,
                                    SUM(discount_amount) AS total_discount,
                                    SUM(panelty_amount) AS total_sub_panelty_amount
                                FROM course_payment_has_payments
                                WHERE payment_status_payment_status_id IN (2,3)
                                GROUP BY course_payments_course_payments_id
                            ) cpp ON cp.course_payments_id = cpp.course_payments_course_payments_id
                            WHERE `pphsi`.`students_has_intakes_students_has_intakes_id` = '" . $paymentData['students_has_intakes_id'] . "'
                                AND `cp`.`payment_status_payment_status_id` = '1'
                                AND `cp`.`course_payments_id` != '" . $payment_id . "'
                            GROUP BY `cp`.`course_payments_id`
                            ORDER BY `cp`.`due_date` ASC
                        ");

                        $upcomingBalances = array();
                        $totalAvailable = 0;

                        if ($upcomingPayments->num_rows > 0) {
                            while ($row = $upcomingPayments->fetch_assoc()) {
                                $rowBalance = calculatePayableBalance($row);
                                if ($rowBalance > 0) {
                                    $upcomingBalances[] = array(
                                        'course_payments_id' => $row['course_payments_id'],
                                        'balance' => $rowBalance
                                    );
                                    $totalAvailable += $rowBalance;
                                }
                            }
                        }

                        if ($overpaymentAmount > $totalAvailable) {
                            $status->amount = 'Paid amount cannot be greater than the total pending balance for this course. Maximum payable across all pending dues is ' . number_format($balance + $totalAvailable, 2, '.', '') . '.';
                        } else {
                            $remaining = $overpaymentAmount;
                            foreach ($upcomingBalances as $up) {
                                if ($remaining <= 0) {
                                    break;
                                }
                                $applyAmount = min($remaining, $up['balance']);
                                $overpaymentAllocations[] = array(
                                    'course_payments_id' => $up['course_payments_id'],
                                    'amount' => $applyAmount,
                                    'fully_settled' => (bccomp((string)$applyAmount, (string)$up['balance'], 2) === 0)
                                );
                                $remaining -= $applyAmount;
                            }
                        }
                    }

                    if (isset($_FILES["slips"])) {
                        $allowed_slip_extensions = array(
                            "application/pdf",
                            "image/jpeg",
                            "image/png",
                            "image/jpg"
                        );

                        foreach ($_FILES["slips"]["name"] as $key => $slip_name) {
                            $slip_ex = $_FILES["slips"]["type"][$key];

                            if (!in_array($slip_ex, $allowed_slip_extensions)) {
                                $status->slip[] = "File '$slip_name' is not a valid type. Only PDF, JPEG, PNG and JPG files are allowed.";
                            } else {
                                $new_slip_extension = '';

                                switch ($slip_ex) {
                                    case "application/pdf":
                                        $new_slip_extension = ".pdf";
                                        break;
                                    case "image/jpeg":
                                        $new_slip_extension = ".jpg";
                                        break;
                                    case "image/png":
                                        $new_slip_extension = ".png";
                                        break;
                                    case "image/jpg":
                                        $new_slip_extension = ".jpg";
                                        break;
                                }

                                if ($_FILES["slips"]["error"][$key] === 0) {
                                    if ($_FILES["slips"]["size"][$key] <= 10 * 1024 * 1024) {
                                        $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$key]);
                                        $isValidSlip = Database::search("SELECT * FROM `course_payment_slips` WHERE `slip_hash` = '" . $slipHash . "'");
                                        if ($isValidSlip->num_rows > 0) {

                                            $status->slip[] = $slip_name . " - Duplicate receipt detected. This payment slip was already used.";
                                        } else {
                                            $slip = 'R' . $payment_id . '_' . uniqid() . $new_slip_extension;
                                            array_push($paymentSlips, $slip);
                                        }
                                    } else {
                                        $status->slip[] = "File '$slip_name' exceeds the maximum allowed size of 10MB.";
                                    }
                                } else {
                                    $status->slip[] = "There was an error uploading the file '$slip_name'.";
                                }
                            }
                        }
                    }

                    if (!empty($note) && strlen($note) > 150) {
                        $status->note = 'Note must be less than 150 characters';
                    }

                    $requiredProperties = [
                        'error',
                        'amount',
                        'slip',
                        'paid_date',
                        'method',
                        'discount',
                        'panelty',
                        'note',
                        'deactivate_date'
                    ];
                    $allPropertiesExist = true;
                    foreach ($requiredProperties as $property) {
                        if (property_exists($status, $property)) {
                            $allPropertiesExist = false;
                            break;
                        }
                    }
                    if ($allPropertiesExist) {
                        $timezone = new DateTimeZone("Asia/Colombo");
                        $dateTime = new DateTime("now", $timezone);
                        $now = $dateTime->format('Y-m-d H:i:s');

                        // One receipt covers the whole transaction: the current payment
                        // plus every payment it overflows into. Every course_payment_has_payments
                        // row created below (main + cascaded overpayment rows) links back to
                        // this single receipt via `course_payments_receipts_course_payments_receipt_id`.
                        Database::iud("INSERT INTO `course_payments_receipts` (`users_user_id`,`date_added`) VALUES ('" . $sessionUserObject["user_id"] . "','" . $now . "')");
                        $receiptId = Database::$connection->insert_id;

                        $columns = "`course_payments_course_payments_id`,`payment_method_payment_method_id`
                        ,`date_paid`,`paid_amount`,`discount_amount`,`payment_status_payment_status_id`,`date_added`,`course_payments_receipts_course_payments_receipt_id`";

                        $values = "'" . $payment_id . "','" . $method . "','" . $paid_date . "','" . $currentPaymentAmount . "','" . $discount_amount . "','3','" . $now . "','" . $receiptId . "'";

                        if (!empty($penalty_amount)) {
                            $columns .= ",`panelty_amount`";
                            $values .= ",'" . $penalty_amount . "'";
                        } else {
                            $columns .= ",`panelty_amount`";
                            $values .= ",'0'";
                        }

                        Database::iud("INSERT INTO `course_payment_has_payments` (" . $columns . ") VALUES (" . $values . ")");

                        $paymentId = Database::$connection->insert_id;

                        $balance_before_payment = $balance;
                        if ($balance - $currentPaymentAmount == 0) {
                            $payment_update_query = "`payment_status_payment_status_id`='5'";
                            Database::iud("UPDATE `course_payments` SET " . $payment_update_query . " WHERE `course_payments_id`='" . $payment_id . "'");
                        }

                        if (isset($_FILES["slips"])) {
                            $payment_slip_insert_columns = "`course_payment_has_payment_id`,`slip_name`,`reference_number`,`slip_hash`";

                            for ($i = 0; $i < count($_FILES["slips"]["name"]); $i++) {
                                $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                                $reference = strtoupper(substr($slipHash, 0, 10));

                                Database::iud("INSERT INTO `course_payment_slips` (" . $payment_slip_insert_columns . ") VALUES ('" . $paymentId . "','" . $paymentSlips[$i] . "','" . $reference . "','" . $slipHash . "')");
                                $payment_slip_path = "../assets/uploads/payment-slips/" . $paymentSlips[$i];
                                move_uploaded_file($_FILES["slips"]["tmp_name"][$i], $payment_slip_path);
                            }
                        }

                        $payment_log_columns = "`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`";
                        $payment_log_values = "'" . $payment_id . "','" . $paymentId . "','" . $sessionUserObject['user_id'] . "','" . $now . "','Upload Direct Payment Receipt and Confirm Payment'";

                        if (!empty($note)) {
                            $payment_log_columns .= ",`note`";
                            $payment_log_values .= ", '" . Database::escape($note) . "'";
                        }

                        Database::iud("INSERT INTO `payment_logs`(" . $payment_log_columns . ") VALUES(" . $payment_log_values . ")");

                        // ---- Apply cascading overpayment to next pending payments ----
                        // Reuses the same payment method/date/note and links the same
                        // uploaded slip(s) + receipt to each auto-settled payment.
                        $overpaymentAppliedSummary = array();

                        foreach ($overpaymentAllocations as $allocation) {
                            $allocPaymentId = $allocation['course_payments_id'];
                            $allocAmount = $allocation['amount'];

                            $allocColumns = "`course_payments_course_payments_id`,`payment_method_payment_method_id`
                            ,`date_paid`,`paid_amount`,`discount_amount`,`payment_status_payment_status_id`,`date_added`,`panelty_amount`,`course_payments_receipts_course_payments_receipt_id`";
                            $allocValues = "'" . $allocPaymentId . "','" . $method . "','" . $paid_date . "','" . $allocAmount . "','0','3','" . $now . "','0','" . $receiptId . "'";

                            Database::iud("INSERT INTO `course_payment_has_payments` (" . $allocColumns . ") VALUES (" . $allocValues . ")");
                            $allocCoursePaymentHasPaymentId = Database::$connection->insert_id;

                            if ($allocation['fully_settled']) {
                                Database::iud("UPDATE `course_payments` SET `payment_status_payment_status_id`='5' WHERE `course_payments_id`='" . $allocPaymentId . "'");
                            }

                            // Link the same uploaded slip(s) / receipt to this auto-applied payment
                            for ($i = 0; $i < count($paymentSlips); $i++) {
                                $slipHash = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                                $reference = strtoupper(substr($slipHash, 0, 10));
                                Database::iud("INSERT INTO `course_payment_slips` (`course_payment_has_payment_id`,`slip_name`,`reference_number`,`slip_hash`) VALUES ('" . $allocCoursePaymentHasPaymentId . "','" . $paymentSlips[$i] . "','" . $reference . "','" . $slipHash . "')");
                            }

                            $allocLogNote = 'Overpayment of ' . number_format($allocAmount, 2, '.', '') . ' auto-applied from Payment ID ' . $payment_id . (!empty($note) ? (' - ' . $note) : '');
                            Database::iud("INSERT INTO `payment_logs` (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`,`note`) VALUES ('" . $allocPaymentId . "','" . $allocCoursePaymentHasPaymentId . "','" . $sessionUserObject['user_id'] . "','" . $now . "','Overpayment Auto-Applied from Direct Payment Receipt','" . Database::escape($allocLogNote) . "')");

                            $overpaymentAppliedSummary[] = array(
                                'course_payments_id' => $allocPaymentId,
                                'amount_applied' => $allocAmount,
                                'fully_settled' => $allocation['fully_settled']
                            );
                        }

                        $status->receipt_id = $receiptId;

                        if (!empty($overpaymentAppliedSummary)) {
                            $status->overpayment_applied = $overpaymentAppliedSummary;
                        }

                        // ---- Student status after this payment ----
                        // Inactive student, or an active student on a temporary activation:
                        // check whether any due (past due date) still has a balance.
                        $shiId = (int)$paymentData['students_has_intakes_id'];
                        $studentStatusId = (int)$paymentData['status_status_id'];
                        $onTempActivation = $studentStatusId === 1 && sprHasPendingDeactivation($shiId);
                        $activationMessage = '';

                        if ($studentStatusId == 2 || $onTempActivation) {

                            $query = "
                                                    SELECT
                                                        `cp`.`course_payments_id`,
                                                        `cp`.`amount`,
                                                        `cp`.`discount`,
                                                        `cp`.`due_date`,
                                                        `cp`.`discount_type_discount_type_id`,
                                                        IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                        IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                                        IFNULL(cpp.total_discount, 0) AS total_discount,
                                                        IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                    FROM `course_payments` AS `cp`
                                                    INNER JOIN `payment_plan_has_students_has_intakes` AS `pphsi`
                                                        ON `cp`.`payment_plan_has_students_has_intakes_id` = `pphsi`.`payment_plan_has_students_has_intakes_id`
                                                    LEFT JOIN (
                                                        SELECT
                                                            course_payments_course_payments_id,
                                                            SUM(amount) AS panelty_amount
                                                        FROM panelty_payments
                                                        GROUP BY course_payments_course_payments_id
                                                    ) pp
                                                        ON cp.course_payments_id = pp.course_payments_course_payments_id
                                                    LEFT JOIN (
                                                        SELECT
                                                            course_payments_course_payments_id,
                                                            SUM(paid_amount) AS paid_amount,
                                                            SUM(discount_amount) AS total_discount,
                                                            SUM(panelty_amount) AS total_sub_panelty_amount
                                                        FROM course_payment_has_payments
                                                        WHERE payment_status_payment_status_id IN (2,3)
                                                        GROUP BY course_payments_course_payments_id
                                                    ) cpp
                                                        ON cp.course_payments_id = cpp.course_payments_course_payments_id
                                                    WHERE `pphsi`.`students_has_intakes_students_has_intakes_id` = '" . $shiId . "'
                                                        AND `cp`.`payment_status_payment_status_id` = '1'
                                                        AND `cp`.`due_date` < '" . date('Y-m-d') . "'
                                                    GROUP BY `cp`.`course_payments_id`
                                                ";

                            $payments = Database::search($query);

                            $isOverdueWithBalance = false;
                            $overdueDetails = [];

                            if ($payments->num_rows > 0) {
                                while ($payment = $payments->fetch_assoc()) {

                                    $discountAmount = $payment["discount"];
                                    if ($payment["discount_type_discount_type_id"] == 3) {
                                        $discountAmount = $payment["amount"] / 100 * $payment["discount"];
                                    }

                                    $payableAmount = $payment["amount"]
                                        - $discountAmount
                                        - $payment["total_discount"]
                                        + $payment["panelty_amount"]
                                        + $payment["total_sub_panelty_amount"];

                                    $balance = $payableAmount - $payment["paid_amount"];

                                    if ($balance > 0) {
                                        $isOverdueWithBalance = true;
                                        $overdueDetails[] = "course_payments_id=" . $payment["course_payments_id"]
                                            . " due_date=" . $payment["due_date"]
                                            . " balance=" . number_format($balance, 2, '.', '');
                                    }
                                }
                            }

                            $userId = (int)$sessionUserObject['user_id'];
                            $remaining = max(0, round($balance_before_payment - $currentPaymentAmount, 2));

                            if (!$isOverdueWithBalance) {
                                // Nothing overdue any more → normal (permanent) activation.
                                if ($studentStatusId == 2) {
                                    Database::iud("UPDATE `students_has_intakes` SET `status_status_id`='1' WHERE `students_has_intakes_id`='" . $shiId . "'");
                                    sprStudentLog($shiId, $userId, $now, 'Status changed from Inactive to Active (payment received)', 'Payment ' . $paymentData['refference'] . ' - no overdue balance.');
                                    $activationMessage = ' Student activated.';
                                } else {
                                    $activationMessage = ' Student\'s temporary activation is now permanent.';
                                }
                                sprCancelPendingDeactivation($shiId, $now, 'Cancelled: overdue payments settled');
                            } else if ($studentStatusId == 2 && $tempDeactivateDate) {
                                // Partial payment, still overdue → activate until the selected date.
                                Database::iud("UPDATE `students_has_intakes` SET `status_status_id`='1' WHERE `students_has_intakes_id`='" . $shiId . "' AND `status_status_id`='2'");
                                sprCancelPendingDeactivation($shiId, $now, 'Replaced by a new activation');
                                $tempNote = 'Partial payment ' . number_format((float)$currentPaymentAmount, 2) . ' for ' . $paymentData['refference']
                                    . '. Remaining ' . number_format($remaining, 2) . '.' . (!empty($note) ? ' ' . $note : '');
                                Database::iud("INSERT INTO `students_has_intakes_temp_activations`
                                    (`students_has_intakes_students_has_intakes_id`, `deactivate_date`, `note`, `schedule_status`, `created_by`, `created_by_role`, `created_at`)
                                    VALUES ('" . $shiId . "', '" . $tempDeactivateDate . "', '" . Database::escape($tempNote) . "', 'PENDING', '" . $userId . "', '" . sprRoleKey($sessionUserRole) . "', '" . $now . "')");
                                sprStudentLog($shiId, $userId, $now, 'Status changed from Inactive to Active until ' . $tempDeactivateDate . ' (partial payment)', $tempNote);
                                $activationMessage = ' Student activated until ' . $tempDeactivateDate . ' and will be deactivated automatically on that date.';
                                $status->deactivate_date_set = $tempDeactivateDate;
                            }
                        }

                        http_response_code(200);
                        $status->done = 'Payment receipt uploaded successfully.' . $activationMessage;
                        $status->id = $paymentData['students_has_intakes_id'];
                    }
                }
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

echo (json_encode($status));
