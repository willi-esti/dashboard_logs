<?php

$logDir = '/var/www/html/server-dashboard/logs';

if ($requestMethod === 'GET') {
    $files = [];
    if ($handle = opendir($logDir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            // put in array
            array_push($files, $file);
        }
        closedir($handle);
    }
    //$logFiles = array_filter(glob($logDir . "/*"), 'is_file');
    jsonResponse(array_values($files));
} elseif ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $file = sanitize($data['file']);
    $filePath = "$logDir/$file";

    if (!file_exists($filePath)) {
        jsonResponse(['error' => 'Log file not found', 'file' => $filePath], 404);
        exit;
    }

    if (!is_readable($filePath)) {
        jsonResponse(['error' => 'Log file is not readable'], 403);
        exit;
    }

    $lines = [];
    $handle = fopen($filePath, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $lines[] = $line;
            if (count($lines) > 10) {
                array_shift($lines);
            }
        }
        fclose($handle);
    } else {
        jsonResponse(['error' => 'Failed to open log file'], 500);
        exit;
    }

    $logContent = implode("", $lines);
    jsonResponse(['content' => $logContent], 200, false);
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
