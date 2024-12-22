<?php

require __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

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
        return $decoded;
    } catch (Exception $e) {
        jsonResponse(['error' => 'Unauthorized', 'message' => $e->getMessage()], 401);
    }
}
?>
