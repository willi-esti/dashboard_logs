<?php

require_once __DIR__ . '/utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$logDir = __DIR__ . '/../../logs';
if (!file_exists($logDir)) {
    mkdir($logDir);
}
// error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL); // Report all errors
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', $logDir . '/server-dashboard-php-error.log'); // Specify error log file

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

try {

    ob_start();
    
    switch (true) {
        case preg_match('/api\/authenticate$/', $requestUri):
            require 'authenticate.php';
            break;
        case preg_match('/api\/services$/', $requestUri):
            verifyToken();
            require 'services.php';
            break;
        case preg_match('/api\/reports$/', $requestUri):
            verifyToken();
            require 'reports.php';
            break;
        case preg_match('/api\/logs$/', $requestUri):
            verifyToken();
            require 'logs.php';
            break;
        case preg_match('/api\/logs\/download/', $requestUri):
            verifyToken();
            require 'download.php';
            break;
        case preg_match('/api\/info$/', $requestUri):
            verifyToken();
            jsonResponse(['mode' => $_ENV['MODE']], 200);
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
