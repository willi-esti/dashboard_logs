<?php

require __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

function authenticate()
{
    $headers = getallheaders();
    if (empty($headers['Authorization'])) {
        jsonResponse(['error' => 'Unauthorized', 'message' => 'Authorization header is missing'], 401);
        exit;
    }
    
    $authHeader = explode(' ', $headers['Authorization']);
    $authType = $authHeader[0];
    if ($authType !== 'Basic') {
        jsonResponse(['error' => 'Unauthorized', 'message' => 'Authorization type is not Basic'], 401);
        exit;
    }
    list($username, $password) = explode(':', base64_decode($authHeader[1]));
    
    
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/../' . $_ENV['DB_PATH']);
        $stmt = $db->prepare('SELECT password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $hashedPassword = $stmt->fetchColumn(); 
        
        //echo password_hash($password, PASSWORD_DEFAULT);

        if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
            jsonResponse(['error' => 'Invalid credentials', 'message' => 'Username or password is incorrect'], 401);
        } else {
            $payload = [
                'iss' => $_ENV['JWT_ISSUER'],
                'iat' => time(),
                'exp' => time() + 3600, // Token expires in 1 hour
                'sub' => $username
            ];
            $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
            jsonResponse(['token' => $jwt, 'success' => 'Authentication successful']);
        }
    } catch (PDOException $e) {
        logError($e->getMessage());
        jsonResponse(['error' => 'Authentication failed'], 500);
    }
}

function getUsernameFromAuthHeader()
{
    $authHeader = explode(' ', getallheaders()['Authorization']);
    return explode(':', base64_decode($authHeader[1]))[0];
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
    // create logs directory if it doesn't exist
    if (!file_exists(__DIR__ . '/../logs')) {
        mkdir(__DIR__ . '/../logs');
    }
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

function isWhitelistedService($service)
{
    $whitelistedServices = ['nginx.service', 'mysql.service', 'apache2.service'];
    return in_array($service, $whitelistedServices, true);
}

function isValidAction($action)
{
    $validActions = ['start', 'stop', 'restart'];
    return in_array($action, $validActions, true);
}

?>
