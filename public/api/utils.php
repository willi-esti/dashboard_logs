<?php

require __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();


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
    logError(json_encode($data));
    echo json_encode($data);
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
