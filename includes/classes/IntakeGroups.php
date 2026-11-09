<?php

/**
 * IntakeGroups
 * ------------------------------------------------------------------
 * Shared logic for intake student groups and lecture audiences.
 *
 * Used by:
 *   api/intakeGroups.php          (group management on intake-details)
 *   api/lectureGroups.php         (audience options / edit for a lecture)
 *   api/scheduleLecture.php       (save audience when scheduling)
 *   api/getLectureScheduleData.php(attach audience to calendar data)
 *
 * Tables (see sql/intake_groups.sql):
 *   intake_groups
 *   intake_groups_has_students_has_intakes   (current membership)
 *   intake_groups_logs                       (append-only history)
 *   time_table_has_intake_groups             (no rows = all-intake)
 *
 * All queries here use mysqli prepared statements on the existing
 * Database::$connection. Writes that touch more than one row are done
 * by the caller inside IntakeGroups::transaction().
 *
 * Requires the mysqlnd driver (mysqli_stmt::get_result), which is the
 * default on PHP 7.4+/8.x builds.
 */

require_once __DIR__ . '/../connection.php';

class IntakeGroupException extends Exception {}

class IntakeGroups
{
    const MAX_NAME_LENGTH = 100;
    const STATUS_DISCONTINUED = 3; // students_has_intakes.status_status_id

    /* ============================================================
     * Connection / query helpers
     * ============================================================ */

    public static function conn()
    {
        if (!Database::$connection) {
            // Database opens its connection lazily on first use.
            Database::search("SELECT 1");
        }
        return Database::$connection;
    }

    /** SELECT → array of assoc rows */
    public static function rows($sql, $types = '', array $params = [])
    {
        $stmt = self::prepareAndRun($sql, $types, $params);
        $res = $stmt->get_result();
        $out = [];
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $out[] = $r;
            }
        }
        $stmt->close();
        return $out;
    }

    /** SELECT → first row or null */
    public static function row($sql, $types = '', array $params = [])
    {
        $r = self::rows($sql, $types, $params);
        return $r ? $r[0] : null;
    }

    /** INSERT/UPDATE/DELETE → ['affected' => n, 'insert_id' => id] */
    public static function exec($sql, $types = '', array $params = [])
    {
        $stmt = self::prepareAndRun($sql, $types, $params);
        $out = ['affected' => $stmt->affected_rows, 'insert_id' => $stmt->insert_id];
        $stmt->close();
        return $out;
    }

    private static function prepareAndRun($sql, $types, array $params)
    {
        $conn = self::conn();
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $errno = $stmt->errno;
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('Query failed: ' . $err, $errno);
        }
        return $stmt;
    }

    /**
     * Run $fn inside a transaction. Commits on success, rolls back and
     * rethrows on any exception.
     */
    public static function transaction(callable $fn)
    {
        $conn = self::conn();
        $conn->begin_transaction();
        try {
            $result = $fn();
            $conn->commit();
            return $result;
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    public static function now()
    {
        return (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');
    }

    /** "IN (?,?,?)" placeholder string + matching type string */
    public static function inList(array $ids)
    {
        return [implode(',', array_fill(0, count($ids), '?')), str_repeat('i', count($ids))];
    }

    /** Accepts an array, JSON string or comma string; returns unique positive ints. */
    public static function toIdList($raw)
    {
        if ($raw === null || $raw === '') return [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        if (!is_array($raw)) return [];
        $ids = [];
        foreach ($raw as $v) {
            if (is_numeric($v) && (int)$v > 0 && (string)(int)$v === trim((string)$v)) {
                $ids[(int)$v] = true;
            }
        }
        return array_keys($ids);
    }

    /* ============================================================
     * Permissions
     * ============================================================ */

    public static function canView($role, $intakeId)
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAcademicPro($role)
            || UserPermissions::validPermisisonType('intake_students', $intakeId);
    }

    /** Same rule the page already uses for "Add New" students (intake_students, 2). */
    public static function canManage($role, $intakeId)
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAcademicPro($role)
            || UserPermissions::hasPermission('intake_students', 2, $intakeId);
    }

    /** Who may set a lecture's audience: schedulers + whoever sees the lecture "Edit" action. */
    public static function canEditLectureAudience($role)
    {
        return SessionUser::ifUserIsPro($role)
            || SessionUser::ifUserIsAcademicPro($role)
            || UserPermissions::hasPermission('super_lecture_schedule', 2)
            || UserPermissions::validPermisisonType('lecture_schedule', 0);
    }

    /* ============================================================
     * Intakes / groups
     * ============================================================ */

    public static function getIntake($intakeId)
    {
        return self::row(
            "SELECT `intake_id`, `intake` FROM `intakes` WHERE `intake_id` = ?",
            'i',
            [(int)$intakeId]
        );
    }

    /** Groups of one intake with live counts. */
    public static function listGroups($intakeId)
    {
        return self::rows(
            "SELECT `g`.`intake_group_id`, `g`.`intake_group`, `g`.`max_students`, `g`.`date_added`,
                    COUNT(`m`.`intake_groups_has_students_has_intakes_id`) AS `student_count`
             FROM `intake_groups` AS `g`
             LEFT JOIN `intake_groups_has_students_has_intakes` AS `m`
                    ON `m`.`intake_groups_intake_group_id` = `g`.`intake_group_id`
             WHERE `g`.`intakes_intake_id` = ?
             GROUP BY `g`.`intake_group_id`
             ORDER BY `g`.`intake_group` ASC",
            'i',
            [(int)$intakeId]
        );
    }

    /**
     * Load a group and verify it belongs to $intakeId.
     * $lock = true takes a row lock (FOR UPDATE) — use inside a transaction
     * so concurrent add/move requests on the same group serialise.
     */
    public static function getGroup($groupId, $intakeId, $lock = false)
    {
        $g = self::row(
            "SELECT `intake_group_id`, `intake_group`, `max_students`, `intakes_intake_id`
             FROM `intake_groups`
             WHERE `intake_group_id` = ? AND `intakes_intake_id` = ?" . ($lock ? " FOR UPDATE" : ""),
            'ii',
            [(int)$groupId, (int)$intakeId]
        );
        if (!$g) {
            throw new IntakeGroupException('The selected group does not belong to this intake.');
        }
        return $g;
    }

    public static function memberCount($groupId)
    {
        $r = self::row(
            "SELECT COUNT(*) AS `c` FROM `intake_groups_has_students_has_intakes`
             WHERE `intake_groups_intake_group_id` = ?",
            'i',
            [(int)$groupId]
        );
        return (int)$r['c'];
    }

    public static function validateGroupName($name)
    {
        $name = trim(preg_replace('/\s+/', ' ', (string)$name));
        if ($name === '') {
            throw new IntakeGroupException('Please enter a group name.');
        }
        if (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw new IntakeGroupException('Group name must be ' . self::MAX_NAME_LENGTH . ' characters or fewer.');
        }
        return $name;
    }

    public static function validateCapacity($max)
    {
        if (!is_numeric($max) || (string)(int)$max !== trim((string)$max) || (int)$max < 1) {
            throw new IntakeGroupException('Maximum students must be a whole number greater than 0.');
        }
        if ((int)$max > 10000) {
            throw new IntakeGroupException('Maximum students is too large.');
        }
        return (int)$max;
    }

    public static function nameTaken($intakeId, $name, $exceptGroupId = 0)
    {
        return (bool)self::row(
            "SELECT `intake_group_id` FROM `intake_groups`
             WHERE `intakes_intake_id` = ? AND `intake_group` = ? AND `intake_group_id` <> ?",
            'isi',
            [(int)$intakeId, $name, (int)$exceptGroupId]
        );
    }

    /* ============================================================
     * Students / registrations
     * ============================================================ */

    /** Shared SELECT for a registration with its display fields. */
    private static function studentSelect()
    {
        return "SELECT `shi`.`students_has_intakes_id` AS `shi_id`,
                       `shi`.`student_id`              AS `reg_no`,
                       `shi`.`status_status_id`        AS `status_id`,
                       `st`.`status`                   AS `status`,
                       `ld`.`first_name`, `ld`.`last_name`, `ld`.`email`,
                       `s`.`profile_img`, `t`.`title`
                FROM `students_has_intakes` AS `shi`
                INNER JOIN `students`      AS `s`  ON `shi`.`students_student_id` = `s`.`student_id`
                INNER JOIN `login_details` AS `ld` ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                INNER JOIN `title`         AS `t`  ON `s`.`title_title_id` = `t`.`title_id`
                INNER JOIN `status`        AS `st` ON `shi`.`status_status_id` = `st`.`status_id` ";
    }

    public static function label(array $r)
    {
        return trim(($r['title'] ? $r['title'] . '. ' : '') . $r['first_name'] . ' ' . $r['last_name'])
            . ' (' . $r['reg_no'] . ')';
    }

    /** Registrations in the intake that are in no group (discontinued excluded). */
    public static function eligibleStudents($intakeId, $search = '')
    {
        $sql = self::studentSelect() . "
            LEFT JOIN `intake_groups_has_students_has_intakes` AS `m`
                   ON `m`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
            WHERE `shi`.`intakes_intake_id` = ?
              AND `shi`.`status_status_id` <> ?
              AND `m`.`intake_groups_has_students_has_intakes_id` IS NULL";
        $types = 'ii';
        $params = [(int)$intakeId, self::STATUS_DISCONTINUED];

        $search = trim((string)$search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= " AND (`shi`.`student_id` LIKE ? OR `ld`.`email` LIKE ?
                       OR CONCAT(`ld`.`first_name`, ' ', `ld`.`last_name`) LIKE ?)";
            $types .= 'sss';
            array_push($params, $like, $like, $like);
        }
        $sql .= " ORDER BY `ld`.`first_name`, `ld`.`last_name` LIMIT 1000";
        return self::rows($sql, $types, $params);
    }

    public static function groupMembers($groupId)
    {
        return self::rows(
            self::studentSelect() . "
            INNER JOIN `intake_groups_has_students_has_intakes` AS `m`
                    ON `m`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
            INNER JOIN `users` AS `u` ON `m`.`users_user_id` = `u`.`user_id`
            WHERE `m`.`intake_groups_intake_group_id` = ?
            ORDER BY `ld`.`first_name`, `ld`.`last_name`",
            'i',
            [(int)$groupId]
        );
    }

    /**
     * Lock and validate registrations for an add operation.
     * Every id must belong to $intakeId, not be discontinued, and not
     * already be in any group. Returns rows keyed by shi_id.
     */
    public static function lockEligibleRegistrations($intakeId, array $shiIds)
    {
        list($in, $t) = self::inList($shiIds);
        $rows = self::rows(
            self::studentSelect() . "
            WHERE `shi`.`intakes_intake_id` = ? AND `shi`.`students_has_intakes_id` IN ($in)
            FOR UPDATE",
            'i' . $t,
            array_merge([(int)$intakeId], $shiIds)
        );
        if (count($rows) !== count($shiIds)) {
            throw new IntakeGroupException('One or more selected students are not registered in this intake.');
        }
        $byId = [];
        foreach ($rows as $r) {
            if ((int)$r['status_id'] === self::STATUS_DISCONTINUED) {
                throw new IntakeGroupException(self::label($r) . ' is discontinued and cannot be added to a group.');
            }
            $byId[(int)$r['shi_id']] = $r;
        }

        $already = self::rows(
            "SELECT `students_has_intakes_students_has_intakes_id` AS `shi_id`
             FROM `intake_groups_has_students_has_intakes`
             WHERE `students_has_intakes_students_has_intakes_id` IN ($in) FOR UPDATE",
            $t,
            $shiIds
        );
        if ($already) {
            $names = array_map(function ($a) use ($byId) {
                return self::label($byId[(int)$a['shi_id']]);
            }, $already);
            throw new IntakeGroupException(
                'Already assigned to a group (use Move instead): ' . implode(', ', array_slice($names, 0, 5))
                    . (count($names) > 5 ? ' and ' . (count($names) - 5) . ' more' : '') . '.'
            );
        }
        return $byId;
    }

    /** One registration's current membership, verified against intake + source group. */
    public static function lockMembership($intakeId, $shiId, $groupId)
    {
        $r = self::row(
            "SELECT `m`.`intake_groups_has_students_has_intakes_id` AS `membership_id`,
                        `shi`.`students_has_intakes_id` AS `shi_id`, `shi`.`student_id` AS `reg_no`,
                        `ld`.`first_name`, `ld`.`last_name`, `t`.`title`
                 FROM `intake_groups_has_students_has_intakes` AS `m`
                 INNER JOIN `students_has_intakes` AS `shi` ON `m`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                 INNER JOIN `students`      AS `s`  ON `shi`.`students_student_id` = `s`.`student_id`
                 INNER JOIN `login_details` AS `ld` ON `s`.`login_details_login_details_id` = `ld`.`login_details_id`
                 INNER JOIN `title`         AS `t`  ON `s`.`title_title_id` = `t`.`title_id`
                 WHERE `m`.`students_has_intakes_students_has_intakes_id` = ?
                   AND `m`.`intake_groups_intake_group_id` = ?
                   AND `shi`.`intakes_intake_id` = ?
                 FOR UPDATE",
            'iii',
            [(int)$shiId, (int)$groupId, (int)$intakeId]
        );
        if (!$r) {
            throw new IntakeGroupException('This student is no longer in the selected group. Refresh and try again.');
        }
        return $r;
    }

    /* ============================================================
     * History (append-only)
     * ============================================================ */

    public static function log($groupId, $action, $userId, array $opt = [])
    {
        self::exec(
            "INSERT INTO `intake_groups_logs`
                (`intake_groups_intake_group_id`, `action`, `students_has_intakes_students_has_intakes_id`,
                 `student_label`, `from_intake_group_id`, `to_intake_group_id`, `details`, `users_user_id`, `date_added`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            'isisiisis',
            [
                (int)$groupId,
                $action,
                $opt['shi_id'] ?? null,
                isset($opt['student_label']) ? mb_substr($opt['student_label'], 0, 255) : null,
                $opt['from'] ?? null,
                $opt['to'] ?? null,
                isset($opt['details']) ? mb_substr($opt['details'], 0, 500) : null,
                (int)$userId,
                self::now(),
            ]
        );
    }

    public static function history($groupId)
    {
        return self::rows(
            "SELECT `l`.`intake_groups_logs_id` AS `id`, `l`.`action`, `l`.`student_label`, `l`.`details`,
                    `l`.`date_added`,
                    `fg`.`intake_group` AS `from_group`, `tg`.`intake_group` AS `to_group`,
                    CONCAT(IFNULL(CONCAT(`t`.`title`, '. '), ''), `u`.`user_fname`, ' ', `u`.`user_lname`) AS `performed_by`
             FROM `intake_groups_logs` AS `l`
             INNER JOIN `users` AS `u` ON `l`.`users_user_id` = `u`.`user_id`
             LEFT JOIN `title` AS `t` ON `u`.`title_title_id` = `t`.`title_id`
             LEFT JOIN `intake_groups` AS `fg` ON `l`.`from_intake_group_id` = `fg`.`intake_group_id`
             LEFT JOIN `intake_groups` AS `tg` ON `l`.`to_intake_group_id` = `tg`.`intake_group_id`
             WHERE `l`.`intake_groups_intake_group_id` = ?
             ORDER BY `l`.`date_added` DESC, `l`.`intake_groups_logs_id` DESC
             LIMIT 500",
            'i',
            [(int)$groupId]
        );
    }

    /* ============================================================
     * Lecture audience
     * ============================================================ */

    /** Intake + subject that a time_table row belongs to (via subject → level → intake). */
    public static function getLectureContext($timeTableId)
    {
        return self::row(
            "SELECT `tt`.`time_table_id`, `tt`.`subjects_subject_id` AS `subject_id`,
                    `i`.`intake_id`, `i`.`intake`, `subj`.`subject`
             FROM `time_table` AS `tt`
             INNER JOIN `subjects` AS `subj` ON `tt`.`subjects_subject_id` = `subj`.`subject_id`
             INNER JOIN `levels`   AS `lv`   ON `subj`.`levels_level_id` = `lv`.`level_id`
             INNER JOIN `intakes`  AS `i`    ON `lv`.`intakes_intake_id` = `i`.`intake_id`
             WHERE `tt`.`time_table_id` = ?",
            'i',
            [(int)$timeTableId]
        );
    }

    /** Intake id for a subject (used by scheduleLecture to cross-check the posted intake). */
    public static function getSubjectIntakeId($subjectId)
    {
        $r = self::row(
            "SELECT `lv`.`intakes_intake_id` AS `intake_id`
             FROM `subjects` AS `subj`
             INNER JOIN `levels` AS `lv` ON `subj`.`levels_level_id` = `lv`.`level_id`
             WHERE `subj`.`subject_id` = ?",
            'i',
            [(int)$subjectId]
        );
        return $r ? (int)$r['intake_id'] : 0;
    }

    /** Every id must be a group of $intakeId. Returns the ids (possibly empty = all-intake). */
    public static function validateGroupsForIntake($intakeId, array $groupIds)
    {
        if (!$groupIds) return [];
        list($in, $t) = self::inList($groupIds);
        $found = self::rows(
            "SELECT `intake_group_id` FROM `intake_groups`
             WHERE `intakes_intake_id` = ? AND `intake_group_id` IN ($in)",
            'i' . $t,
            array_merge([(int)$intakeId], $groupIds)
        );
        if (count($found) !== count($groupIds)) {
            throw new IntakeGroupException('One or more selected groups do not belong to this lecture\'s intake.');
        }
        return $groupIds;
    }

    /**
     * Replace a lecture's audience. Empty $groupIds = all-intake (no rows).
     * Call inside a transaction; validate the ids first.
     */
    public static function saveLectureGroups($timeTableId, array $groupIds, $userId)
    {
        self::exec(
            "DELETE FROM `time_table_has_intake_groups` WHERE `time_table_time_table_id` = ?",
            'i',
            [(int)$timeTableId]
        );
        $now = self::now();
        foreach ($groupIds as $gid) {
            self::exec(
                "INSERT INTO `time_table_has_intake_groups`
                    (`time_table_time_table_id`, `intake_groups_intake_group_id`, `users_user_id`, `date_added`)
                 VALUES (?, ?, ?, ?)",
                'iiis',
                [(int)$timeTableId, (int)$gid, (int)$userId, $now]
            );
        }
    }

    /** Copy audience from one lecture to another (e.g. a postponed occurrence). */
    public static function copyLectureGroups($fromTimeTableId, $toTimeTableId, $userId)
    {
        self::exec(
            "INSERT IGNORE INTO `time_table_has_intake_groups`
                (`time_table_time_table_id`, `intake_groups_intake_group_id`, `users_user_id`, `date_added`)
             SELECT ?, `intake_groups_intake_group_id`, ?, ?
             FROM `time_table_has_intake_groups` WHERE `time_table_time_table_id` = ?",
            'iisi',
            [(int)$toTimeTableId, (int)$userId, self::now(), (int)$fromTimeTableId]
        );
    }

    /**
     * SQL condition for STUDENT-SIDE lecture visibility. Append to the
     * student portal's existing lecture query with AND.
     *   $ttAlias  — alias of `time_table` in that query (e.g. 'tt')
     *   $shiAlias — alias of the student's `students_has_intakes` row
     *               (the specific intake registration being viewed)
     * Visible = no saved groups (all-intake) OR the registration is in
     * one of the lecture's groups. Aliases are code constants, not input.
     */
    public static function studentVisibilityCondition($ttAlias = 'tt', $shiAlias = 'shi')
    {
        $tt = preg_replace('/[^A-Za-z0-9_]/', '', $ttAlias);
        $shi = preg_replace('/[^A-Za-z0-9_]/', '', $shiAlias);
        return "(
            NOT EXISTS (SELECT 1 FROM `time_table_has_intake_groups` AS `ttg_v`
                        WHERE `ttg_v`.`time_table_time_table_id` = `$tt`.`time_table_id`)
            OR EXISTS (SELECT 1 FROM `time_table_has_intake_groups` AS `ttg_v`
                       INNER JOIN `intake_groups_has_students_has_intakes` AS `igm_v`
                               ON `igm_v`.`intake_groups_intake_group_id` = `ttg_v`.`intake_groups_intake_group_id`
                       WHERE `ttg_v`.`time_table_time_table_id` = `$tt`.`time_table_id`
                         AND `igm_v`.`students_has_intakes_students_has_intakes_id` = `$shi`.`students_has_intakes_id`)
        )";
    }

    /**
     * Audience filter for ONE lecture, for admin-side student lists
     * (e.g. lecture attendance). Returns:
     *   'groups' => [ ['id'=>..,'name'=>..], ... ]   ([] = all-intake)
     *   'sql'    => '' for an all-intake lecture, otherwise
     *               " AND <shiIdColumn> IN (members of those groups)"
     * $shiIdColumn is a code constant such as
     * '`students_has_intakes`.`students_has_intakes_id`'; group ids come
     * from the database and are cast to int.
     * If the group tables don't exist yet, it fails open (no filter).
     */
    public static function lectureAudienceFilter($timeTableId, $shiIdColumn)
    {
        try {
            $groups = self::getAudienceMap([(int)$timeTableId])[(int)$timeTableId] ?? [];
        } catch (Throwable $e) {
            error_log('[IntakeGroups::lectureAudienceFilter] ' . $e->getMessage());
            $groups = [];
        }
        if (!$groups) {
            return ['groups' => [], 'sql' => ''];
        }
        $ids = implode(',', array_map(function ($g) {
            return (int)$g['id'];
        }, $groups));
        return [
            'groups' => $groups,
            'sql'    => " AND $shiIdColumn IN (
                            SELECT `igm_a`.`students_has_intakes_students_has_intakes_id`
                            FROM `intake_groups_has_students_has_intakes` AS `igm_a`
                            WHERE `igm_a`.`intake_groups_intake_group_id` IN ($ids)
                        )",
        ];
    }

    /**
     * Saved audience for many lectures at once.
     * Returns [time_table_id => [ ['id' => .., 'name' => ..], ... ]].
     * Lectures missing from the result are all-intake.
     */
    public static function getAudienceMap(array $timeTableIds)
    {
        $timeTableIds = array_values(array_unique(array_map('intval', $timeTableIds)));
        $map = [];
        if (!$timeTableIds) return $map;

        foreach (array_chunk($timeTableIds, 500) as $chunk) {
            list($in, $t) = self::inList($chunk);
            $rows = self::rows(
                "SELECT `x`.`time_table_time_table_id` AS `tt_id`, `g`.`intake_group_id` AS `id`, `g`.`intake_group` AS `name`
                 FROM `time_table_has_intake_groups` AS `x`
                 INNER JOIN `intake_groups` AS `g` ON `x`.`intake_groups_intake_group_id` = `g`.`intake_group_id`
                 WHERE `x`.`time_table_time_table_id` IN ($in)
                 ORDER BY `g`.`intake_group`",
                $t,
                $chunk
            );
            foreach ($rows as $r) {
                $map[(int)$r['tt_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
            }
        }
        return $map;
    }
}