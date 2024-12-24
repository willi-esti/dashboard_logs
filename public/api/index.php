<?php

require_once __DIR__ . '/utils.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

try {

    ob_start();
    
    switch (true) {
        case preg_match('/^api\/authenticate$/', $requestUri):
            require 'authenticate.php';
            break;
        case preg_match('/^api\/services$/', $requestUri):
            verifyToken();
            require 'services.php';
            break;
        case preg_match('/^api\/logs$/', $requestUri):
            verifyToken();
            require 'logs.php';
            break;
        /*case preg_match('/^api\/register$/', $requestUri):
            require 'register.php';
            break;*/
        default:
            jsonResponse(['error' => 'Endpoint not found'], 404);
    }

    $response = ob_get_clean();
    //logApi($requestUri, $requestMethod, $user, $_REQUEST, $response, http_response_code());
    echo $response;
} catch (Exception $e) {
    logError($e->getMessage());
    jsonResponse(['error' => 'Internal server error'], 500);
}
?>
