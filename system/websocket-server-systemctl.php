<?php
// Get the path to the .env file from the arguments
if ($argc < 2) {
    die("Usage: php systemclt-script.php /path/to/.env\n");
}
$envPath = $argv[1];

// Load .env file
require $envPath . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($envPath);
$dotenv->load();

// Get the APP_DIR from .env
$appDir = $_ENV['APP_DIR'];

// Path to the actions.json file
$actionsFile = $appDir . '/tmp/actions.json';
$reportFile = $appDir . '/tmp/reports.json';

// Check if the file exists
if (!file_exists($actionsFile)) {
    die("Actions file not found.");
}

// Get the content of the actions.json file
$actionsJson = file_get_contents($actionsFile);

// Parse the JSON content
$actions = json_decode($actionsJson, true);

// Check if JSON parsing was successful
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error parsing JSON.");
}

// Function to execute system commands
function executeCommand($command) {
    $output = null;
    $retval = null;
    exec($command, $output, $retval);
    return $retval === 0;
    //return true;
}

// Get the existing report data
$reportData = file_exists($reportFile) ? json_decode(file_get_contents($reportFile), true) : [];

// Iterate through the actions and perform the necessary operations
foreach ($actions as $action) {
    $service = $action['service'];
    $actionType = $action['action'];
    $actionId = $action['id'];

    // Check if the action is already in the report file
    $actionExists = false;
    foreach ($reportData as $reportEntry) {
        if ($reportEntry['id'] === $actionId) {
            $actionExists = true;
            break;
        }
    }

    if ($actionExists) {
        echo "Action $actionType on $service with ID $actionId already reported.\n";
        continue;
    }

    switch ($actionType) {
        case 'restart':
            $command = "systemctl restart $service";
            break;
        case 'stop':
            $command = "systemctl stop $service";
            break;
        default:
            echo "Unknown action: $actionType\n";
            continue 2;
    }

    if (executeCommand($command)) {
        echo "Successfully executed $actionType on $service\n";
        $reportData[] = ['id' => $actionId, 'success' => true, 'message' => "Successfully executed $actionType on $service"];
    } else {
        echo "Failed to execute $actionType on $service\n";
        $reportData[] = ['id' => $actionId, 'success' => false, 'error' => "Failed to execute $actionType on $service"];
    }
}

// Save the report data
file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));

// Delete the tmp folder
/*$tmpDir = $appDir . '/tmp';
if (is_dir($tmpDir)) {
    array_map('unlink', glob("$tmpDir/*.*"));
    rmdir($tmpDir);
}*/
?>