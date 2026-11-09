<?php

// declare(strict_types=1);

date_default_timezone_set('Asia/Colombo');

// ========================================================
// PURPOSE
// ========================================================
// This is a TEST-ONLY cron script used to verify that the
// server's cron scheduler is correctly configured and firing
// this script on time. It does NOT touch the database.
// Delete or disable once cron setup is confirmed working.
// ========================================================

$logFile = __DIR__ . '/logs/testCronSetupCheck.txt';

function writeLog(string $message, string $logFile): void
{
    // Make sure the logs directory exists (in case this is a fresh setup)
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

try {
    $startTime = microtime(true);

    writeLog("Test cron triggered successfully.", $logFile);
    writeLog("Server date/time: " . date("Y-m-d H:i:s"), $logFile);
    writeLog("Timezone in use: " . date_default_timezone_get(), $logFile);
    writeLog("PHP SAPI: " . php_sapi_name(), $logFile);
    writeLog("Script path: " . __FILE__, $logFile);

    $executionTime = round(microtime(true) - $startTime, 4);
    writeLog("Run complete | execution_time_sec: $executionTime", $logFile);

    echo json_encode([
        "status" => "success",
        "message" => "Test cron ran and logged successfully.",
        "timestamp" => date("Y-m-d H:i:s")
    ]);
} catch (Throwable $e) {
    writeLog("Error: " . $e->getMessage(), $logFile);
    echo "Error: " . $e->getMessage() . PHP_EOL;
}