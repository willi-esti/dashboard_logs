<?php

require __DIR__ . '/../../vendor/autoload.php';
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
        $db = new PDO('sqlite:' . __DIR__ . '/../' . $_ENV['DB_PATH']);
        $stmt = $db->prepare('SELECT password FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $hashedPassword = $stmt->fetchColumn(); 
        
        //echo password_hash($password, PASSWORD_DEFAULT);

        if (!$hashedPassword || !password_verify($password, $hashedPassword)) {
            jsonResponse(['error' => 'Invalid credentials', 'message' => 'Username or password is incorrect'], 401);
        } else {
            $payload = [
                'iss' => $_ENV['JWT_ISSUER'],
                'iat' => time(),
                'exp' => time() + 3600, // Token expires in 1 hour
                'sub' => $username
            ];
            $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');
            jsonResponse(['token' => $jwt, 'success' => 'Authentication successful']);
        }
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Authentication failed'], 500);
    }
}

authenticate();

