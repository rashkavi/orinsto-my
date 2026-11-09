<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
$status = new stdClass();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../includes/UserClass.php');
require_once('../includes/connection.php');
require_once('../includes/UserPermissionsClass.php');
require_once('../includes/Project.php');
require_once('../includes/phpmailer/EmailLayoutRenderer.php');
require_once('../includes/phpmailer/EmailNotificationService.php');
require_once('../includes/phpmailer/EmailTemplateContent.php');
require_once('../includes/phpmailer/mail-process.php');
if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $sessionUserObject = SessionUser::getCurrentUserObject();
            if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);
                if (json_last_error() == JSON_ERROR_NONE) {

                    $studentId = $decoded['studentId'];

                    if (empty($studentId) || $studentId == 0 || !is_numeric($studentId)) {
                        $status->error = 'Invalid student Id';
                    } else {
                        $isValidStudent = Database::search("SELECT *,`students_has_intakes`.`student_id` AS `studentId` FROM `students_has_intakes` 
                        INNER JOIN `intakes` ON `students_has_intakes`.`intakes_intake_id` = `intakes`.`intake_id`
                        INNER JOIN `programmes` ON `intakes`.`programmes_programme_id` = `programmes`.`programme_id`
                        INNER JOIN `faculties` ON `programmes`.`faculties_faculty_id` = `faculties`.`faculty_id`
                        INNER JOIN `students` ON `students_has_intakes`.`students_student_id` = `students`.`student_id`
                        INNER JOIN `login_details` ON `students`.`login_details_login_details_id` = `login_details`.`login_details_id`
                        INNER JOIN `title` ON `students`.`title_title_id` = `title`.`title_id`
                        WHERE `students_has_intakes`.`students_has_intakes_id` = '$studentId' 
                       AND `students_has_intakes`.`status_status_id` = 1");

                        if ($isValidStudent->num_rows == 0) {
                            $status->error = 'Invalid student Id';
                        } else {

                            $student = $isValidStudent->fetch_assoc();

                            $lecturesResult = Database::search("
                                    SELECT
                                        `time_table`.`time_table_id`,
                                        `date`,
                                        `user_fname`,
                                        `user_lname`,
                                        `title`,
                                        `lecture_type`,
                                        TIME_FORMAT(`start_time`, '%h:%i %p') AS start_time,
                                        TIME_FORMAT(`end_time`, '%h:%i %p')   AS end_time,
                                        `subject`
                                    FROM `time_table`
                                    INNER JOIN `subjects`
                                        ON `time_table`.`subjects_subject_id` = `subjects`.`subject_id`
                                    INNER JOIN `lecturers`
                                        ON `time_table`.`lecturers_lecturers_id` = `lecturers`.`lecturers_id`
                                    INNER JOIN `users`
                                        ON `lecturers`.`users_user_id` = `users`.`user_id`
                                    INNER JOIN `title`
                                        ON `users`.`title_title_id` = `title`.`title_id`
                                    INNER JOIN `lecture_type`
                                        ON `time_table`.`lecture_type_lecture_type_id` = `lecture_type`.`lecture_type_id`
                                    WHERE  `subjects`.`levels_level_id` IN (
                                            SELECT `level_id`
                                            FROM `levels`
                                            WHERE `intakes_intake_id` = '" . $student['intakes_intake_id'] . "'
                                    )
                                    AND (
                                            `subjects`.`subject_type_subject_type_id` = 1
                                            OR (
                                                `subjects`.`subject_type_subject_type_id` = 2
                                                AND EXISTS (
                                                    SELECT 1
                                                    FROM `students_has_subjects`
                                                    WHERE `students_student_id` = '" . $student['students_student_id'] . "'
                                                    AND `subjects_subject_id` = `subjects`.`subject_id`
                                                )
                                            )
                                    )
                                    ORDER BY `date` ASC, `start_time` ASC
                                ");

                            $lectures = [];
                            if ($lecturesResult && $lecturesResult->num_rows > 0) {
                                while ($row = $lecturesResult->fetch_assoc()) {
                                    $lectures[] = $row;
                                }
                            }

                            if (empty($lectures)) {
                                echo json_encode(['error' => 'No lectures scheduled for this intake']);
                                exit;
                            }

                            // ── Helper functions ─────────────────────────────────────────────────────
                            function esc($s)
                            {
                                return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
                            }

                            // ── Generate PDF HTML ────────────────────────────────────────────────────
                            $scheduleHtml = buildTimetablePdf($student, $lectures);

                            // ── Generate PDF from HTML ──────────────────────────────────────────────
                            $pdfPath = null;
                            $usePdf  = false;

                            $dompdfPaths = [
                                '../vendor/autoload.php',
                                '../includes/dompdf/autoload.inc.php',
                            ];

                            foreach ($dompdfPaths as $path) {
                                if (file_exists($path)) {
                                    require_once $path;
                                    $usePdf = true;
                                    break;
                                }
                            }

                            if ($usePdf && class_exists('Dompdf\Dompdf')) {
                                try {
                                    $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                                    $dompdf->loadHtml($scheduleHtml);
                                    $dompdf->setPaper('A4', 'portrait');
                                    $dompdf->render();

                                    $pdfOutput = $dompdf->output();
                                    $pdfPath   = sys_get_temp_dir() . '/timetable_' . $studentId . '_' . time() . '.pdf';
                                    file_put_contents($pdfPath, $pdfOutput);
                                } catch (Exception $e) {
                                    echo json_encode(['error' => 'PDF Error', 'message' => $e->getMessage()]);
                                    exit;
                                }
                            } else {
                                echo json_encode(['error' => 'PDF generation library not found']);
                                exit;
                            }

                            // ── Build + send email ───────────────────────────────────────────────────
                            $studentName = $student['title'] . '. ' . $student['first_name'] . ' ' . $student['last_name'];
                            $toEmail     = $student['email'];

                            $emailContent = EmailNotificationService::build(
                                EmailTemplateContent::TIMETABLE_EMAIL,
                                [
                                    'student_name'   => $studentName,
                                    'programme'      => $student['programme'],
                                    'intake'         => $student['intake'],
                                    'lecture_count'  => (string)count($lectures),
                                ]
                            );

                            require_once '../includes/phpmailer/src/Exception.php';
                            require_once '../includes/phpmailer/src/PHPMailer.php';
                            require_once '../includes/phpmailer/src/SMTP.php';

                            try {
                                $mail = new PHPMailer(true);
                                $mail->SMTPDebug  = 0;
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.titan.email';
                                $mail->SMTPAuth   = true;
                                $mail->Username   = 'no-reply@orinstolms.com'; // TODO: make institute-aware if multi-tenant SMTP is used
                                $mail->Password   = 'Orinsto#@!2026LMS';        // TODO: move to config, don't hardcode
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                $mail->Port       = 465;

                                $mail->setFrom('no-reply@orinstolms.com', Project::getBaseName());
                                $mail->addAddress($toEmail, $studentName);

                                if ($usePdf && $pdfPath && file_exists($pdfPath)) {
                                    $mail->addAttachment(
                                        $pdfPath,
                                        'Timetable-.pdf',
                                        PHPMailer::ENCODING_BASE64,
                                        'application/pdf'
                                    );
                                }

                                $mail->isHTML(true);
                                $mail->Subject = $emailContent['subject'];
                                $mail->Body    = $emailContent['body'];
                                $mail->AltBody = 'Dear ' . $studentName . ', your timetable is attached. Total lectures: ' . count($lectures);

                                $mail->send();

                                if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);

                                echo json_encode(['done' => 'Timetable email sent successfully to ' . $toEmail]);
                            } catch (Exception $e) {
                                if ($pdfPath && file_exists($pdfPath)) @unlink($pdfPath);
                                echo json_encode(['error' => 'Failed to send email: ' . $mail->ErrorInfo]);
                                exit;
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

function buildTimetablePdf($student, $lectures)
{

    // ── Build lecture rows ──────────────────────────────────────────────
    $lectureRows = '';
    $idx = 0;

    foreach ($lectures as $l) {
        $bg = ($idx % 2 === 0) ? '#ffffff' : '#f8fafc';
        $idx++;

        $lecturerName = esc($l['title'] . '. ' . $l['user_fname'] . ' ' . $l['user_lname']);
        $timeRange    = esc($l['start_time']) . ' - ' . esc($l['end_time']);
        $dateDisplay  = !empty($l['date']) ? esc(date('d M Y', strtotime($l['date']))) : '';

        $C = 'padding:8px 10px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

        $lectureRows .= '<tr style="background:' . $bg . ';">'
            . '<td style="' . $C . 'color:#9ca3af;width:22px;">' . $idx . '</td>'
            . '<td style="' . $C . 'white-space:nowrap;font-weight:700;color:#1e3a5f;">' . $dateDisplay . '</td>'
            . '<td style="' . $C . 'white-space:nowrap;font-weight:700;color:#1e3a5f;">' . $timeRange . '</td>'
            . '<td style="' . $C . 'font-weight:600;color:#111827;">' . esc($l['subject']) . '</td>'
            . '<td style="' . $C . 'color:#6b7280;">' . $lecturerName . '</td>'
            . '<td style="' . $C . 'color:#6b7280;">' . esc($l['lecture_type']) . '</td>'
            . '</tr>';
    }

    if (!$lectureRows) {
        $lectureRows = '<tr><td colspan="6" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No lectures scheduled</td></tr>';
    }

    $TH = 'padding:8px 10px;font-size:9px;font-weight:600;color:rgba(255,255,255,.85);letter-spacing:.04em;';

    $studentName = esc($student['title'] . '. ' . $student['first_name'] . ' ' . $student['last_name']);
    $programme   = esc($student['programme']);
    $intake      = esc($student['intake']);
    $faculty     = esc($student['faculty']);
    $logoUrl     = '../assets/imgs/common/logo.png';
    $baseName    = esc(Project::getBaseName());
    $year        = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Timetable</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: "Segoe UI", system-ui, -apple-system, sans-serif; font-size: 11px; line-height: 1.5; color: #111827; background: #fff; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    @page { size: A4 portrait; margin: 8mm 10mm; }
  }
  table { page-break-inside: auto; }
  tr { page-break-inside: avoid; }
</style>
</head>
<body>

<!-- ════ HEADER BAND ════ -->
<div style="background:#1e3a5f;padding:20px 28px;">
<table style="width:100%;border-collapse:collapse;"><tr>

<!-- Left — logo -->
<td style="vertical-align:top;width:55%;">
  <img src="https://dashboard.orinstolms.com/assets/imgs/common/logo.png" alt="{$baseName}" style="height:44px;width:auto;display:block;margin-bottom:8px;">
  <div style="font-size:8px;color:rgba(255,255,255,.45);text-transform:uppercase;letter-spacing:.12em;">Lecture Timetable</div>
</td>

<!-- Right — student -->
<td style="vertical-align:top;text-align:right;">
  <div style="font-size:8px;font-weight:700;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.1em;margin-bottom:3px;">Full Schedule</div>
  <div style="font-size:14px;font-weight:800;color:#fff;letter-spacing:-.3px;">{$intake}</div>
</td>
</tr></table>
</div>

<!-- ════ BODY ════ -->
<div style="padding:20px 28px;">

<!-- Student + Programme -->
<table style="width:100%;border-collapse:collapse;margin-bottom:20px;"><tr>
<td style="width:50%;vertical-align:top;padding-right:10px;">
  <div style="border:1px solid #e5e7eb;padding:12px 14px;background:#fafafa;">
    <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;">Student</div>
    <div style="font-size:13px;font-weight:700;color:#111827;">{$studentName}</div>
    <div style="font-size:10px;color:#6b7280;margin-top:4px;">Email : {$student['email']}</div>
    <div style="font-size:10px;color:#6b7280;margin-top:4px;">Student ID :{$student['studentId']}</div>
  </div>
</td>
<td style="width:50%;vertical-align:top;padding-left:10px;">
  <div style="border:1px solid #e5e7eb;padding:12px 14px;background:#fafafa;">
    <div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;">Programme</div>
    <div style="font-size:11px;font-weight:700;color:#111827;">{$faculty}</div>
    <div style="font-size:10px;color:#6b7280;margin-top:3px;">{$programme}<br>{$intake}</div>
  </div>
</td>
</tr></table>

<!-- Section label -->
<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
  <span style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.12em;white-space:nowrap;">Lectures</span>
  <div style="flex:1;height:1px;background:#f3f4f6;"></div>
</div>

<!-- Timetable -->
<div style="border:1px solid #e5e7eb;overflow:hidden;">
<table style="width:100%;border-collapse:collapse;">
<thead><tr style="background:#1e3a5f;">
  <th style="{$TH}text-align:left;">#</th>
  <th style="{$TH}text-align:left;">Date</th>
  <th style="{$TH}text-align:left;">Time</th>
  <th style="{$TH}text-align:left;">Subject</th>
  <th style="{$TH}text-align:left;">Lecturer</th>
  <th style="{$TH}text-align:left;">Type</th>
</tr></thead>
<tbody>{$lectureRows}</tbody>
</table>
</div>

</div><!-- /body -->

<!-- ════ FOOTER ════ -->
<div style="border-top:1px dashed #e5e7eb;padding:10px 28px;background:#fafafa;">
  <div style="font-size:9px;color:#9ca3af;">This is a computer-generated timetable and does not require a physical signature.</div>
</div>

<div style="text-align:center;padding:10px 8px;border-top:1px solid #f3f4f6;background:#fff;">
  <span style="font-size:8px;color:#9ca3af;letter-spacing:.02em;">
    &copy;{$year} ENLIXO Uni.All Rights Reserved | Powered by ENVIIZ Softwares (PVT) LTD
  </span>
</div>

</body>
</html>
HTML;
}
