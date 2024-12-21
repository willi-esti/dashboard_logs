
<?php

if ($requestMethod === 'GET') {
    exec("systemctl list-units --type=service --no-pager", $output, $status);
    jsonResponse(['status' => $status, 'services' => $output]);
} elseif ($requestMethod === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = sanitize($data['action']);
    $service = sanitize($data['service']);

    if (!isValidAction($action) || !isWhitelistedService($service)) {
        jsonResponse(['error' => 'Invalid action or service'], 400);
    }

    exec("sudo systemctl $action $service 2>&1", $output, $status);
    jsonResponse(['status' => $status, 'output' => $output]);
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
