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
        // $PDO dslite
        echo $_ENV['DB_PATH'];
        $dsn = 'sqlite:' . $_ENV['DB_PATH'];
        $PDO = new PDO($dsn);
        $PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $PDO->prepare('SELECT username, password FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        $hashed_password = $user['password'];
        if (!$user || !password_verify($password, $hashed_password)) {
            jsonResponse(['error' => 'Invalid credentials', 'message' => 'Username or password is incorrect'], 401);
        } else {
            genNewToken($username);
        }
    } catch (PDOException $e) {
        logError($e->getMessage());
        jsonResponse(['error' => 'Authentication failed'], 500);
    }
}

authenticate();
