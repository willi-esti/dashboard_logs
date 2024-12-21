
<?php

$config = require __DIR__ . '/../config/config.php';

function authenticate()
{
    $headers = getallheaders();
    if (empty($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    list($username, $password) = explode(':', base64_decode(substr($headers['Authorization'], 6)));

    try {
        $db = new PDO('sqlite:../config/database.db');
        $stmt = $db->prepare('SELECT password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $hashedPassword = $stmt->fetchColumn();

        if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
    } catch (PDOException $e) {
        logError($e->getMessage());
        jsonResponse(['error' => 'Authentication failed'], 500);
    }
}

function sanitize($data)
{
    return htmlspecialchars(strip_tags(trim($data)));
}

function jsonResponse($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function logError($message)
{
    error_log("[" . date('Y-m-d H:i:s') . "] $message\n", 3, __DIR__ . '/../logs/errors.log');
}

function logApi($endpoint, $method, $user, $request, $response, $statusCode)
{
    try {
        $db = new PDO('sqlite:../config/database.db');
        $stmt = $db->prepare('
            INSERT INTO api_logs (endpoint, method, user, request, response, status_code)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$endpoint, $method, $user, json_encode($request), json_encode($response), $statusCode]);
    } catch (PDOException $e) {
        logError('API logging failed: ' . $e->getMessage());
    }
}
?>
