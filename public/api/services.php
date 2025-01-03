<?php

require_once __DIR__ . '/utils.php';

// env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

if ($requestMethod === 'GET') {
    $services = array_merge(
        !empty($_ENV['SERVICES']) ? explode(',', $_ENV['SERVICES']) : [],
        !empty($_ENV['PROTECTED_SERVICES']) ? explode(',', $_ENV['PROTECTED_SERVICES']) : []
    );
    //print_r(!empty($_ENV['PROTECTED_SERVICES']) ? explode(',', $_ENV['PROTECTED_SERVICES']) : []);
    foreach ($services as $service) {
        $isProtected = in_array($service, !empty($_ENV['PROTECTED_SERVICES']) ? explode(',', $_ENV['PROTECTED_SERVICES']) : []);
        $data[] = [
            'name' => $service,
            'protected' => $isProtected
        ];
        $result = exec("systemctl is-active --quiet " . $service . " && echo 1 || echo 0", $output, $status);
        $data[count($data) - 1]['status'] = $result;
    }

    jsonResponse($data);
} else if ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    logError(json_encode($data));
    $service = $data['service'];
    $action = $data['action'];

    $services = !empty($_ENV['SERVICES']) ? explode(',', $_ENV['SERVICES']) : [];
    $protectedServices = !empty($_ENV['PROTECTED_SERVICES']) ? explode(',', $_ENV['PROTECTED_SERVICES']) : [];

    if (!in_array($service, $services) && !in_array($service, $protectedServices)) {
        jsonResponse(['error' => 'Service not found'], 404);
    }

    if (in_array($service, $protectedServices) && $action !== 'status') {
        jsonResponse(['error' => 'Action not allowed on protected service'], 403);
    }

    if ($action === 'status') {
        exec("/bin/systemctl status " . $service, $output, $status);
        jsonResponse(['content' => $output]);
    } else if ($_ENV['MODE'] === 'selinux') {
        $tmpDir = __DIR__ . '/../../tmp';
        if (!file_exists($tmpDir)) {
            mkdir($tmpDir);
        }
        $logFile = $tmpDir . '/actions.json';
        $reportFile = $tmpDir . '/reports.json';
        $logData = file_exists($logFile) ? json_decode(file_get_contents($logFile), true) : [];
        $reportData = file_exists($reportFile) ? json_decode(file_get_contents($reportFile), true) : [];

        $actionId = time();
        $newEntry = ['id' => $actionId, 'service' => $service, 'action' => $action];

        // Check if the action and service are already in the log file
        $entryExists = false;
        foreach ($logData as $logEntry) {
            if ($logEntry['service'] === $service && $logEntry['action'] === $action) {
                $entryExists = true;
                break;
            }
        }

        if (!$entryExists) {
            $logData[] = $newEntry;
            file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT));
        }

        // Check if the action is in the report file
        foreach ($reportData as $key => $reportEntry) {
            if ($reportEntry['id'] === $actionId) {
                jsonResponse(['message' => $reportEntry['message']]);
                unset($reportData[$key]);
                file_put_contents($reportFile, json_encode(array_values($reportData), JSON_PRETTY_PRINT));
                exit;
            }
        }

        jsonResponse(['status' => 2, 'message' => 'Action logged due to SELinux mode, the cron job will execute the action in a few seconds. You can check the pending actions with getReportsDebug()'], 202);
    }
    else if ($action === 'restart') {
        exec("bash ../../system/systemctl.sh restart " . $service, $output, $status);
        jsonResponse(['content' => $output, 'status' => $status]);
    } else if ($action === 'stop') {
        exec("bash ../../system/systemctl.sh stop " . $service, $output, $status);
        jsonResponse(['content' => $output, 'status' => $status]);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
}
?>
