<?php

/**
 * api/generateBatchActivityReportPdf.php
 * ------------------------------------------
 * Generates a downloadable PDF of the full batch activity report for one
 * intake: summary cards (as a plain table, no sparklines — not meaningful
 * in print), the full Active Students list, and the full Deactivated
 * Students list. Triggered by the "Generate Batch Report PDF" button.
 *
 * FLAG — PDF LIBRARY ASSUMPTION: I have no visibility into which PDF
 * library this codebase already uses (only PhpSpreadsheet/Excel exports
 * are in the material I've seen). This file is written against **Dompdf**:
 *
 *     composer require dompdf/dompdf
 *
 * as the most common HTML-to-PDF choice for Bootstrap-styled admin exports.
 * If you're already standardized on mPDF or TCPDF, only the "SETUP" block
 * right below needs to change (the require + `new Dompdf(...)` /
 * `->render()` / `->stream()` calls) — the HTML template further down can
 * stay as-is since it's just Bootstrap-ish inline-styled markup.
 */

require_once './../includes/UserClass.php';
require_once './../includes/UserPermissionsClass.php';
require_once "./../includes/connection.php";
require_once '../includes/classes/BatchDashboardSummary.php';

// ── SETUP (swap this block if not using Dompdf) ────────────────────────────
require_once './../vendor/autoload.php'; // ASSUMPTION: composer autoload path
use Dompdf\Dompdf;
use Dompdf\Options;

if (!SessionUser::hasValidSession()) {
    http_response_code(403);
    exit('Session expired.');
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

const STATUS_ACTIVE_PDF  = 1;
const STATUS_OVERDUE_PDF = 2;

$intakeId = isset($_GET['intake-id']) ? (int)$_GET['intake-id'] : 0;
if ($intakeId <= 0) {
    http_response_code(400);
    exit('Missing or invalid intake-id.');
}

// ── Resolve intake / programme / faculty name for the header ──────────────
$intakeInfoRow = Database::search("
    SELECT i.`intake`, p.`programme`, f.`faculty`
    FROM `intakes` i
    INNER JOIN `programmes` p ON i.`programmes_programme_id` = p.`programme_id`
    INNER JOIN `faculties` f ON p.`faculties_faculty_id` = f.`faculty_id`
    WHERE i.`intake_id` = '{$intakeId}'
")->fetch_assoc();

$batchLabel = $intakeInfoRow
    ? htmlspecialchars($intakeInfoRow['faculty'] . ' / ' . $intakeInfoRow['programme'] . ' / ' . $intakeInfoRow['intake'])
    : "Intake #{$intakeId}";

// ── Summary cards (reuse the same source as the on-screen cards) ──────────
$cards = BatchDashboardSummary::getBatchDashboardSummary($intakeId);

// ── Full (unpaginated) student lists ───────────────────────────────────────
function fetchAllStudentsForPdf(int $intakeId, int $statusId): array
{
    $rs = Database::search("
        SELECT shi.`student_id` AS `student_id_number`, ld.first_name,
            ld.last_name
        FROM `students_has_intakes` shi
        INNER JOIN `students` st ON shi.`students_student_id` = st.`student_id`
          INNER JOIN `login_details` ld ON st.`login_details_login_details_id` = ld.`login_details_id`
        WHERE shi.`intakes_intake_id` = '{$intakeId}'
          AND shi.`status_status_id` = '{$statusId}'
        ORDER BY shi.student_id ASC
    ");
    return $rs->fetch_all(MYSQLI_ASSOC);
}

$activeStudents = fetchAllStudentsForPdf($intakeId, STATUS_ACTIVE_PDF);
$deactivatedStudents = fetchAllStudentsForPdf($intakeId, STATUS_OVERDUE_PDF);

// ── HTML template ───────────────────────────────────────────────────────
ob_start();
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #222;
        }

        h1 {
            font-size: 18px;
            margin-bottom: 2px;
        }

        h2 {
            font-size: 13px;
            margin: 18px 0 8px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
        }

        .sub {
            color: #666;
            margin-bottom: 16px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 5px 8px;
            text-align: left;
        }

        th {
            background: #f3f3f3;
        }

        .cards-grid {
            width: 100%;
        }

        .cards-grid td {
            width: 25%;
            vertical-align: top;
        }

        .card-title {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }

        .card-value {
            font-size: 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Batch Activity Report</h1>
    <div class="sub"><?= $batchLabel ?> &middot; Generated <?= date('Y-m-d H:i') ?></div>

    <h2>Summary</h2>
    <table class="cards-grid">
        <?php foreach (array_chunk($cards, 4) as $rowOfCards) { ?>
            <tr>
                <?php foreach ($rowOfCards as $card) { ?>
                    <td>
                        <div class="card-title"><?= htmlspecialchars($card['title']) ?></div>
                        <div class="card-value"><?= htmlspecialchars($card['value']) ?></div>
                    </td>
                <?php } ?>
            </tr>
        <?php } ?>
    </table>

    <h2>Active Students (<?= count($activeStudents) ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Student ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($activeStudents)) { ?>
                <tr>
                    <td colspan="3">No active students in this intake.</td>
                </tr>
            <?php } ?>
            <?php foreach ($activeStudents as $idx => $s) { ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars(trim($s['first_name'] . ' ' . $s['last_name'])) ?></td>
                    <td><?= htmlspecialchars($s['student_id_number']) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <h2>Deactivated Students — Payment Due (<?= count($deactivatedStudents) ?>)</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Student</th>
                <th>Student ID</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($deactivatedStudents)) { ?>
                <tr>
                    <td colspan="3">No deactivated students in this intake.</td>
                </tr>
            <?php } ?>
            <?php foreach ($deactivatedStudents as $idx => $s) { ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars(trim($s['first_name'] . ' ' . $s['last_name'])) ?></td>
                    <td><?= htmlspecialchars($s['student_id_number']) ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

</body>

</html>
<?php
$html = ob_get_clean();

// ── Render + stream (Dompdf) ────────────────────────────────────────────
$options = new Options();
$options->set('isRemoteEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'batch-activity-report-intake-' . $intakeId . '-' . date('Ymd') . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
