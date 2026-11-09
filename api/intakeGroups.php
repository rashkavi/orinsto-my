<?php
/**
 * api/intakeGroups.php
 * ------------------------------------------------------------------
 * Intake student group management (intake-details → Students → Manage Groups).
 *
 * POST, JSON body: { "action": "...", "intake_id": n, ... }
 *
 *   list          {intake_id}                                  view
 *   eligible      {intake_id, group_id, search?}               manage
 *   members       {intake_id, group_id}                        view
 *   history       {intake_id, group_id}                        view
 *   create        {intake_id, group_name, max_students}        manage
 *   update        {intake_id, group_id, group_name, max_students} manage
 *   add_students  {intake_id, group_id, shi_ids:[..]}          manage
 *   move          {intake_id, shi_id, from_group_id, to_group_id} manage
 *   remove        {intake_id, shi_id, group_id}                manage
 *
 * Response: { done: true, ... }  or  { error: "message" }
 */
$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/classes/IntakeGroups.php");

header('Content-Type: application/json');

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

    $action   = isset($in['action']) ? (string)$in['action'] : '';
    $intakeId = isset($in['intake_id']) && is_numeric($in['intake_id']) ? (int)$in['intake_id'] : 0;

    if ($intakeId <= 0 || !IntakeGroups::getIntake($intakeId)) {
        throw new IntakeGroupException('Invalid intake.');
    }

    $readActions  = ['list', 'members', 'history'];
    $writeActions = ['eligible', 'create', 'update', 'add_students', 'move', 'remove'];

    if (in_array($action, $readActions, true)) {
        if (!IntakeGroups::canView($sessionUserRole, $intakeId)) {
            throw new IntakeGroupException('You do not have permission to view groups of this intake.');
        }
    } elseif (in_array($action, $writeActions, true)) {
        if (!IntakeGroups::canManage($sessionUserRole, $intakeId)) {
            throw new IntakeGroupException('You do not have permission to manage groups of this intake.');
        }
    } else {
        throw new IntakeGroupException('Unknown action.');
    }

    $gid = function ($key) use ($in) {
        return isset($in[$key]) && is_numeric($in[$key]) ? (int)$in[$key] : 0;
    };

    switch ($action) {

        /* ---------------------------------------------------- list */
        case 'list':
            $groups = IntakeGroups::listGroups($intakeId);
            foreach ($groups as &$g) {
                $g['intake_group_id'] = (int)$g['intake_group_id'];
                $g['max_students']    = (int)$g['max_students'];
                $g['student_count']   = (int)$g['student_count'];
                $g['available']       = max(0, $g['max_students'] - $g['student_count']);
            }
            unset($g);
            $unassigned = IntakeGroups::row(
                "SELECT COUNT(*) AS `c` FROM `students_has_intakes` AS `shi`
                 LEFT JOIN `intake_groups_has_students_has_intakes` AS `m`
                        ON `m`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                 WHERE `shi`.`intakes_intake_id` = ? AND `shi`.`status_status_id` <> ?
                   AND `m`.`intake_groups_has_students_has_intakes_id` IS NULL",
                'ii',
                [$intakeId, IntakeGroups::STATUS_DISCONTINUED]
            );
            $status->groups     = $groups;
            $status->unassigned = (int)$unassigned['c'];
            $status->can_manage = IntakeGroups::canManage($sessionUserRole, $intakeId);
            $status->done       = true;
            break;

        /* ------------------------------------------------ eligible */
        case 'eligible':
            IntakeGroups::getGroup($gid('group_id'), $intakeId);
            $search = isset($in['search']) ? mb_substr((string)$in['search'], 0, 100) : '';
            $status->students = array_map(function ($r) {
                return [
                    'shi_id'      => (int)$r['shi_id'],
                    'reg_no'      => $r['reg_no'],
                    'name'        => trim($r['title'] . '. ' . $r['first_name'] . ' ' . $r['last_name']),
                    'first_name'  => $r['first_name'],
                    'last_name'   => $r['last_name'],
                    'email'       => $r['email'],
                    'status'      => $r['status'],
                    'profile_img' => $r['profile_img'],
                ];
            }, IntakeGroups::eligibleStudents($intakeId, $search));
            $status->done = true;
            break;

        /* ------------------------------------------------- members */
        case 'members':
            $group = IntakeGroups::getGroup($gid('group_id'), $intakeId);
            $status->group = [
                'intake_group_id' => (int)$group['intake_group_id'],
                'intake_group'    => $group['intake_group'],
                'max_students'    => (int)$group['max_students'],
            ];
            $status->students = array_map(function ($r) {
                return [
                    'shi_id'      => (int)$r['shi_id'],
                    'reg_no'      => $r['reg_no'],
                    'name'        => trim($r['title'] . '. ' . $r['first_name'] . ' ' . $r['last_name']),
                    'first_name'  => $r['first_name'],
                    'last_name'   => $r['last_name'],
                    'email'       => $r['email'],
                    'status'      => $r['status'],
                    'status_id'   => (int)$r['status_id'],
                    'profile_img' => $r['profile_img'],
                ];
            }, IntakeGroups::groupMembers($group['intake_group_id']));
            $status->can_manage = IntakeGroups::canManage($sessionUserRole, $intakeId);
            $status->done = true;
            break;

        /* ------------------------------------------------- history */
        case 'history':
            $group = IntakeGroups::getGroup($gid('group_id'), $intakeId);
            $status->group   = ['intake_group' => $group['intake_group']];
            $status->history = IntakeGroups::history($group['intake_group_id']);
            $status->done    = true;
            break;

        /* -------------------------------------------------- create */
        case 'create':
            $name = IntakeGroups::validateGroupName($in['group_name'] ?? '');
            $max  = IntakeGroups::validateCapacity($in['max_students'] ?? '');

            $newId = IntakeGroups::transaction(function () use ($intakeId, $name, $max, $userId) {
                // Lock the intake row so two simultaneous creates with the
                // same name serialise; the UNIQUE key is the final guard.
                IntakeGroups::row("SELECT `intake_id` FROM `intakes` WHERE `intake_id` = ? FOR UPDATE", 'i', [$intakeId]);
                if (IntakeGroups::nameTaken($intakeId, $name)) {
                    throw new IntakeGroupException('A group named "' . $name . '" already exists in this intake.');
                }
                $r = IntakeGroups::exec(
                    "INSERT INTO `intake_groups`
                        (`intake_group`, `max_students`, `intakes_intake_id`, `users_user_id`, `date_added`)
                     VALUES (?, ?, ?, ?, ?)",
                    'siiis',
                    [$name, $max, $intakeId, $userId, IntakeGroups::now()]
                );
                IntakeGroups::log($r['insert_id'], 'GROUP_CREATED', $userId, [
                    'details' => 'Created "' . $name . '" with capacity ' . $max,
                ]);
                return $r['insert_id'];
            });
            $status->group_id = (int)$newId;
            $status->done = 'Group "' . $name . '" created successfully.';
            break;

        /* -------------------------------------------------- update */
        case 'update':
            $name = IntakeGroups::validateGroupName($in['group_name'] ?? '');
            $max  = IntakeGroups::validateCapacity($in['max_students'] ?? '');
            $groupId = $gid('group_id');

            IntakeGroups::transaction(function () use ($intakeId, $groupId, $name, $max, $userId) {
                $group = IntakeGroups::getGroup($groupId, $intakeId, true);
                $count = IntakeGroups::memberCount($groupId);
                if ($max < $count) {
                    throw new IntakeGroupException("Capacity can't be lower than the $count student(s) already in this group.");
                }
                if (IntakeGroups::nameTaken($intakeId, $name, $groupId)) {
                    throw new IntakeGroupException('A group named "' . $name . '" already exists in this intake.');
                }
                $changes = [];
                if ($group['intake_group'] !== $name) $changes[] = 'Name: "' . $group['intake_group'] . '" → "' . $name . '"';
                if ((int)$group['max_students'] !== $max) $changes[] = 'Capacity: ' . $group['max_students'] . ' → ' . $max;
                if (!$changes) return;

                IntakeGroups::exec(
                    "UPDATE `intake_groups` SET `intake_group` = ?, `max_students` = ?, `date_updated` = ?
                     WHERE `intake_group_id` = ?",
                    'sisi',
                    [$name, $max, IntakeGroups::now(), $groupId]
                );
                IntakeGroups::log($groupId, 'GROUP_UPDATED', $userId, ['details' => implode('; ', $changes)]);
            });
            $status->done = 'Group updated successfully.';
            break;

        /* -------------------------------------------- add_students */
        case 'add_students':
            $groupId = $gid('group_id');
            $shiIds  = IntakeGroups::toIdList($in['shi_ids'] ?? []);
            if (!$shiIds) {
                throw new IntakeGroupException('Please select at least one student.');
            }
            if (count($shiIds) > 1000) {
                throw new IntakeGroupException('Too many students in one request.');
            }

            $added = IntakeGroups::transaction(function () use ($intakeId, $groupId, $shiIds, $userId) {
                // Row lock on the group: a concurrent add/move for the SAME group
                // waits here until this transaction commits, so the count below
                // is accurate and capacity cannot be exceeded.
                $group = IntakeGroups::getGroup($groupId, $intakeId, true);
                $regs  = IntakeGroups::lockEligibleRegistrations($intakeId, $shiIds);

                $count     = IntakeGroups::memberCount($groupId);
                $available = (int)$group['max_students'] - $count;
                if (count($shiIds) > $available) {
                    throw new IntakeGroupException(
                        '"' . $group['intake_group'] . '" has only ' . max(0, $available) . ' seat(s) left, but '
                            . count($shiIds) . ' student(s) were selected.'
                    );
                }

                $now = IntakeGroups::now();
                foreach ($shiIds as $shiId) {
                    try {
                        IntakeGroups::exec(
                            "INSERT INTO `intake_groups_has_students_has_intakes`
                                (`intake_groups_intake_group_id`, `students_has_intakes_students_has_intakes_id`, `users_user_id`, `date_added`)
                             VALUES (?, ?, ?, ?)",
                            'iiis',
                            [$groupId, $shiId, $userId, $now]
                        );
                    } catch (Exception $e) {
                        if ((int)$e->getCode() === 1062) {
                            // A parallel request put this registration into another group.
                            throw new IntakeGroupException(IntakeGroups::label($regs[$shiId]) . ' was just assigned to another group. Refresh and try again.');
                        }
                        throw $e;
                    }
                    IntakeGroups::log($groupId, 'STUDENT_ADDED', $userId, [
                        'shi_id'        => $shiId,
                        'student_label' => IntakeGroups::label($regs[$shiId]),
                        'to'            => $groupId,
                    ]);
                }
                return count($shiIds);
            });
            $status->added = $added;
            $status->done = $added . ' student(s) added to the group.';
            break;

        /* ---------------------------------------------------- move */
        case 'move':
            $shiId = $gid('shi_id');
            $from  = $gid('from_group_id');
            $to    = $gid('to_group_id');
            if ($shiId <= 0) throw new IntakeGroupException('Invalid student.');
            if ($to <= 0)    throw new IntakeGroupException('Please select the destination group.');
            if ($from === $to) throw new IntakeGroupException('The student is already in that group.');

            $toName = IntakeGroups::transaction(function () use ($intakeId, $shiId, $from, $to, $userId) {
                // Lock both groups in id order to avoid deadlocks between
                // opposite-direction moves (A→B and B→A at the same time).
                $locked = [];
                foreach ([min($from, $to), max($from, $to)] as $id) {
                    $locked[$id] = IntakeGroups::getGroup($id, $intakeId, true);
                }
                $src = $locked[$from];
                $dst = $locked[$to];

                $m = IntakeGroups::lockMembership($intakeId, $shiId, $from);

                $dstCount = IntakeGroups::memberCount($to);
                if ($dstCount >= (int)$dst['max_students']) {
                    throw new IntakeGroupException('"' . $dst['intake_group'] . '" is full (' . $dstCount . '/' . $dst['max_students'] . ').');
                }

                IntakeGroups::exec(
                    "UPDATE `intake_groups_has_students_has_intakes`
                     SET `intake_groups_intake_group_id` = ?, `users_user_id` = ?, `date_added` = ?
                     WHERE `intake_groups_has_students_has_intakes_id` = ?",
                    'iisi',
                    [$to, $userId, IntakeGroups::now(), (int)$m['membership_id']]
                );

                $label = IntakeGroups::label($m);
                $detail = 'Moved from "' . $src['intake_group'] . '" to "' . $dst['intake_group'] . '"';
                // One entry in each group's history so both show the move.
                IntakeGroups::log($from, 'STUDENT_MOVED_OUT', $userId, [
                    'shi_id' => $shiId, 'student_label' => $label, 'from' => $from, 'to' => $to, 'details' => $detail,
                ]);
                IntakeGroups::log($to, 'STUDENT_MOVED_IN', $userId, [
                    'shi_id' => $shiId, 'student_label' => $label, 'from' => $from, 'to' => $to, 'details' => $detail,
                ]);
                return $dst['intake_group'];
            });
            $status->done = 'Student moved to "' . $toName . '".';
            break;

        /* -------------------------------------------------- remove */
        case 'remove':
            $shiId   = $gid('shi_id');
            $groupId = $gid('group_id');
            if ($shiId <= 0) throw new IntakeGroupException('Invalid student.');

            IntakeGroups::transaction(function () use ($intakeId, $shiId, $groupId, $userId) {
                $group = IntakeGroups::getGroup($groupId, $intakeId, true);
                $m = IntakeGroups::lockMembership($intakeId, $shiId, $groupId);
                IntakeGroups::exec(
                    "DELETE FROM `intake_groups_has_students_has_intakes` WHERE `intake_groups_has_students_has_intakes_id` = ?",
                    'i',
                    [(int)$m['membership_id']]
                );
                IntakeGroups::log($groupId, 'STUDENT_REMOVED', $userId, [
                    'shi_id' => $shiId, 'student_label' => IntakeGroups::label($m), 'from' => $groupId,
                    'details' => 'Removed from "' . $group['intake_group'] . '"',
                ]);
            });
            $status->done = 'Student removed from the group.';
            break;
    }
} catch (IntakeGroupException $e) {
    $status->error = $e->getMessage();
} catch (Throwable $e) {
    error_log('[intakeGroups] ' . $e->getMessage());
    if ((int)$e->getCode() === 1062) {
        $status->error = 'That change conflicts with existing data (duplicate). Refresh and try again.';
    } else {
        $status->error = 'Something went wrong. Please try again.';
    }
}

echo json_encode($status);
