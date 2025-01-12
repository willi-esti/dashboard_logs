<?php

$logDirs = explode(',', $_ENV['LOG_DIRS']);

if ($requestMethod === 'GET') {
    $files = [];
    foreach ($logDirs as $logDir) {
        $dirFiles = [];
        if (!is_readable($logDir)) {
            jsonResponse(['error' => 'Log directory is not readable'], 403);
            exit;
        }
        if ($handle = opendir($logDir)) {
            while (false !== ($file = readdir($handle))) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                $filePath = "$logDir/$file";
                $fileSize = filesize($filePath);
                // put in array
                array_push($dirFiles, ['name' => $file, 'size' => $fileSize]);
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
        jsonResponse(['message' => 'Log file not found', 'file' => $file], 404);
        exit;
    }

    if (!is_readable($filePath)) {
        jsonResponse(['success' => false, 'message' => 'Log file is not readable :' . $filePath], 403);
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
        jsonResponse(['message' => 'Failed to open log file'], 500);
        exit;
    }

    $logContent = implode("", $lines);
    $fileSize = filesize($filePath);
    jsonResponse(['content' => $logContent, 'size' => $fileSize], 200, false);
} else {
    jsonResponse(['message' => 'Method not allowed'], 405);
}
?>
