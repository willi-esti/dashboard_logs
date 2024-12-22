
<?php

require_once 'utils.php';
echo 'ok';
authenticate();

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

try {
    $user = getUsernameFromAuthHeader();
    ob_start();

    switch (true) {
        case preg_match('/^api\/services$/', $requestUri):
            require 'services.php';
            break;
        case preg_match('/^api\/logs$/', $requestUri):
            require 'logs.php';
            break;
        default:
            jsonResponse(['error' => 'Endpoint not found'], 404);
    }

    $response = ob_get_clean();
    logApi($requestUri, $requestMethod, $user, $_REQUEST, $response, http_response_code());
    echo $response;
} catch (Exception $e) {
    logError($e->getMessage());
    jsonResponse(['error' => 'Internal server error'], 500);
}
?>
