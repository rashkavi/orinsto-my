<?php
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/phpmailer/mail-process.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/EmailNotificationService.php");

/**
 * Computes the outstanding payable balance for a course_payments row.
 * A negative result means the row has been overpaid by that amount.
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

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $paymentId = isset($decoded['paymentId']) ? $decoded['paymentId'] : 0;
                    $paymentStatus = isset($decoded['paymentStatus']) ? $decoded['paymentStatus'] : 0;
                    $paymentMethod = isset($decoded['method']) ? $decoded['method'] : 0;
                    $paymentAmount = isset($decoded['amount']) ? $decoded['amount'] : 0;
                    $paymentDate = isset($decoded['date']) ? $decoded['date'] : 0;
                    $forceUpdate = isset($decoded['forceUpdate']) ? intval($decoded['forceUpdate']) : 0;
                    $note = isset($decoded['note']) ? $decoded['note'] : '';

                    if ($paymentId == 0) {
                        $status->error = "Invalid payment id";
                    } else {
                        $isValidPaymentId = Database::search("SELECT * FROM `course_payment_has_payments` 
                        INNER JOIN `payment_method` ON `course_payment_has_payments`.`payment_method_payment_method_id` = `payment_method`.`payment_method_id`
                        
                        WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");
                        if ($isValidPaymentId->num_rows == 0) {
                            $status->error = "Invalid payment id";
                        } else {
                            $coursePaymentData = $isValidPaymentId->fetch_assoc();
                            $isValidPaymentStatus = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` = '" . $paymentStatus . "'");
                            if ($isValidPaymentStatus->num_rows == 0) {
                                $status->status = "Please select payment status";
                            }

                            if (empty($note)) {
                                $status->note = "Note is required";
                            }

                            if ($coursePaymentData['paid_amount'] != $paymentAmount) {
                                $status->amount = "Please enter the paid amount";
                            }

                            if (!property_exists($status, "status") && !property_exists($status, "note")) {
                                $timezone = new DateTimeZone("Asia/Colombo");
                                $dateTime = new DateTime("now", $timezone);
                                $date = $dateTime->format('Y-m-d H:i:s');
                                $today = date('Y-m-d', strtotime($date));

                                $paymentStatusData = $isValidPaymentStatus->fetch_assoc();

                                $updateFields = [];
                                if ($paymentMethod != 0) {
                                    $updateFields[] = "`payment_method_payment_method_id` = '" . $paymentMethod . "'";
                                }
                                if ($paymentAmount != 0) {
                                    $updateFields[] = "`paid_amount` = '" . $paymentAmount . "'";
                                }
                                if ($paymentDate != 0) {
                                    $updateFields[] = "`date_paid` = '" . $paymentDate . "'";
                                }


                                Database::iud("UPDATE `course_payment_has_payments` SET `payment_status_payment_status_id` = '" . $paymentStatus . "', " . implode(", ", $updateFields) . " WHERE `course_payment_has_payment_id` = '" . $paymentId . "'");

                                // The single receipt for this verification: this payment plus
                                // any overpayment spillover it triggers below both link back to
                                // this same `course_payments_receipts` row via the FK on
                                // `course_payment_has_payments.course_payments_receipts_course_payments_receipt_id`.
                                $receiptId = null;
                                if ($paymentStatus == 3) {
                                    Database::iud("INSERT INTO `course_payments_receipts` (`users_user_id`,`date_added`) VALUES ('" . $sessionUserObject["user_id"] . "','" . $date . "')");
                                    $receiptId = Database::$connection->insert_id;

                                    Database::iud("UPDATE `course_payment_has_payments` SET `course_payments_receipts_course_payments_receipt_id`='" . $receiptId . "' WHERE `course_payment_has_payment_id`='" . $paymentId . "'");
                                }
                                // Determine log details based on whether the value exceeded limits
                                $logMsg = "Payment verification status updated to " . $paymentStatusData["payment_status"];

                                Database::iud("INSERT INTO `payment_logs` (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`note`,`log`) 
    VALUES ('" . $coursePaymentData["course_payments_course_payments_id"] . "','" . $paymentId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . Database::escape($note) . "','" . Database::escape($logMsg) . "')");

                                if ($forceUpdate === 1) {
                                    Database::iud("UPDATE `course_payments` SET `amount` = '" . $paymentAmount . "' WHERE `course_payments_id` = '" . $coursePaymentData["course_payments_course_payments_id"] . "'");

                                    $logMsg .= " (Super Admin Override Applied: Amount " . $paymentAmount . " exceeded Max Course standard limit)";

                                    Database::iud("INSERT INTO `payment_logs` (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`note`,`log`) 
    VALUES ('" . $coursePaymentData["course_payments_course_payments_id"] . "','" . $paymentId . "','" . $sessionUserObject["user_id"] . "','" . $date . "','" . Database::escape($note) . "','" . Database::escape($logMsg) . "')");
                                }




                                if ($paymentStatus == 3) {

                                    $query = "SELECT
                                                                        `cp`.`course_payments_id`,
                                                                        `ps`.`payment_status`,
                                                                        `ps`.`payment_status_id`,
                                                                       `cp`.`due_date`,
                                                                        `cp`.`amount`,
                                                                       
                                                                        `cp`.`discount`,
                                                                        `cp`.`discount_type_discount_type_id`,
                                                                        `shi`.`student_id`,
                                                                        `shi`.`students_has_intakes_id`,
                                                                        `shi`.`status_status_id`,
                                                                        `ld`.`first_name`,
                                                                        `ld`.`last_name`,
                                                                        `ld`.`email`,
                                                                        `t`.`title`,
                                                                        `s`.`profile_img`,
                                                                        `c`.`code`,
                                                                        `c`.`decimal_places`,
                                                                        `pt`.`payment_type`,
                                                                        `pc`.`payment_category`,
                                                                        `shi`.`student_id` AS 'SIID',
                                                                        `intake`,
                                                                        `programme`,
                                                                        `cp`.`payment_plan_has_students_has_intakes_id`,
                                                                        IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                                                                        IFNULL(cpp.paid_amount, 0) AS paid_amount,
                                                                        IFNULL(cpp.total_discount, 0) AS total_discount,
                                                                        IFNULL(cpp.total_sub_panelty_amount, 0) AS total_sub_panelty_amount
                                                                        FROM `course_payments` AS `cp`
                                                                    INNER JOIN `payment_status` AS `ps` 
                                                                        ON `cp`.`payment_status_payment_status_id` = `ps`.`payment_status_id`
                                                                   
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
                                                                    LEFT JOIN `payment_type` AS `pt`
                                                                        ON `cp`.`payment_type_payment_type_id` = `pt`.`payment_type_id`    
                                                                  
                                                                    INNER JOIN `students` AS `s` 
                                                                        ON `shi`.`students_student_id` = `s`.`student_id`
                                                                    INNER JOIN `login_details` AS `ld`
                                                                        ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`    
                                                                    INNER JOIN `title` AS `t` 
                                                                        ON `s`.`title_title_id` = `t`.`title_id`
                                                                    LEFT JOIN `intakes` ON `shi`.`intakes_intake_id`=`intakes`.`intake_id`
                                                                    LEFT JOIN `programmes` ON `intakes`.`programmes_programme_id`=`programmes`.`programme_id`
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
                                                                    WHERE `cp`.`course_payments_id`='" . $coursePaymentData['course_payments_course_payments_id'] . "'
                                                                
                                                                    GROUP BY `cp`.`course_payments_id`
                                                                    ORDER BY `cp`.`course_payments_id` DESC
                                                                    ";

                                    $paymentData = Database::search($query)->fetch_assoc();


                                    $discountAmount = $paymentData["discount"];
                                    if ($paymentData["discount_type_discount_type_id"] == 3) {
                                        $discountAmount = $paymentData["amount"] / 100 * $paymentData["discount"];
                                    }
                                    $payableAmount = $paymentData["amount"] - $discountAmount + $paymentData["panelty_amount"] + $paymentData["total_sub_panelty_amount"];
                                    $balance = $payableAmount - $paymentData["paid_amount"] - $paymentData["total_discount"];

                                    // ---- Overpayment detection & cascading allocation ----
                                    // Verifying this payment can push the course_payments balance
                                    // to zero or below. Zero just settles it; negative means the
                                    // student overpaid, so the excess auto-applies to the student's
                                    // next pending payment(s) in this SAME course sequence
                                    // (payment_plan_has_students_has_intakes_id), cascading through
                                    // as many as needed and sharing this verification's receipt.
                                    $overpaymentAppliedSummary = array();

                                    if ($balance <= 0) {
                                        $payment_update_query = "`payment_status_payment_status_id`='5'";
                                        Database::iud("UPDATE `course_payments` SET " . $payment_update_query . " WHERE `course_payments_id`='" . $coursePaymentData["course_payments_course_payments_id"] . "'");

                                        if ($balance < 0) {
                                            $overpaymentAmount = abs($balance);

                                            $cascadeMethod = ($paymentMethod != 0) ? $paymentMethod : $coursePaymentData['payment_method_payment_method_id'];
                                            $cascadeDate = ($paymentDate != 0) ? $paymentDate : $coursePaymentData['date_paid'];

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
                                                    AND `cp`.`course_payments_id` != '" . $coursePaymentData['course_payments_course_payments_id'] . "'
                                                GROUP BY `cp`.`course_payments_id`
                                                ORDER BY `cp`.`due_date` ASC
                                            ");

                                            $remaining = $overpaymentAmount;

                                            if ($upcomingPayments->num_rows > 0) {
                                                while ($row = $upcomingPayments->fetch_assoc()) {
                                                    if ($remaining <= 0) {
                                                        break;
                                                    }
                                                    $rowBalance = calculatePayableBalance($row);
                                                    if ($rowBalance <= 0) {
                                                        continue;
                                                    }
                                                    $applyAmount = min($remaining, $rowBalance);
                                                    $fullySettled = (bccomp((string)$applyAmount, (string)$rowBalance, 2) === 0);

                                                    $allocColumns = "`course_payments_course_payments_id`,`payment_method_payment_method_id`
                                                    ,`date_paid`,`paid_amount`,`discount_amount`,`payment_status_payment_status_id`,`date_added`,`panelty_amount`,`course_payments_receipts_course_payments_receipt_id`";
                                                    $allocValues = "'" . $row['course_payments_id'] . "','" . $cascadeMethod . "','" . $cascadeDate . "','" . $applyAmount . "','0','3','" . $date . "','0','" . $receiptId . "'";

                                                    Database::iud("INSERT INTO `course_payment_has_payments` (" . $allocColumns . ") VALUES (" . $allocValues . ")");
                                                    $allocCoursePaymentHasPaymentId = Database::$connection->insert_id;

                                                    if ($fullySettled) {
                                                        Database::iud("UPDATE `course_payments` SET `payment_status_payment_status_id`='5' WHERE `course_payments_id`='" . $row['course_payments_id'] . "'");
                                                    }

                                                    $allocLogNote = 'Overpayment of ' . number_format($applyAmount, 2, '.', '') . ' auto-applied from Payment ID ' . $paymentId . ' upon verification';
                                                    Database::iud("INSERT INTO `payment_logs` (`course_payments_course_payments_id`,`course_payment_has_payment_id`,`users_user_id`,`date_updated`,`log`,`note`) VALUES ('" . $row['course_payments_id'] . "','" . $allocCoursePaymentHasPaymentId . "','" . $sessionUserObject['user_id'] . "','" . $date . "','Overpayment Auto-Applied on Verification','" . Database::escape($allocLogNote) . "')");

                                                    $overpaymentAppliedSummary[] = array(
                                                        'course_payments_id' => $row['course_payments_id'],
                                                        'amount_applied' => $applyAmount,
                                                        'fully_settled' => $fullySettled
                                                    );

                                                    $remaining -= $applyAmount;
                                                }
                                            }

                                            if ($remaining > 0) {
                                                $status->overpayment_note = 'Verified amount exceeds the total pending balance for this course by ' . number_format($remaining, 2, '.', '') . '. No further pending payments were available to auto-apply it to.';
                                            }
                                        }
                                    }

                                    if (!empty($overpaymentAppliedSummary)) {
                                        $status->overpayment_applied = $overpaymentAppliedSummary;
                                    }

                                    if ($receiptId !== null) {
                                        $status->receipt_id = $receiptId;
                                    }



                                    $userName = $paymentData['title'] . "." . $paymentData['first_name'] . " " . $paymentData['last_name'];
                                    if ($paymentData['payment_plan_has_students_has_intakes_id'] != null) {
                                        $mail = EmailNotificationService::build(
                                            EmailTemplateContent::RECEIPT_APPROVED,
                                            [
                                                "student_name" => $userName,
                                                "program" => $paymentData['programme'],
                                                "intake" => $paymentData['intake'],
                                                "admission_no" => $paymentData['SIID'],
                                                "paid_amount" => $coursePaymentData['paid_amount'],
                                                "payment_date" => $coursePaymentData['date_paid'],
                                            ]
                                        );
                                    } else {
                                        $mail = EmailNotificationService::build(
                                            EmailTemplateContent::RECEIPT_APPROVED_DIRECT_REG,
                                            [
                                                "student_name" => $userName,
                                                "paid_amount" => $coursePaymentData['paid_amount'],
                                                "payment_date" => $coursePaymentData['date_paid'],
                                            ]
                                        );
                                    }
                                    sendMail($paymentData['email'], $userName, $mail['subject'], $mail['body']);

                                    if ($paymentData['status_status_id'] == 2) {

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
                                                    WHERE `pphsi`.`students_has_intakes_students_has_intakes_id` = '" . $paymentData['students_has_intakes_id'] . "'
                                                        AND `cp`.`payment_status_payment_status_id` = '1'
                                                        AND `cp`.`due_date` < '$today'
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
                                        if (!$isOverdueWithBalance) {
                                            Database::iud("UPDATE `students_has_intakes` SET `status_status_id`='1' WHERE `students_has_intakes_id`='" . $paymentData['students_has_intakes_id'] . "'");

                                            $hasDeactivation = Database::search("SELECT * FROM `students_has_intakes_temp_activations` 
                                            WHERE `students_has_intakes_students_has_intakes_id`='" . $paymentData['students_has_intakes_id'] . "'
                                            AND `schedule_status`='PENDING' ORDER BY `id` DESC LIMIT 1");

                                            if ($hasDeactivation->num_rows > 0) {
                                                $deactivationData = $hasDeactivation->fetch_assoc();
                                                Database::iud("UPDATE `students_has_intakes_temp_activations` SET `schedule_status`='CANCELLED' 
                                                WHERE `students_has_intakes_temp_activation_id`='" . $deactivationData['students_has_intakes_temp_activation_id'] . "'");
                                            }
                                        }
                                    }
                                }

                                if ($paymentStatus == 4) {
                                    $userName = $paymentData['title'] . "." . $paymentData['first_name'] . " " . $paymentData['last_name'];
                                    $mail = EmailNotificationService::build(
                                        EmailTemplateContent::RECEIPT_REJECTED,
                                        [
                                            "student_name" => $userName,
                                            "program" => $paymentData['programme'],
                                            "intake" => $paymentData['intake'],
                                            "admission_no" => $paymentData['SIID'],
                                            "paid_amount" => $paymentData['paid_amount'],
                                            "payment_date" => $paymentData['date_paid'],
                                            "reject_reason" => $note == null ? "N/A" : $note,

                                        ]
                                    );
                                    sendMail($paymentData['email'], $userName, $mail['subject'], $mail['body']);
                                }
                                $status->done = "Payment status updated successfully!";
                            }
                        }
                    }
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
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

function normalizePhoneNumber($number)
{
    $number = preg_replace('/[\s\-()]/', '', $number);
    if (preg_match('/^0([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 1);
    }
    if (preg_match('/^94([7][0-9]{8})$/', $number)) {
        return '+94' . substr($number, 2);
    }
    if (preg_match('/^\+?[1-9][0-9]{7,14}$/', $number)) {
        if ($number[0] !== '+') {
            return '+' . $number;
        }
        return $number;
    }

    return false;
}
