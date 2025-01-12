<?php

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
    if ($authType !== 'Bearer') {
        jsonResponse(['error' => 'Unauthorized', 'message' => 'Authorization type is not Basic'], 401);
        exit;
    }
    $token = $authHeader[1];    
    
    try {
        $token_api = $_ENV['TOKEN_API'];
        if (empty($token_api)) {
            jsonResponse(['error' => 'Unauthorized', 'message' => 'Token API is not set'], 401);
            exit;
        }
        if ($token !== $token_api) {
            jsonResponse(['error' => 'Unauthorized', 'message' => 'Invalid token'], 401);
            exit;
        }
        else {
            genNewToken('admin');
        }
    } catch (Exception $e) {
        jsonResponse(['error' => 'Authentication failed', 'message' => $e->getMessage()], 500);
    }
}

authenticate();

