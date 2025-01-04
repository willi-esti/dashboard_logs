<?php
// Get the path to the .env file from the arguments
if ($argc < 2) {
    die("Usage: php -f server-dashboard-systemctl.php /path/to/envdir\n");
}
$envPath = $argv[1];

// Load .env file
require $envPath . '/vendor/autoload.php';
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$dotenv = Dotenv\Dotenv::createImmutable($envPath);
$dotenv->load();

// Create a log channel
$log = new Logger('dashboard_server');
$log->pushHandler(new StreamHandler( $envPath . '/logs/server-dashboard-cron.log', Logger::INFO));


// Get the APP_DIR from .env
$appDir = $_ENV['APP_DIR'];

// Path to the actions.json file
$actionsFile = $appDir . '/tmp/actions.json';
$reportFile = $appDir . '/tmp/reports.json';

// Check if the file exists
if (!file_exists($actionsFile)) {
    $log->error("Actions file not found.");
    exit;
}


// Function to execute system commands
function executeCommand($command) {
    $output = null;
    $retval = null;
    exec($command, $output, $retval);
    return $retval === 0;
    //return true;
}

$start = time();

try {
    while ($start + 45 > time()) {
        // Get the content of the actions.json file
        $actionsJson = file_get_contents($actionsFile);

        // Parse the JSON content
        $actions = json_decode($actionsJson, true);

        // Check if JSON parsing was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            $log->error("Error parsing JSON: " . json_last_error_msg());
            exit;
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
                $log->warning("Action $actionType on $service with ID $actionId already reported.");
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
                    $log->error("Unknown action: $actionType");
                    continue 2;
            }

            if (executeCommand($command)) {
                $log->info("Successfully executed $actionType on $service");
                $reportData[] = ['id' => $actionId, 'success' => true, 'message' => "Successfully executed $actionType on $service"];
            } else {
                $log->error("Failed to execute $actionType on $service");
                $reportData[] = ['id' => $actionId, 'success' => false, 'message' => "Failed to execute $actionType on $service"];
            }
        }

        // Save the report data
        file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));

        // Sleep for 60 seconds before running again
        sleep(1);
    }
} catch (Exception $e) {
    $log->error("Error: " . $e->getMessage());
}
?>