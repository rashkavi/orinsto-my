<?php
/**
 * api/lectureGroups.php
 * ------------------------------------------------------------------
 * Lecture audience (which intake groups a time_table row targets).
 * No rows in time_table_has_intake_groups = all students in the intake.
 *
 * POST, JSON body:
 *   options {intake_id}                 groups of an intake, for the schedule form
 *   get     {lecture_id}                lecture's intake, its groups, and saved selection
 *   save    {lecture_id, group_ids:[]}  replace the saved selection ([] = all-intake)
 *
 * Saving the audience is a separate call from editing date/time/title,
 * so editing those fields elsewhere can never wipe the audience.
 */
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/classes/IntakeGroups.php");

header('Content-Type: application/json');

function lg_groups_payload($intakeId)
{
    return array_map(function ($g) {
        return [
            'id'            => (int)$g['intake_group_id'],
            'name'          => $g['intake_group'],
            'max_students'  => (int)$g['max_students'],
            'student_count' => (int)$g['student_count'],
        ];
    }, IntakeGroups::listGroups($intakeId));
}

try {
    if (!SessionUser::hasValidSession()) {
        throw new IntakeGroupException('Authentication Failed!');
    }
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        throw new IntakeGroupException('Authentication Failed!');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(400);
        throw new IntakeGroupException('Invalid request method');
    }
    $sessionUserObject = SessionUser::getCurrentUserObject();
    UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);
    $userId = (int)$sessionUserObject['user_id'];

    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) {
        http_response_code(400);
        throw new IntakeGroupException('Invalid JSON');
    }
    $action = (string)($in['action'] ?? '');

    switch ($action) {

        case 'options':
            $intakeId = isset($in['intake_id']) && is_numeric($in['intake_id']) ? (int)$in['intake_id'] : 0;
            if ($intakeId <= 0 || !IntakeGroups::getIntake($intakeId)) {
                throw new IntakeGroupException('Invalid intake.');
            }
            if (!(IntakeGroups::canEditLectureAudience($sessionUserRole) || IntakeGroups::canView($sessionUserRole, $intakeId))) {
                throw new IntakeGroupException('You do not have permission to view groups of this intake.');
            }
            $status->groups = lg_groups_payload($intakeId);
            $status->done = true;
            break;

        case 'get':
            $lectureId = isset($in['lecture_id']) && is_numeric($in['lecture_id']) ? (int)$in['lecture_id'] : 0;
            $ctx = $lectureId > 0 ? IntakeGroups::getLectureContext($lectureId) : null;
            if (!$ctx) {
                throw new IntakeGroupException('Lecture not found.');
            }
            if (!IntakeGroups::canEditLectureAudience($sessionUserRole)) {
                throw new IntakeGroupException('You do not have permission to change this lecture\'s audience.');
            }
            $map = IntakeGroups::getAudienceMap([$lectureId]);
            $status->lecture  = [
                'lecture_id' => (int)$ctx['time_table_id'],
                'intake_id'  => (int)$ctx['intake_id'],
                'intake'     => $ctx['intake'],
                'subject'    => $ctx['subject'],
            ];
            $status->groups   = lg_groups_payload($ctx['intake_id']);
            $status->selected = array_map(function ($g) {
                return $g['id'];
            }, $map[$lectureId] ?? []);
            $status->done = true;
            break;

        case 'save':
            $lectureId = isset($in['lecture_id']) && is_numeric($in['lecture_id']) ? (int)$in['lecture_id'] : 0;
            $ctx = $lectureId > 0 ? IntakeGroups::getLectureContext($lectureId) : null;
            if (!$ctx) {
                throw new IntakeGroupException('Lecture not found.');
            }
            if (!IntakeGroups::canEditLectureAudience($sessionUserRole)) {
                throw new IntakeGroupException('You do not have permission to change this lecture\'s audience.');
            }
            $groupIds = IntakeGroups::toIdList($in['group_ids'] ?? []);

            IntakeGroups::transaction(function () use ($ctx, $lectureId, $groupIds, $userId) {
                // Lock the lecture row so two saves don't interleave delete/insert.
                IntakeGroups::row("SELECT `time_table_id` FROM `time_table` WHERE `time_table_id` = ? FOR UPDATE", 'i', [$lectureId]);
                IntakeGroups::validateGroupsForIntake($ctx['intake_id'], $groupIds);
                IntakeGroups::saveLectureGroups($lectureId, $groupIds, $userId);
            });

            $map = IntakeGroups::getAudienceMap([$lectureId]);
            $status->audienceGroups = $map[$lectureId] ?? [];
            $status->done = 'Lecture audience updated.';
            break;

        default:
            throw new IntakeGroupException('Unknown action.');
    }
} catch (IntakeGroupException $e) {
    $status->error = $e->getMessage();
} catch (Throwable $e) {
    error_log('[lectureGroups] ' . $e->getMessage());
    $status->error = 'Something went wrong. Please try again.';
}

echo json_encode($status);
