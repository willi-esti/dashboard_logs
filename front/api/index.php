<?php

require_once __DIR__ . '/utils.php';

$LOG_DIR = $_ENV['LOG_DIR'];
if (!file_exists($LOG_DIR)) {
    mkdir($LOG_DIR);
}
// error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL); // Report all errors
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', $LOG_DIR . '/server-dashboard-php-error.log'); // Specify error log file

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

try {

    ob_start();
    
    switch (true) {
        case preg_match('/api\/authenticate$/', $requestUri):
            require 'authenticate.php';
            break;
        case preg_match('/api\/servers$/', $requestUri):
            verifyToken();
            require 'servers.php';
            break;
        case preg_match('/api\/servers\/(.+)$/', $requestUri, $matches):
            verifyToken();
            $server_id = $matches[1];
            require 'servers.php';
            break;
        case preg_match('/api\/info$/', $requestUri):
            verifyToken();
            jsonResponse([
                'version' => $_ENV['VERSION'],
                'base_url' => $_ENV['BASE_URL']
            ], 200);
            break;
        default:
            jsonResponse(['error' => 'Endpoint not found'], 404);
    }

    $response = ob_get_clean();
    echo $response;
} catch (Exception $e) {
    logError($e->getMessage());
    jsonResponse(['error' => 'Internal server error'], 500);
}
?>
