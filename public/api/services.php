
<?php

if ($requestMethod === 'GET') {
    // check in the database the list of serveces and systemclt them
    $db = new PDO('sqlite:' . __DIR__ . '/../' . $_ENV['DB_PATH']);
    $stmt = $db->prepare('SELECT * FROM services;');
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $result = exec("systemctl is-active --quiet " . $row["name"] . " && echo 1 || echo 0", $output, $status);

        $data[] = $row;
        $data[count($data) - 1]['status'] = $result;
    }

    jsonResponse($data);
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
