<?php

/**
 * api/getBatchActivitySummary.php
 * --------------------------------
 * Thin AJAX wrapper around BatchDashboardSummary::getBatchDashboardSummary().
 * Returns the 12 cards (already built with values/change/period/chart) plus
 * a standalone monthly-engagement series for the bar chart on the page.
 *
 * FLAG: "engagement" is mapped to the assignments-per-month series that
 * BatchDashboardSummary already computes internally for the "Assignments"
 * card's sparkline — I've re-derived the same series here as labelled
 * month buckets for the bigger bar chart. If lecture attendance or logins
 * is what you actually meant by engagement, point me at the source table
 * and I'll swap getMonthlySeries() for that query instead.
 */

require_once './../includes/UserClass.php';
require_once './../includes/UserPermissionsClass.php';
require_once "./../includes/connection.php";
require_once '../includes/classes/BatchDashboardSummary.php';

header('Content-Type: application/json');

if (!SessionUser::hasValidSession()) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired.']);
    exit;
}

$sessionUserObject = SessionUser::getCurrentUserObject();
UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

$intakeId = isset($_GET['intake-id']) ? (int)$_GET['intake-id'] : 0;

if ($intakeId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Missing or invalid intake-id.']);
    exit;
}

try {
    // ASSUMPTION: full admin view uses getBatchDashboardSummary(); swap to
    // getLecturerBatchDashboardSummary() here if this endpoint is ever
    // reused for a lecturer-scoped variant of this report.
    $cards = BatchDashboardSummary::getBatchDashboardSummary($intakeId);

    // Re-derive the same month-key labels BatchDashboardSummary uses
    // internally (current month + 4 back) so the bar chart's x-axis lines
    // up with the sparkline data already inside $cards.
    $monthLabels = [];
    for ($i = 4; $i >= 0; $i--) {
        $monthLabels[] = date('M Y', strtotime("-{$i} month"));
    }

    // The "Assignments" card (row 2, index 4 in getBatchDashboardSummary's
    // array) already carries the per-month submissions series — reuse it
    // as the engagement chart values rather than re-querying.
    $engagementValues = [];
    foreach ($cards as $card) {
        if ($card['title'] === 'Assignments' && !empty($card['chart'])) {
            $engagementValues = $card['chart'];
            break;
        }
    }
    if (empty($engagementValues)) {
        $engagementValues = array_fill(0, count($monthLabels), 0);
    }

    echo json_encode([
        'status' => 'success',
        'cards' => $cards,
        'engagement_chart_labels' => $monthLabels,
        'engagement_chart_values' => $engagementValues,
    ]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to build batch summary: ' . $e->getMessage()]);
}