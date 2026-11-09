<?php

declare(strict_types=1);

final class EmailTemplateContent
{
    public const WELCOME_STUDENT = 'welcome_student';
    public const PASSWORD_SETUP = 'password_setup';
    public const PASSWORD_RESET = 'password_reset';
    public const PASSWORD_SETUP_SUCCESS = 'password_setup_success';
    public const APPLICATION_RECEIVED = 'application_received';
    public const APPLICATION_APPROVED = 'application_approved';
    public const APPLICATION_REJECTED = 'application_rejected';
    public const PAYMENT_REMINDER_5_DAYS = 'payment_reminder_5_days';
    public const PAYMENT_REMINDER_1_DAY = 'payment_reminder_1_day';
    public const PAYMENT_OVERDUE_3_DAYS = 'payment_overdue_3_days';
    public const PAYMENT_OVERDUE_7_DAYS = 'payment_overdue_7_days';
    public const ADMISSION_NEW_BATCH = 'admission_new_batch';
    public const RECEIPT_APPROVED = 'receipt_approved';
    public const RECEIPT_REJECTED = 'receipt_rejected';
    public const RECEIPT_APPROVED_DIRECT_REG = 'receipt_approved_direct_reg';
    public const BIRTHDAY_WISH = 'birthday_wish';
    public const TIMETABLE_EMAIL = 'timetable_email';

    public static function get(string $type, array $data = []): array
    {
        $baseName = Project::getBaseName();
        $basePortalURL  = Project::getBasePortalUrl();
        $baseURL  = Project::getBaseUrl();

        $templates = [


            self::PASSWORD_SETUP => [
                'subject' => "Complete Your {$baseName} System Registration",
                'heading' => "Complete Your {$baseName} System Registration",
                'body' => self::renderBody([
                    "Thank you for creating an account with <strong>{$baseName}</strong>.",
                    "To complete your registration, please verify your email address and set up your password by clicking the button below:"
                ]),
                'button_text' => 'Verify Email & Setup Password',
                'button_url' => $data['action_url'] ?? '',
                'params' => ['action_url'],
            ],

            self::PASSWORD_RESET => [
                'subject' => "Password Reset Request - {$baseName}",
                'heading' => 'Password Reset Request',
                'body' => self::renderBody([
                    "You recently requested to reset your <strong>{$baseName}</strong> system password.",
                    "Please click the button below to proceed with creating a new password.",
                    "<strong>Note:</strong> This password reset link will expire in 15 minutes. If you didn't request a password reset, please contact support immediately."
                ]),
                'button_text' => 'Reset Your Password',
                'button_url' => $data['action_url'] ?? '',
                'params' => ['action_url'],
            ],

            self::PASSWORD_SETUP_SUCCESS => [
                'subject' => "Welcome to {$baseName} – Your Account is Ready",

                'heading' => 'Account Activation Successful',

                'body' => self::renderBody([
                    "Dear " . self::value($data, 'user_name', 'User') . ",",

                    "Your password has been successfully configured, and your <strong>{$baseName}</strong> account is now fully active.",

                    "You may now securely access your account using your registered credentials:",


                    "To ensure the security of your account, please maintain the confidentiality of your login credentials and avoid sharing them with external parties.",

                    "Click the button below to log in and access your admin dashboard."
                ]),

                'button_text' => 'Access Your Dashboard',

                'button_url' => $data['login_url'] ?? '',

                'params' => [
                    'user_name',
                    'login_url'
                ],
            ],

            self::APPLICATION_RECEIVED => [
                'subject' => "Application Received - {$baseName}",
                'heading' => "Application Received",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "We are pleased to inform you that your application to register with <strong>{$baseName}</strong> has been successfully received.",
                    "Our admissions team is currently reviewing your application. You will be notified once the review process is completed.",
                    "If you have any questions or need further assistance, please feel free to contact us."
                ]),
                'button_text' => 'Visit Website',
                'button_url' => $basePortalURL,
                'params' => ['student_name'],
            ],

            self::APPLICATION_APPROVED => [
                'subject' => "Application Approved - {$baseName}",
                'heading' => "Application Approved",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Congratulations! We are pleased to inform you that your application to <strong>{$baseName}</strong> has been successfully approved.",
                    "You may now proceed with the next steps of your registration and academic onboarding process.",
                    "If you require any assistance, please feel free to contact our admissions team."
                ]),
                'params' => ['student_name'],
            ],

            self::APPLICATION_REJECTED => [
                'subject' => "Application Status Update - {$baseName}",
                'heading' => "Application Not Approved",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Thank you for your interest in <strong>{$baseName}</strong>.",
                    "After reviewing your application, we regret to inform you that your application was not approved at this time.",
                    "Please review the note below and update your application with the required corrections.",
                    self::detailList([
                        'Review Note' => self::value($data, 'note', 'No additional note provided'),
                    ]),
                    "Once updated, our admissions team will review your application again."
                ]),
                'button_text' => 'Update Application',
                'button_url' => $data['application_update_url'] ?? $basePortalURL,
                'params' => ['student_name', 'note', 'application_update_url'],
            ],

            self::PAYMENT_REMINDER_5_DAYS => [
                'subject' => "Payment Reminder - Installment Due in 5 Days",
                'heading' => "Payment Reminder",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "This is a friendly reminder to settle your upcoming payment dues.",
                    "Please note that the academic department will not be able to accept your assignments if your payments are not completed.",
                    "Additionally, if the payment is not made by the due date, a penalty fee will be applied for every day of delay.",
                    self::detailList([
                        'Installment No' => self::value($data, 'installment_no'),
                        'Amount' => self::value($data, 'amount'),
                        'Due Date' => self::value($data, 'due_date'),
                    ]),
                    self::detailList([
                        'Account Name' => self::value($data, 'account_name', 'Summerset Campus'),
                        'Bank' => self::value($data, 'bank_name', 'Commercial Bank'),
                        'Account Number' => self::value($data, 'account_number', '1000997970'),
                        'Branch' => self::value($data, 'branch_name', 'Maharagama'),
                        'Reference' => self::value($data, 'reference', 'Your Name'),
                    ]),
                    "Alternatively, you can make the payment through our Internet Payment Gateway using the button below.",
                    "Once the payment is made, please upload the payment slip through the LMS portal.",
                    "If you have any questions or need further assistance, please do not hesitate to contact us.",
                    "<em>Please ignore this email if you have already paid your dues.</em>"
                ]),
                'button_text' => 'Pay Online via LMS',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'installment_no', 'amount', 'due_date'],
            ],

            self::PAYMENT_REMINDER_1_DAY => [
                'subject' => "Payment Reminder - Installment Due Tomorrow",
                'heading' => "Payment Reminder",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "This is a reminder that your installment payment is due tomorrow.",
                    self::detailList([
                        'Installment No' => self::value($data, 'installment_no'),
                        'Amount' => self::value($data, 'amount'),
                        'Due Date' => self::value($data, 'due_date'),
                    ]),
                    "Please complete the payment on or before the due date to avoid penalties."
                ]),
                'button_text' => 'Pay Online via LMS',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'installment_no', 'amount', 'due_date'],
            ],

            self::PAYMENT_OVERDUE_3_DAYS => [
                'subject' => "Overdue Payment Reminder - 3 Days",
                'heading' => "Overdue Payment Reminder",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Your installment payment is overdue by 3 days.",
                    self::detailList([
                        'Installment No' => self::value($data, 'installment_no'),
                        'Outstanding Amount' => self::value($data, 'amount'),
                        'Due Date' => self::value($data, 'due_date'),
                    ]),
                    "Please settle the payment as soon as possible to avoid further penalties."
                ]),
                'button_text' => 'Pay Online via LMS',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'installment_no', 'amount', 'due_date'],
            ],

            self::PAYMENT_OVERDUE_7_DAYS => [
                'subject' => "Final Payment Reminder - 7 Days Overdue",
                'heading' => "Final Payment Reminder",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Your installment payment is overdue by 7 days.",
                    self::detailList([
                        'Installment No' => self::value($data, 'installment_no'),
                        'Outstanding Amount' => self::value($data, 'amount'),
                        'Due Date' => self::value($data, 'due_date'),
                    ]),
                    "Please settle the outstanding amount immediately to avoid further inconvenience."
                ]),
                'button_text' => 'Pay Online via LMS',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'installment_no', 'amount', 'due_date'],
            ],

            self::ADMISSION_NEW_BATCH => [
                'subject' => "Admission to New Batch - {$baseName}",
                'heading' => "Admission to New Batch",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "We are pleased to inform you that you have successfully enrolled in the following program:",
                    self::detailList([
                        'Faculty' => self::value($data, 'faculty'),
                        'Program' => self::value($data, 'program'),
                        'Intake' => self::value($data, 'intake'),
                        'Batch' => self::value($data, 'batch'),
                        'Admission No' => self::value($data, 'admission_no'),
                    ]),
                    "If you have any questions or require further assistance, please do not hesitate to contact us."
                ]),
                'button_text' => 'Open LMS Portal',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'faculty', 'program', 'intake', 'batch', 'admission_no'],
            ],


            self::RECEIPT_APPROVED => [
                'subject' => "Payment Accepted - {$baseName}",
                'heading' => "Payment Accepted",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Your submitted payment has been reviewed and accepted. Your payment record has been successfully updated.",
                    self::detailList([
                        'Program' => self::value($data, 'program'),
                        'Intake' => self::value($data, 'batch'),
                        'Admission No' => self::value($data, 'admission_no'),
                        'Paid Amount' => self::value($data, 'paid_amount'),
                        'Payment Date' => self::value($data, 'payment_date'),
                    ]),
                ]),
                'button_text' => 'View LMS Portal',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'program', 'intake', 'admission_no', 'paid_amount', 'payment_date'],
            ],

            self::RECEIPT_REJECTED => [
                'subject' => "Payment Rejected - {$baseName}",
                'heading' => "Payment Rejected",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Your submitted payment could not be approved after review. Please check the details and upload a valid payment slip again.",
                    self::detailList([
                        'Program' => self::value($data, 'program'),
                        'Intake' => self::value($data, 'intake'),
                        'Admission No' => self::value($data, 'admission_no'),
                        'Paid Amount' => self::value($data, 'paid_amount'),
                        'Payment Date' => self::value($data, 'payment_date'),
                        'Reject Reason' => self::value($data, 'reject_reason'),
                    ]),
                ]),
                'button_text' => 'Upload Again',
                'button_url' => $data['portal_url'] ?? $basePortalURL,
                'params' => ['student_name', 'program', 'intake', 'admission_no', 'paid_amount', 'payment_date', 'reject_reason'],
            ],

            self::WELCOME_STUDENT => [
                'subject' => "Welcome to {$baseName} – Complete Your Registration",
                'heading' => "Welcome to {$baseName}",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "We are excited to welcome you to <strong>{$baseName}</strong>!",
                    "Your student account has been created successfully. To activate your account and access the student portal, please complete your registration by setting your password.",
                    "<strong>Your Enrollment Details</strong>",
                    self::detailList([
                        'Program' => self::value($data, 'program'),
                        'Intake' => self::value($data, 'intake'),
                        'Student ID' => self::value($data, 'student_id'),
                    ]),
                    "Click the button below to verify your email and set your password to get started with your studies."
                ]),

                'button_text' => 'Activate Account & Set Password',
                'button_url' => $data['action_url'] ?? $basePortalURL,
                'params' => [
                    'student_name',
                    'action_url',
                    'program',
                    'intake',
                    'student_id'
                ],
            ],
            self::RECEIPT_APPROVED_DIRECT_REG => [
                'subject' => "Payment Accepted - {$baseName}",
                'heading' => "Payment Accepted",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Your submitted payment has been reviewed and accepted. Your payment record has been successfully updated.",
                    self::detailList([
                        'Paid Amount'  => self::value($data, 'paid_amount'),
                        'Payment Date' => self::value($data, 'payment_date'),
                    ]),
                    "Thank you for your payment. Our team will be in touch with further details regarding your enrollment.",
                ]),
                'params' => ['student_name', 'paid_amount', 'payment_date'],
            ],

            self::BIRTHDAY_WISH => [
                'subject' => "Happy Birthday, {$baseName} Family!",
                'heading' => "Happy Birthday!",
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "The entire {$baseName} family wishes you a day filled with joy and a year filled with achievement. As you continue your academic journey, may you reach new heights and accomplish your goals with confidence.",
                    "Celebrate your special day and make it memorable!",
                    "Best Wishes from {$baseName}."
                ]) . '
                    <div style="text-align: center; margin: 30px 0;">
                        <img src="' . $baseURL . '/assets/imgs/common/bday-card.jpg" 
                            alt="Happy Birthday" 
                            style="max-width: 100%; height: auto; border-radius: 8px;">
                    </div>
                ',
                'params' => ['student_name'],
            ],

            self::TIMETABLE_EMAIL => [
                'subject' => "Your Lecture Timetable - {$baseName}",
                'heading' => 'Your Lecture Timetable',
                'body' => self::renderBody([
                    "Dear " . self::value($data, 'student_name', 'Student') . ",",
                    "Please find attached your lecture timetable for the <strong>" . self::value($data, 'programme') . "</strong> programme.",
                    self::detailList([
                        'Programme'     => self::value($data, 'programme'),
                        'Intake'        => self::value($data, 'intake'),
                        'Total Lectures' => self::value($data, 'lecture_count'),
                    ]),
                    "The attached PDF lists all scheduled lectures along with their date, time, subject, lecturer, and lecture type.",
                    "Please check your timetable regularly, as schedules may be updated from time to time. This is a computer-generated timetable and does not require a physical signature.",
                ]),
                'button_text' => '',
                'button_url'  => '',
                'params' => [
                    'student_name',
                    'programme',
                    'intake',
                    'lecture_count',
                ],
            ],
        ];



        if (!isset($templates[$type])) {
            throw new InvalidArgumentException("Unsupported email template type: {$type}");
        }

        self::validateRequiredParams($templates[$type]['params'], $data, $type);

        return $templates[$type];
    }

    private static function validateRequiredParams(array $required, array $data, string $type): void
    {
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new InvalidArgumentException("Missing required parameter '{$key}' for email type '{$type}'");
            }
        }
    }

    private static function value(array $data, string $key, string $default = ''): string
    {
        return htmlspecialchars((string)($data[$key] ?? $default), ENT_QUOTES, 'UTF-8');
    }

    private static function renderBody(array $blocks): string
    {
        $html = '';
        foreach ($blocks as $block) {
            if ($block === '') {
                continue;
            }
            $html .= "<p>{$block}</p>";
        }
        return $html;
    }

    private static function detailList(array $items): string
    {
        $html = '
            <table cellpadding="0" cellspacing="0" border="0" width="100%" 
                style="
                    margin:18px 0;
                    border-collapse:separate;
                    border-spacing:0;
                    background:#F8FAFC;
                    border:1px solid #E5E7EB;
                    border-radius:14px;
                    overflow:hidden;
                ">
        ';

        foreach ($items as $label => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            $html .= '
                <tr>
                    <td style="
                        padding:12px 16px;
                        font-size:13px;
                        font-weight:600;
                        color:#64748B;
                        width:38%;
                        border-bottom:1px solid #E5E7EB;
                        background:#F1F5F9;
                    ">
                        ' . htmlspecialchars($label) . '
                    </td>
    
                    <td style="
                        padding:12px 16px;
                        font-size:14px;
                        font-weight:600;
                        color:#0F172A;
                        border-bottom:1px solid #E5E7EB;
                        background:#FFFFFF;
                    ">
                        ' . htmlspecialchars((string)$value) . '
                    </td>
                </tr>
            ';
        }

        $html .= '</table>';

        return $html;
    }
}
