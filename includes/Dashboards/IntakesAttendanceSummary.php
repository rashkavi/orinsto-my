<?php
// Shared query logic for the Batches Attendance Summary page and its XLSX
// export. Reads from the `attendance` table (statuses: present, absent,
// recording watched) for the lectures scheduled on a given date.

function getIntakesAttendanceSummaryData(string $date): array
{
    $dateEsc = Database::escape($date);

    $intakesQuery = "
        SELECT
            intakes.intake_id,
            intakes.intake,
            intakes.intake_cover,
            programmes.programme,
            faculties.faculty,
           
            (SELECT COUNT(*) FROM students_has_intakes shi WHERE shi.intakes_intake_id = intakes.intake_id) AS total_students
        FROM intakes
        INNER JOIN programmes ON intakes.programmes_programme_id = programmes.programme_id
        INNER JOIN faculties ON programmes.faculties_faculty_id = faculties.faculty_id
        ORDER BY  faculties.faculty ASC, programmes.programme ASC, intakes.intake ASC
    ";
    $allIntakes = Database::search($intakesQuery)->fetch_all(MYSQLI_ASSOC);

    // One row per student per intake for the date.
    // A student can have several lectures in a day, so the best status wins:
    // PRESENT (2) > RECORDING_WATCHED (1) > ABSENT (0).
    // ASSUMED: time_table.time_table_date, time_table.intakes_intake_id,
    //          attendance_status.attendance_status (name column)
    // Only Present (2) and Watched (4) rows matter. Everyone else in the intake
    // (Absent, Half Verify, or no row at all) is computed as absent below.
    // Best status per student per day: Present beats Watched.
    // ASSUMED: time_table.time_table_date, time_table.intakes_intake_id
    $recordsQuery = "
        SELECT
            l.intakes_intake_id AS intake_id,
            a.students_student_id AS student_id,
            MAX(CASE a.attendance_status_attendance_status_id
                    WHEN 2 THEN 2   -- Present
                    WHEN 4 THEN 1   -- Watched
                END) AS best_status
        FROM attendance a
        INNER JOIN time_table tt
            ON a.time_table_time_table_id = tt.time_table_id
        INNER JOIN subjects s
            ON tt.subjects_subject_id = s.subject_id
        INNER JOIN levels l
            ON s.levels_level_id = l.level_id
        INNER JOIN intakes i
            ON l.intakes_intake_id = i.intake_id
        WHERE tt.date = '" . $dateEsc . "'
          AND a.attendance_status_attendance_status_id IN (2, 4)
          AND tt.time_table_status_time_table_status_id = 5
        GROUP BY l.intakes_intake_id, a.students_student_id
    ";
  
    $rawRecords = Database::search($recordsQuery)->fetch_all(MYSQLI_ASSOC);

    $intakeStatusCounts = [];
    foreach ($rawRecords as $rec) {
        $intakeId = $rec['intake_id'];
        if (!isset($intakeStatusCounts[$intakeId])) {
            $intakeStatusCounts[$intakeId] = ['PRESENT' => 0, 'RECORDING_WATCHED' => 0];
        }
        if ((int)$rec['best_status'] === 2) {
            $intakeStatusCounts[$intakeId]['PRESENT']++;
        } else {
            $intakeStatusCounts[$intakeId]['RECORDING_WATCHED']++;
        }
    }

    $cards = [];
    foreach ($allIntakes as $row) {
        $intakeId = $row['intake_id'];

        if (!isset($intakeStatusCounts[$intakeId])) {
            continue; // no class that day for this intake
        }

        $counts        = $intakeStatusCounts[$intakeId];
        $totalStudents = (int)$row['total_students'];

        // Anyone without a present / recording-watched record counts as absent,
        // including students who have no attendance row at all for that day.
        $absent = max(0, $totalStudents - $counts['PRESENT'] - $counts['RECORDING_WATCHED']);

        $cards[] = [
            'intake_id'         => $intakeId,
            'intake'            => $row['intake'],
            'programme'         => $row['programme'],
            'faculty'           => $row['faculty'],
            // NOTE: assumes this file is only require'd from scripts inside /api/
            'intake_cover'      => (!empty($row['intake_cover']) && file_exists('../assets/imgs/intakes-covers/' . $row['intake_cover'])) ? $row['intake_cover'] : null,
            'total_students'    => $totalStudents,
            'present'           => $counts['PRESENT'],
            'recording_watched' => $counts['RECORDING_WATCHED'],
            'absent'            => $absent,
        ];
    }

    return $cards;
}

function isValidAttendanceDate($date): bool
{
    if (!$date) return false;
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
