<?php
/**
 * api/updateIntakeStudentsStatus.php
 * ------------------------------------------------------------------
 * Change intake students' status (Active / Inactive / Discontinued) with a
 * reason, and keep a log row per student in `students_has_intakes_logs`.
 *
 * POST JSON
 *   {action:'reasons'}                         → list from students_inactive_reasons
 *   {action:'preview', intakeId, update, ...}  → how many students will change
 *   {action:'update',  intakeId, update, ..., reasonId?, note?}   (default action)
 *
 * Which students (same three modes the page already uses):
 *   checkedStudents   [shiId, ...]  → those students
 *   uncheckedStudents [shiId, ...]  → every student in the intake except these
 *   both empty                      → the students on the current page
 *                                     (pageStudents [shiId, ...] sent by the page)
 *
 * Allowed changes (anything else is skipped and reported):
 *   → Active        from Inactive               (temporary — see below)
 *   → Inactive      from Active
 *   → Discontinued  from Active or Inactive   (cannot be undone)
 *
 * Activate = temporary activation with an automatic deactivation date:
 *   - deactivateDate (Y-m-d) is required: tomorrow up to today + 3 days (Asia/Colombo);
 *     every other date is blocked. The student becomes Active straight away.
 *   - A PENDING row goes into students_has_intakes_temp_activations;
 *     cron/intake-student-auto-deactivation-cron.php sets the student back to
 *     Inactive on that date.
 *   - Academic coordinators (AcademicPro, not Pro) can activate each student
 *     once per calendar month.
 *   - Deactivate / Discontinue by hand cancel the PENDING auto-deactivation.
 */
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

const ISS_STATUS_NAMES = [1 => 'Active', 2 => 'Inactive', 3 => 'Discontinued'];
const ISS_ALLOWED_FROM = [1 => [2], 2 => [1], 3 => [1, 2]];
const ISS_MAX_STUDENTS = 5000;
const ISS_DEACTIVATE_MIN_DAYS = 1;   // auto-deactivate date: from tomorrow …
const ISS_DEACTIVATE_MAX_DAYS = 3;   // … up to today + 3 days (other dates blocked)
const ISS_COORD_MONTHLY_LIMIT = 1;   // academic coordinator: activations per student per calendar month
const ISS_TZ = 'Asia/Colombo';

class IssException extends Exception
{
    public $fields = [];
}

/* ----------------------------------------------------------- db helpers */
function iss_conn()
{
    if (!Database::$connection) {
        Database::search("SELECT 1");
    }
    return Database::$connection;
}
function iss_run($sql, $types = '', array $params = [])
{
    $stmt = iss_conn()->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . iss_conn()->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $e = $stmt->error;
        $stmt->close();
        throw new Exception('Query failed: ' . $e);
    }
    return $stmt;
}
function iss_rows($sql, $types = '', array $params = [])
{
    $stmt = iss_run($sql, $types, $params);
    $res = $stmt->get_result();
    $out = [];
    while ($res && ($r = $res->fetch_assoc())) {
        $out[] = $r;
    }
    $stmt->close();
    return $out;
}
function iss_exec($sql, $types = '', array $params = [])
{
    $stmt = iss_run($sql, $types, $params);
    $n = $stmt->affected_rows;
    $stmt->close();
    return $n;
}
function iss_ids($raw)
{
    if (!is_array($raw)) return [];
    $ids = [];
    foreach ($raw as $v) {
        if (is_numeric($v) && (int)$v > 0) $ids[(int)$v] = true;
    }
    return array_keys($ids);
}
function iss_in(array $ids)
{
    return [implode(',', array_fill(0, count($ids), '?')), str_repeat('i', count($ids))];
}

/* ------------------------------- temporary activation / auto-deactivation */
function iss_now()
{
    return new DateTime('now', new DateTimeZone(ISS_TZ));
}
/** [min, max] allowed auto-deactivate dates as Y-m-d. */
function iss_deactivate_range()
{
    $min = iss_now()->modify('+' . ISS_DEACTIVATE_MIN_DAYS . ' days')->format('Y-m-d');
    $max = iss_now()->modify('+' . ISS_DEACTIVATE_MAX_DAYS . ' days')->format('Y-m-d');
    return [$min, $max];
}
/** PRO / ACADEMIC_PRO / OTHER — the monthly limit only applies to ACADEMIC_PRO. */
function iss_role_key($role)
{
    if (SessionUser::ifUserIsPro($role)) return 'PRO';
    if (SessionUser::ifUserIsAcademicPro($role)) return 'ACADEMIC_PRO';
    return 'OTHER';
}
/** shi_id => count of coordinator activations created this calendar month. */
function iss_month_counts(array $ids)
{
    if (!$ids) return [];
    $start = iss_now()->format('Y-m-01 00:00:00');
    $end = iss_now()->modify('first day of next month')->format('Y-m-d 00:00:00');
    list($in, $t) = iss_in($ids);
    $out = [];
    foreach (iss_rows(
        "SELECT `students_has_intakes_students_has_intakes_id` AS `shi_id`, COUNT(*) AS `n`
         FROM `students_has_intakes_temp_activations`
         WHERE `students_has_intakes_students_has_intakes_id` IN ($in)
           AND `created_by_role` = 'ACADEMIC_PRO' AND `created_at` >= ? AND `created_at` < ?
         GROUP BY `students_has_intakes_students_has_intakes_id`",
        $t . 'ss',
        array_merge($ids, [$start, $end])
    ) as $r) {
        $out[(int)$r['shi_id']] = (int)$r['n'];
    }
    return $out;
}
/** Cancel PENDING auto-deactivations for these students. Returns rows changed. */
function iss_cancel_pending(array $ids, $why)
{
    if (!$ids) return 0;
    list($in, $t) = iss_in($ids);
    return iss_exec(
        "UPDATE `students_has_intakes_temp_activations`
         SET `schedule_status` = 'CANCELLED', `processed_at` = ?, `process_note` = ?
         WHERE `students_has_intakes_students_has_intakes_id` IN ($in) AND `schedule_status` = 'PENDING'",
        'ss' . $t,
        array_merge([iss_now()->format('Y-m-d H:i:s'), mb_substr($why, 0, 255)], $ids)
    );
}
/**
 * For Activate: split eligible students into allowed vs blocked by the
 * academic coordinator monthly limit.
 */
function iss_activation_split(array $eligible, $roleKey)
{
    $ids = array_map('intval', array_column($eligible, 'shi_id'));
    $counts = $roleKey === 'ACADEMIC_PRO' ? iss_month_counts($ids) : [];
    $allowed = [];
    $limited = 0;
    foreach ($eligible as $r) {
        $id = (int)$r['shi_id'];
        if (($counts[$id] ?? 0) >= ISS_COORD_MONTHLY_LIMIT) {
            $limited++;
            continue;
        }
        $allowed[] = $r;
    }
    return ['allowed' => $allowed, 'limited' => $limited];
}

/* ------------------------------------------------- inactive reasons list */
/**
 * students_inactive_reasons: id = primary key column, label = first
 * text column. Columns are detected so this works with the existing table.
 */
function iss_reasons()
{
    $t = iss_rows("SHOW TABLES LIKE 'students_inactive_reasons'");
    if (!$t) return [];
    $cols = iss_rows("SHOW COLUMNS FROM `students_inactive_reasons`");
    $idCol = null;
    $labelCol = null;
    $statusCol = null;
    foreach ($cols as $c) {
        if ($c['Key'] === 'PRI' && !$idCol) $idCol = $c['Field'];
        elseif (!$labelCol && preg_match('/char|text/i', $c['Type'])) $labelCol = $c['Field'];
        if (preg_match('/^status(_status_id)?$/', $c['Field'])) $statusCol = $c['Field'];
    }
    if (!$idCol || !$labelCol) return [];
    $where = $statusCol ? " WHERE `$statusCol` = 1" : "";
    return array_map(function ($r) use ($idCol, $labelCol) {
        return ['id' => (int)$r[$idCol], 'name' => $r[$labelCol]];
    }, iss_rows("SELECT `$idCol`, `$labelCol` FROM `students_inactive_reasons`$where ORDER BY `$labelCol`"));
}

/* -------------------------------------------- which students are affected */
/** Returns rows [shi_id, status_id, name] in this intake for the requested mode. */
function iss_resolve_targets($intakeId, array $d, $lock = false)
{
    $checked = iss_ids($d['checkedStudents'] ?? []);
    $unchecked = iss_ids($d['uncheckedStudents'] ?? []);
    $page = iss_ids($d['pageStudents'] ?? []);

    $sql = "SELECT `shi`.`students_has_intakes_id` AS `shi_id`, `shi`.`status_status_id` AS `status_id`,
                   CONCAT(`ld`.`first_name`, ' ', `ld`.`last_name`) AS `name`
            FROM `students_has_intakes` AS `shi`
            INNER JOIN `students` AS `s` ON `shi`.`students_student_id` = `s`.`student_id`
            INNER JOIN `login_details` AS `ld` ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
            WHERE `shi`.`intakes_intake_id` = ?";
    $types = 'i';
    $params = [(int)$intakeId];

    if ($checked) {
        list($in, $t) = iss_in($checked);
        $sql .= " AND `shi`.`students_has_intakes_id` IN ($in)";
        $types .= $t;
        $params = array_merge($params, $checked);
    } elseif ($unchecked) {
        list($in, $t) = iss_in($unchecked);
        $sql .= " AND `shi`.`students_has_intakes_id` NOT IN ($in)";
        $types .= $t;
        $params = array_merge($params, $unchecked);
    } elseif ($page) {
        list($in, $t) = iss_in($page);
        $sql .= " AND `shi`.`students_has_intakes_id` IN ($in)";
        $types .= $t;
        $params = array_merge($params, $page);
    } else {
        throw new IssException('Please select at least one student.');
    }
    $sql .= " ORDER BY `shi`.`students_has_intakes_id`" . ($lock ? " FOR UPDATE" : "");
    return iss_rows($sql, $types, $params);
}

function iss_summarise($update, array $rows)
{
    $eligible = [];
    $already = 0;
    $locked = 0;
    foreach ($rows as $r) {
        $sid = (int)$r['status_id'];
        if (in_array($sid, ISS_ALLOWED_FROM[$update], true)) {
            $eligible[] = $r;
        } elseif ($sid === $update) {
            $already++;
        } else {
            $locked++; // e.g. discontinued students can't be re-activated
        }
    }
    return ['eligible' => $eligible, 'already' => $already, 'locked' => $locked];
}

/* ------------------------------------------------------------- request */
try {
    if (!SessionUser::hasValidSession()) throw new IssException('Authentication Failed!');
    $sessionUserRole = SessionUser::getCurrentUserRole();
    $sessionUserObject = SessionUser::getCurrentUserObject();
    if ($sessionUserRole == null) throw new IssException('Authentication Failed!');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        throw new IssException('Invalid request method');
    }
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        http_response_code(400);
        throw new IssException('Invalid Content-Type');
    }
    $decoded = json_decode(trim(file_get_contents('php://input')), true);
    if (!is_array($decoded)) {
        http_response_code(400);
        throw new IssException('Invalid JSON');
    }
    $action = (string)($decoded['action'] ?? 'update');

    if ($action === 'reasons') {
        $status->reasons = iss_reasons();
        $status->done = true;
        echo json_encode($status);
        exit;
    }

    $intakeId = isset($decoded['intakeId']) && is_numeric($decoded['intakeId']) ? (int)$decoded['intakeId'] : 0;
    if ($intakeId <= 0 || !iss_rows("SELECT `intake_id` FROM `intakes` WHERE `intake_id` = ?", 'i', [$intakeId])) {
        throw new IssException('Invalid intake id');
    }
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
    if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAcademicPro($sessionUserRole) || UserPermissions::hasPermission('intake_students', 4, $intakeId))) {
        throw new IssException('You do not have permission to update students status to this intake');
    }

    $update = isset($decoded['update']) && is_numeric($decoded['update']) ? (int)$decoded['update'] : 0;
    if (!isset(ISS_STATUS_NAMES[$update])) {
        throw new IssException('Please select the desired student status change option.');
    }

    if ($action === 'preview') {
        $rows = iss_resolve_targets($intakeId, $decoded);
        if (count($rows) > ISS_MAX_STUDENTS) throw new IssException('Too many students in one update (max ' . ISS_MAX_STUDENTS . ').');
        $sum = iss_summarise($update, $rows);
        $eligibleRows = $sum['eligible'];
        $status->limited = 0;
        if ($update === 1) {
            $split = iss_activation_split($eligibleRows, iss_role_key($sessionUserRole));
            $eligibleRows = $split['allowed'];
            $status->limited = $split['limited'];
            list($status->min_date, $status->max_date) = iss_deactivate_range();
            $status->monthly_limit = iss_role_key($sessionUserRole) === 'ACADEMIC_PRO' ? ISS_COORD_MONTHLY_LIMIT : null;
        }
        $status->total = count($rows);
        $status->eligible = count($eligibleRows);
        $status->already = $sum['already'];
        $status->locked = $sum['locked'];
        $status->sample = array_slice(array_column($eligibleRows, 'name'), 0, 5);
        $status->reason_required = $update !== 1;
        $status->done = true;
        echo json_encode($status);
        exit;
    }

    if ($action !== 'update') throw new IssException('Unknown action.');

    /* ---- validate reason + note ---- */
    $note = trim((string)($decoded['note'] ?? ''));
    $reasonId = isset($decoded['reasonId']) && is_numeric($decoded['reasonId']) && (int)$decoded['reasonId'] > 0 ? (int)$decoded['reasonId'] : null;
    $errors = [];
    if (mb_strlen($note) > 2000) $errors['note'] = 'Note must be 2000 characters or fewer.';

    $deactivateDate = null;
    if ($update === 1) {
        // Re-activation: no reason list, so the note is the reason.
        if ($note === '') $errors['note'] = 'Please enter the reason for activating.';
        $reasonId = null;
        list($minDate, $maxDate) = iss_deactivate_range();
        $raw = trim((string)($decoded['deactivateDate'] ?? ''));
        $dt = DateTime::createFromFormat('!Y-m-d', $raw, new DateTimeZone(ISS_TZ));
        if ($raw === '') {
            $errors['deactivateDate'] = 'Please select the deactivate date.';
        } elseif (!$dt || $dt->format('Y-m-d') !== $raw) {
            $errors['deactivateDate'] = 'Deactivate date is not valid.';
        } elseif ($raw < $minDate || $raw > $maxDate) {
            $errors['deactivateDate'] = 'Deactivate date must be between ' . $minDate . ' and ' . $maxDate . '.';
        } else {
            $deactivateDate = $raw;
        }
    } else {
        $reasons = iss_reasons();
        if ($reasons) {
            if (!$reasonId) {
                $errors['reason'] = 'Please select a reason.';
            } elseif (!in_array($reasonId, array_column($reasons, 'id'), true)) {
                $errors['reason'] = 'Selected reason is not valid.';
            }
        } else {
            // No reasons configured: the note is required instead.
            $reasonId = null;
            if ($note === '') $errors['note'] = 'Please enter the reason.';
        }
    }
    if ($errors) {
        $e = new IssException(reset($errors));
        $e->fields = $errors;
        throw $e;
    }

    /* ---- update + log, one transaction ---- */
    $userId = (int)$sessionUserObject['user_id'];
    $now = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    $conn = iss_conn();
    $conn->begin_transaction();
    try {
        // Lock the target rows so two people changing the same students don't interleave.
        $rows = iss_resolve_targets($intakeId, $decoded, true);
        if (count($rows) > ISS_MAX_STUDENTS) throw new IssException('Too many students in one update (max ' . ISS_MAX_STUDENTS . ').');
        $sum = iss_summarise($update, $rows);
        if (!$sum['eligible']) {
            throw new IssException('None of the selected students can be changed to ' . ISS_STATUS_NAMES[$update] . '.');
        }

        if ($update === 1) {
            /* ---- Activate now + auto-deactivate on the selected date ---- */
            $roleKey = iss_role_key($sessionUserRole);
            $split = iss_activation_split($sum['eligible'], $roleKey);
            $sum['limited'] = $split['limited'];
            if (!$split['allowed']) {
                throw new IssException($split['limited']
                    ? 'The selected students were already activated this month. Academic coordinators can activate each student once per month.'
                    : 'None of the selected students can be activated.');
            }
            $ids = array_map('intval', array_column($split['allowed'], 'shi_id'));
            list($in, $t) = iss_in($ids);
            iss_exec(
                "UPDATE `students_has_intakes` SET `status_status_id` = 1
                 WHERE `intakes_intake_id` = ? AND `students_has_intakes_id` IN ($in) AND `status_status_id` = 2",
                'i' . $t,
                array_merge([$intakeId], $ids)
            );
            iss_cancel_pending($ids, 'Replaced by a new activation');

            $schStmt = $conn->prepare(
                "INSERT INTO `students_has_intakes_temp_activations`
                    (`students_has_intakes_students_has_intakes_id`, `deactivate_date`, `note`, `schedule_status`,
                     `created_by`, `created_by_role`, `created_at`)
                 VALUES (?, ?, ?, 'PENDING', ?, ?, ?)"
            );
            $logStmt = $conn->prepare(
                "INSERT INTO `students_has_intakes_logs`
                    (`students_has_intakes_students_has_intakes_id`, `users_user_id`, `date_updated`, `log`, `note`,
                     `students_inactive_reasons_students_inactive_reasons_id`)
                 VALUES (?, ?, ?, ?, ?, NULL)"
            );
            if (!$schStmt || !$logStmt) throw new Exception('Prepare failed: ' . $conn->error);
            $bulk = count($ids) > 1;
            $log = mb_substr('Status changed from Inactive to Active until ' . $deactivateDate . ($bulk ? ' (bulk update)' : ''), 0, 100);
            foreach ($split['allowed'] as $r) {
                $shiId = (int)$r['shi_id'];
                $schStmt->bind_param('ississ', $shiId, $deactivateDate, $note, $userId, $roleKey, $now);
                if (!$schStmt->execute()) throw new Exception('Auto-deactivation insert failed: ' . $schStmt->error);
                $logStmt->bind_param('iisss', $shiId, $userId, $now, $log, $note);
                if (!$logStmt->execute()) throw new Exception('Log insert failed: ' . $logStmt->error);
            }
            $schStmt->close();
            $logStmt->close();
            $conn->commit();

            $n = count($ids);
            $msg = $n . ' student' . ($n === 1 ? '' : 's') . ' activated. They will be deactivated automatically on ' . $deactivateDate . '.';
            $parts = [];
            if ($sum['already']) $parts[] = $sum['already'] . ' already active';
            if ($sum['locked']) $parts[] = $sum['locked'] . ' discontinued (cannot be changed)';
            if ($sum['limited']) $parts[] = $sum['limited'] . ' already activated this month (once per month)';
            if ($parts) $msg .= ' Skipped: ' . implode(', ', $parts) . '.';
            $status->updated = $n;
            $status->deactivate_date = $deactivateDate;
            $status->skipped = $sum['already'] + $sum['locked'] + $sum['limited'];
            $status->done = $msg;
            echo json_encode($status);
            exit;
        }

        $ids = array_map('intval', array_column($sum['eligible'], 'shi_id'));
        list($in, $t) = iss_in($ids);
        list($fromIn, $ft) = iss_in(ISS_ALLOWED_FROM[$update]);
        iss_exec(
            "UPDATE `students_has_intakes` SET `status_status_id` = ?
             WHERE `intakes_intake_id` = ? AND `students_has_intakes_id` IN ($in) AND `status_status_id` IN ($fromIn)",
            'ii' . $t . $ft,
            array_merge([$update, $intakeId], $ids, ISS_ALLOWED_FROM[$update])
        );

        $bulk = count($ids) > 1;
        $logStmt = $conn->prepare(
            "INSERT INTO `students_has_intakes_logs`
                (`students_has_intakes_students_has_intakes_id`, `users_user_id`, `date_updated`, `log`, `note`,
                 `students_inactive_reasons_students_inactive_reasons_id`)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        if (!$logStmt) throw new Exception('Prepare failed: ' . $conn->error);
        $noteVal = $note !== '' ? $note : null;
        foreach ($sum['eligible'] as $r) {
            $shiId = (int)$r['shi_id'];
            $log = mb_substr('Status changed from ' . (ISS_STATUS_NAMES[(int)$r['status_id']] ?? 'Unknown')
                . ' to ' . ISS_STATUS_NAMES[$update] . ($bulk ? ' (bulk update)' : ''), 0, 100);
            $logStmt->bind_param('iisssi', $shiId, $userId, $now, $log, $noteVal, $reasonId);
            if (!$logStmt->execute()) throw new Exception('Log insert failed: ' . $logStmt->error);
        }
        $logStmt->close();
        // Deactivated / discontinued by hand → the pending auto-deactivation is no longer needed.
        iss_cancel_pending($ids, 'Cancelled: student ' . ($update === 2 ? 'deactivated' : 'discontinued'));
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    $verb = [1 => 'activated', 2 => 'deactivated', 3 => 'discontinued'][$update];
    $msg = count($ids) . ' student' . (count($ids) === 1 ? '' : 's') . ' ' . $verb . '.';
    $skipped = $sum['already'] + $sum['locked'];
    $parts = [];
    if ($sum['already']) $parts[] = $sum['already'] . ' already ' . strtolower(ISS_STATUS_NAMES[$update]);
    if ($sum['locked']) $parts[] = $sum['locked'] . ' discontinued (cannot be changed)';
    if ($parts) $msg .= ' Skipped: ' . implode(', ', $parts) . '.';
    $status->updated = count($ids);
    $status->skipped = $skipped;
    $status->done = $msg;
} catch (IssException $e) {
    $status->error = $e->getMessage();
    if ($e->fields) $status->fields = $e->fields;
} catch (Throwable $e) {
    error_log('[updateIntakeStudentsStatus] ' . $e->getMessage());
    $status->error = 'Something went wrong. No student status was changed.';
}

echo json_encode($status);
