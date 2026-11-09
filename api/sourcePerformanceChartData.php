<?php

$status = new stdClass();

require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json') {
                $content = trim(file_get_contents('php://input'));
                $decoded = json_decode($content, true);

                if (json_last_error() == JSON_ERROR_NONE) {

                    $STATUS_WARM = 3;
                    $STATUS_COMPLETED = 6;

                    $fromDate = $decoded['from_date'] ?? '';
                    $toDate = $decoded['to_date'] ?? '';

                    $dateCondition = "";

                    if (!empty($fromDate) && !empty($toDate)) {
                        $fromDate = Database::$connection->real_escape_string($fromDate);
                        $toDate = Database::$connection->real_escape_string($toDate);

                        $dateCondition = "WHERE DATE(l.`date_added`) BETWEEN '$fromDate' AND '$toDate'";
                    } else if (!empty($fromDate)) {
                        $fromDate = Database::$connection->real_escape_string($fromDate);

                        $dateCondition = "WHERE DATE(l.`date_added`) >= '$fromDate'";
                    } else if (!empty($toDate)) {
                        $toDate = Database::$connection->real_escape_string($toDate);

                        $dateCondition = "WHERE DATE(l.`date_added`) <= '$toDate'";
                    }

                    $sourcePerformance = Database::search("
                                SELECT 
                                    COALESCE(ls.`lead_source`, 'Unknown') AS lead_source,

                                    COUNT(l.`lead_id`) AS total_leads,

                                    SUM(CASE 
                                        WHEN l.`lead_status_lead_status_id` IN ($STATUS_WARM) 
                                        THEN 1 ELSE 0 
                                    END) AS warm_leads,

                                    SUM(CASE 
                                        WHEN l.`lead_status_lead_status_id` IN ($STATUS_COMPLETED) 
                                        THEN 1 ELSE 0 
                                    END) AS enrolled_leads

                                FROM `leads` l

                                LEFT JOIN `lead_source` ls 
                                    ON ls.`lead_source_id` = l.`lead_source_lead_source_id`

                                $dateCondition

                                GROUP BY ls.`lead_source_id`, ls.`lead_source`

                                ORDER BY total_leads DESC
                            ");

                    $sourceLabels = [];
                    $sourceLeadData = [];
                    $sourceWarmData = [];
                    $sourceEnrolledData = [];

                    while ($row = $sourcePerformance->fetch_assoc()) {
                        $sourceLabels[] = $row['lead_source'];
                        $sourceLeadData[] = (int)$row['total_leads'];
                        $sourceWarmData[] = (int)$row['warm_leads'];
                        $sourceEnrolledData[] = (int)$row['enrolled_leads'];
                    }

                                    $status->sourceLabels = $sourceLabels;
                    $status->sourceLeadData = $sourceLeadData;
                    $status->sourceWarmData = $sourceWarmData;
                    $status->sourceEnrolledData = $sourceEnrolledData;
                } else {
                    http_response_code(400);
                    $status->error = 'Invalid JSON';
                }
            } else {
                http_response_code(400);
                $status->error = 'Invalid Content-Type';
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo json_encode($status);
