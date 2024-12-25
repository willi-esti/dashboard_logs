<?php

require __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$LOG_DIR = __DIR__ . '/../' . $_ENV['LOG_DIR'];

function genToken($username)
{
    $payload = [
        'iss' => $_ENV['JWT_ISSUER'],
        'iat' => time(),
        'exp' => time() + 3600, // Token expires in 1h
        'sub' => $username
    ];
    return JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
}

function genNewToken($username)
{
    $token = genToken($username);
    header('Authorization: Bearer ' . $token);
}


function verifyToken()
{
    $headers = getallheaders();
    if (empty($headers['Authorization'])) {
        jsonResponse(['error' => 'Unauthorized', 'message' => 'Authorization header is missing'], 401);
    }

    $authHeader = explode(' ', $headers['Authorization']);
    $jwt = $authHeader[1];

    try {
        $decoded = JWT::decode($jwt, new Key($_ENV['JWT_SECRET'], 'HS256'));
        $currentTime = time();
        $remainingTime = $decoded->exp - $currentTime;

        $newJwt = null;
        // If the token is about to expire in less than 5 minutes, issue a new token
        if ($remainingTime < 300) {
            genNewToken($decoded->sub);
            //header('Authorization: Bearer ' . $newJwt);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
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

function jsonResponse($data, $status = 200, $log = true)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    if ($log) {
        logError(json_encode($data));
    }
    exit;
}

function logError($message)
{
    // create logs directory if it doesn't exist
    $logDir = __DIR__ . '/../../logs';
    if (!file_exists($logDir)) {
        mkdir($logDir);
    }
    error_log("[" . date('Y-m-d H:i:s') . "] $message\n", 3, $logDir . '/errors.log');
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
