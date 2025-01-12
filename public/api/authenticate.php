<?php

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/utils.php';
use Firebase\JWT\JWT;

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
        $username = $_ENV['ADMIN_USERNAME'];
        $hashed_password = $_ENV['ADMIN_PASSWORD'];
        if ($username !== $username || !$hashed_password || !password_verify($password, $hashed_password)) {
            jsonResponse(['error' => 'Invalid credentials', 'message' => 'Username or password is incorrect'], 401);
        } else {
            genNewToken($username);
        }
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Authentication failed'], 500);
    }
}

authenticate();

