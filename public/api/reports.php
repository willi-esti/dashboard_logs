<?php

require_once __DIR__ . '/utils.php';

// Load .env file
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Get the APP_DIR from .env
$appDir = $_ENV['APP_DIR'];

// Path to the reports.json file
$reportFile = $appDir . '/tmp/reports.json';

// Check if the file exists
if (!file_exists($reportFile)) {
    jsonResponse(['error' => 'No reports file'], 200);
}

// Get the content of the reports.json file
$reportJson = file_get_contents($reportFile);

// Parse the JSON content
$reports = json_decode($reportJson, true);

// Check if JSON parsing was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['error' => 'Error parsing JSON'], 500);
}

// Path to the actions.json file
$actionsFile = $appDir . '/tmp/actions.json';
$actionsJson = file_get_contents($actionsFile);
$actions = json_decode($actionsJson, true);

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

// If debug is set, show the content of actions and reports
if (isset($_GET['debug']) && $_GET['debug'] == 'true') {
    jsonResponse(['actions' => $actions, 'reports' => $reports]);
}

// Respond with the reports data
if (empty($reports)) {
    jsonResponse($reports, 200, false);
}
else {
    jsonResponse($reports);
}

?>