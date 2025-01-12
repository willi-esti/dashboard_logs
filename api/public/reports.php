<?php

// Get the APP_DIR from .env
$appDir = $_ENV['APP_DIR'];

// Path to the reports.json file
$tmpDir = $appDir . '/tmp';
$reportFile = $tmpDir . '/reports.json';
$actionsFile = $appDir . '/tmp/actions.json';

if (!file_exists($tmpDir)) {
    mkdir($tmpDir);
}

// Check if the file exists
if (!file_exists($reportFile)) {
    file_put_contents($reportFile, json_encode([]));
    jsonResponse(['error' => 'No reports file'], 200);
}

// Check if the file exists
if (!file_exists($actionsFile)) {
    file_put_contents($actionsFile, json_encode([]));
    jsonResponse(['error' => 'No actions file'], 200);
}

// Get the content of the reports.json file
$reportJson = file_get_contents($reportFile);
$reports = json_decode($reportJson, true);

// Check if JSON parsing was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['error' => 'Error parsing reports file'], 500);
}

$actionsJson = file_get_contents($actionsFile);
$actions = json_decode($actionsJson, true);

// Check if JSON parsing was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['error' => 'Error parsing actions file'], 500);
}

// If debug is set, show the content of actions and reports
if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
    jsonResponse(['actions' => $actions, 'reports' => $reports]);
}

// Remove the actions with the IDs of the report file in the actions file
$is_report = false;
foreach ($reports as $report) {
    $actionId = $report['id'];
    foreach ($actions as $key => $action) {
        if ($action['id'] === $actionId) {
            $is_report = true;
            unset($actions[$key]);
            break;
        }
    }
}
if ($is_report) {
    file_put_contents($actionsFile, json_encode($actions, JSON_PRETTY_PRINT));
    file_put_contents($reportFile, json_encode([], JSON_PRETTY_PRINT));
}

// Respond with the reports data
if (empty($reports)) {
    jsonResponse($reports, 200, false);
}
else {
    jsonResponse($reports);
}

?>