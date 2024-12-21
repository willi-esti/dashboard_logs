
<?php

if ($requestMethod === 'GET') {
    $logFiles = array_filter(glob('/var/log/*'), 'is_file');
    jsonResponse(['files' => array_values($logFiles)]);
} elseif ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $file = sanitize($data['file']);
    $filePath = escapeshellarg("/var/log/$file");

    if (!file_exists($filePath)) {
        jsonResponse(['error' => 'Log file not found'], 404);
    }

    jsonResponse(['content' => file_get_contents($filePath)]);
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
