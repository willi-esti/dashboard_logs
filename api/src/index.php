<?php

require_once __DIR__ . '/utils.php';

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

// Allow from any origin (use '*' for development or trusted domains in production)
header("Access-Control-Allow-Origin: *");

// Allow specific methods
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Allow specific headers
header("Access-Control-Allow-Headers: Authorization, Content-Type");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
            if (isSELinuxActive() && $_ENV['SELINUX'] !== 'true') {
                jsonResponse(['error' => 'SELinux is active, set the SELINUX environment variable to true and rerun the installation script with --selinux.'], 500);
            }
            jsonResponse([
                'version' => $_ENV['VERSION'],
                'server_id' => $_ENV['SERVER_ID'],
                'base_url' => $_ENV['BASE_URL'],
                'selinux' => $_ENV['SELINUX'] === 'true' ? true : false
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
