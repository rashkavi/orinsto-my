<?php

class StudentIdGenerator
{
    public static function generateByIntakeId($intakeId)
    {
        $intakeId = (int)$intakeId;

        $intakeData = self::getIntakeData($intakeId);

        if (!$intakeData) {
            throw new Exception("Invalid intake ID.");
        }

        $intakePrefix = trim($intakeData['intake_prefix']);

        if (empty($intakePrefix)) {
            throw new Exception("Intake prefix is empty.");
        }

        $sequenceStart = (int)$intakeData['student_id_sequence_start'];
        $nextNumber    = self::getNextSequenceNumber($intakeId, $sequenceStart);

        return $intakePrefix . $nextNumber;
    }

    /**
     * Get intake details
     */
    private static function getIntakeData($intakeId)
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

        if ($rs && $rs->num_rows > 0) {
            return $rs->fetch_assoc();
        }

        return null;
    }

    /**
     * Get next sequence number for this intake, offset by sequence_start.
     * Example: sequence_start=100, 0 students enrolled => next is 100
     *          sequence_start=100, 2 students enrolled => next is 102
     */
    private static function getNextSequenceNumber($intakeId, int $sequenceStart)
    {
        $query = "
            SELECT COUNT(*) AS `total`
            FROM `students_has_intakes`
            WHERE `intakes_intake_id` = '" . Database::escape($intakeId) . "'
        ";

        $rs   = Database::search($query);
        $data = $rs->fetch_assoc();

        $next = $sequenceStart + (int)$data['total'];

        // Pad to at least 3 digits; if sequence_start is e.g. 100 it's already 3 digits naturally
        return str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
