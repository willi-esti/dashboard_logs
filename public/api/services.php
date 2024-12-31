<?php

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

    if ($action === 'restart') {
        exec("sudo systemctl restart " . $service, $output, $status);
        jsonResponse(['content' => $output, 'status' => $status]);
    } else if ($action === 'stop') {
        exec("sudo systemctl stop " . $service, $output, $status);
        jsonResponse(['content' => $output, 'status' => $status]);
    } else if ($action === 'status') {
        exec("systemctl status " . $service, $output, $status);
        jsonResponse(['content' => $output]);
    } else {
        jsonResponse(['error' => 'Invalid action'], 400);
    }
}
?>
