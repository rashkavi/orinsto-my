<?php

/**
 * Handles regeneration of student IDs when intake_prefix or
 * student_id_sequence_start changes.
 *
 * Usage (call this right after saving an intake update):
 *
 *   StudentIdUpdater::onIntakeLetterChange(
 *       $intakeId,
 *       $oldPrefix, $newPrefix,
 *       $oldSequenceStart, $newSequenceStart
 *   );
 */
class StudentIdUpdater
{
    /**
     * Call this right after saving an intake update.
     * Checks if the prefix OR sequence start actually changed,
     * then rebuilds all student IDs.
     *
     * @param  int    $intakeId
     * @param  string $oldPrefix         The prefix BEFORE the update
     * @param  string $newPrefix         The prefix AFTER the update
     * @param  int    $oldSequenceStart  The sequence start BEFORE the update
     * @param  int    $newSequenceStart  The sequence start AFTER the update
     * @return array  [ 'updated' => int, 'errors' => array ]
     */
    public static function onIntakeLetterChange(
        int    $intakeId,
        string $oldPrefix,
        string $newPrefix,
        int    $oldSequenceStart = 1,
        int    $newSequenceStart = 1
    ): array {
        $oldPrefix = trim($oldPrefix);
        $newPrefix = trim($newPrefix);

        // Nothing changed — nothing to do
        if ($oldPrefix === $newPrefix && $oldSequenceStart === $newSequenceStart) {
            return ['updated' => 0, 'errors' => []];
        }

        return self::rebuildAllStudentIds($intakeId);
    }

    /**
     * Regenerates and saves student IDs for every student in the given intake.
     * IDs are rebuilt from scratch using the current intake_prefix and
     * student_id_sequence_start, preserving each student's enrolment order.
     *
     * @param  int   $intakeId
     * @return array [ 'updated' => int, 'errors' => array ]
     */
    public static function rebuildAllStudentIds(int $intakeId): array
    {
        // 1. Fetch current intake data (always reads fresh from DB)
        $intakeData = self::getIntakeData($intakeId);
        if (!$intakeData) {
            throw new Exception("Invalid intake ID: $intakeId");
        }

        $intakePrefix  = trim($intakeData['intake_prefix']);
        $sequenceStart = (int)$intakeData['student_id_sequence_start'];

        if (empty($intakePrefix)) {
            throw new Exception("Intake prefix is empty for intake ID: $intakeId");
        }

        // 2. Fetch all students in this intake, ordered by enrolment (preserves sequence)
        $students = self::getStudentsInIntake($intakeId);
        if (!$students || $students->num_rows === 0) {
            return ['updated' => 0, 'errors' => []];
        }

        $updatedCount = 0;
        $errors       = [];
        $offset       = 0; // 0-based offset from sequence start

        $sessionUserObject = SessionUser::getCurrentUserObject();
        date_default_timezone_set("Asia/Colombo");

        // 3. Rebuild each student ID in enrolment order
        while ($row = $students->fetch_assoc()) {
            $sequence     = $sequenceStart + $offset;
            $newStudentId = $intakePrefix . str_pad($sequence, 3, '0', STR_PAD_LEFT);

            $sIntakeId        = (int)$row['students_has_intakes_id'];
            $studentId        = (int)$row['student_id'];
            $currentStudentId = $row['current_student_id'];

            // Skip if already correct (e.g. partial re-run)
            if ($currentStudentId === $newStudentId) {
                $offset++;
                continue;
            }

            // Check for collision with another student outside this intake
            if (self::studentIdExistsElsewhere($newStudentId, $sIntakeId)) {
                $errors[] = [
                    'student_id'     => $studentId,
                    'new_student_id' => $newStudentId,
                    'reason'         => "ID '$newStudentId' already used by another student.",
                ];
                $offset++;
                continue;
            }

            // Update student_id in students_has_intakes table
            Database::iud("
                UPDATE `students_has_intakes`
                SET `student_id` = '" . Database::escape($newStudentId) . "'
                WHERE `students_has_intakes_id` = '" . Database::escape($sIntakeId) . "'
            ");

            Database::iud("
                INSERT INTO `students_has_intakes_logs`
                    (`students_has_intakes_students_has_intakes_id`, `users_user_id`, `date_updated`, `log`)
                VALUES
                    ('" . $sIntakeId . "', '" . $sessionUserObject['user_id'] . "', NOW(),
                     'Student ID updated from " . $currentStudentId . " to " . $newStudentId . "')
            ");

            $updatedCount++;
            $offset++;
        }

        return ['updated' => $updatedCount, 'errors' => $errors];
    }

    // ─────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * Fetch intake details for a given intake.
     */
    private static function getIntakeData(int $intakeId): ?array
    {
        $query = "
            SELECT
                `intake_id`,
                `intake_prefix`,
                `student_id_sequence_start`
            FROM `intakes`
            WHERE `intake_id` = '" . Database::escape($intakeId) . "'
            LIMIT 1
        ";

        $rs = Database::search($query);
        return ($rs && $rs->num_rows > 0) ? $rs->fetch_assoc() : null;
    }

    /**
     * Fetch all students enrolled in a given intake, in enrolment order.
     * Ordered by students_has_intakes_id to preserve original sequence.
     */
    private static function getStudentsInIntake(int $intakeId)
    {
        $query = "
            SELECT
                `shi`.`students_has_intakes_id`,
                `shi`.`students_student_id`  AS `student_id`,
                `shi`.`student_id`           AS `current_student_id`
            FROM `students_has_intakes` shi
            WHERE shi.`intakes_intake_id` = '" . Database::escape($intakeId) . "'
            ORDER BY shi.`students_has_intakes_id` ASC
        ";

        return Database::search($query);
    }

    /**
     * Returns true if the given student_id string is already used
     * by a row in students_has_intakes other than the current one.
     */
    private static function studentIdExistsElsewhere(string $newStudentId, int $excludeSIntakeId): bool
    {
        $query = "
            SELECT 1
            FROM `students_has_intakes`
            WHERE `student_id` = '" . Database::escape($newStudentId) . "'
            AND   `students_has_intakes_id` != '" . Database::escape($excludeSIntakeId) . "'
            LIMIT 1
        ";

        $rs = Database::search($query);
        return ($rs && $rs->num_rows > 0);
    }
}