<?php

$logDir = '/var/www/html/server-dashboard/logs';

if ($requestMethod === 'GET') {
    $logFiles = array_filter(glob($logDir . "/*"), 'is_file');
    jsonResponse(array_values($logFiles));
} elseif ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $file = sanitize($data['file']);
    $filePath = escapeshellarg($logDir. "/$file");

    if (!file_exists($filePath)) {
        jsonResponse(['error' => 'Log file not found'], 404);
    }

    jsonResponse(['content' => file_get_contents($filePath)]);
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
