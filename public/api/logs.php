<?php

// env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$logDirs = explode(',', $_ENV['LOG_DIRS']);

if ($requestMethod === 'GET') {
    $files = [];
    foreach ($logDirs as $logDir) {
        $dirFiles = [];
        if ($handle = opendir($logDir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                // put in array
                array_push($dirFiles, $file);
            }
            closedir($handle);
        }
        $files[$logDir] = $dirFiles;
    }
    jsonResponse($files);
} elseif ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $file = sanitize($data['file']);
    $filePath = null;

    foreach ($logDirs as $logDir) {
        if (file_exists("$logDir/$file")) {
            $filePath = "$logDir/$file";
            break;
        }
    }

    if ($filePath === null) {
        jsonResponse(['error' => 'Log file not found', 'file' => $file], 404);
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
