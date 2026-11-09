<?php
// Shared by api/getUniformBreakdown.php and api/uniformDistribution.php
const UNIFORM_PAYMENT_TYPE_ID = 21;
const UNIFORM_PAID_STATUS_ID  = 5; // TODO: set to your "paid" payment_status id

// One row per student intake (GROUP BY) so multiple payment rows never duplicate a student.
function uniformFrom(): string
{
    return "FROM (
        SELECT COALESCE(cp.students_has_intakes_students_has_intakes_id, pp.students_has_intakes_students_has_intakes_id) AS shi_id,
               SUM(cp.amount - cp.discount) AS scheduled,
               SUM(IF(cp.payment_status_payment_status_id = " . UNIFORM_PAID_STATUS_ID . ", cp.amount - cp.discount, 0)) AS paid,
               cp.course_payments_id
        FROM course_payments cp
        LEFT JOIN payment_plan_has_students_has_intakes pp
               ON pp.payment_plan_has_students_has_intakes_id = cp.payment_plan_has_students_has_intakes_id
        WHERE cp.payment_type_payment_type_id = " . UNIFORM_PAYMENT_TYPE_ID . "
        GROUP BY shi_id
    ) u
    INNER JOIN students_has_intakes shi ON shi.students_has_intakes_id = u.shi_id
    INNER JOIN students s ON s.student_id = shi.students_student_id
    INNER JOIN login_details ld ON ld.login_details_id = s.login_details_login_details_id
    INNER JOIN title t ON t.title_id = s.title_title_id
    INNER JOIN intakes i ON i.intake_id = shi.intakes_intake_id
    INNER JOIN programmes p ON p.programme_id = i.programmes_programme_id
    INNER JOIN faculties f ON f.faculty_id = p.faculties_faculty_id
    LEFT JOIN uniform_distribution_confirmations udc ON udc.students_has_intakes_id = shi.students_has_intakes_id";
}

// $full = false ignores search/payment/uniform filters (used for dropdown options).
function uniformWhere($role, int $uid, array $f, bool $full = true): string
{
    $w = " WHERE 1=1";
    if (SessionUser::ifUserIsCoordinator($role)) {
        $w .= " AND EXISTS (SELECT 1 FROM users_has_permission_to_programmes UPP
                            WHERE UPP.programmes_programme_id = p.programme_id AND UPP.users_user_id = $uid)";
    } elseif (SessionUser::ifUserIsMarketingSRO($role)) {
        $w .= " AND shi.counselor_user_id = $uid";
    }
    if (!empty($f['faculty_id']))   $w .= " AND f.faculty_id = " . intval($f['faculty_id']);
    if (!empty($f['programme_id'])) $w .= " AND p.programme_id = " . intval($f['programme_id']);
    if (!empty($f['intake_id']))    $w .= " AND i.intake_id = " . intval($f['intake_id']);

    if ($full) {
        $q = trim($f['search'] ?? '');
        if ($q !== '') {
            $q = Database::escape($q);
            $w .= " AND (CONCAT(ld.first_name, ' ', ld.last_name) LIKE '%$q%' OR shi.student_id LIKE '%$q%')";
        }
        if (($f['payment_status'] ?? '') === 'PENDING')  $w .= " AND (u.scheduled - u.paid) > 0.005";
        if (($f['payment_status'] ?? '') === 'RECEIVED') $w .= " AND (u.scheduled - u.paid) <= 0.005";
        if (in_array($f['uniform_status'] ?? '', ['PENDING', 'RECEIVED'], true)) {
            $w .= " AND shi.uniform_status = '" . $f['uniform_status'] . "'";
        }
    }
    return $w;
}