<?php
require_once 'utils.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = sanitize($data['username']);
    $password = password_hash($data['password'], PASSWORD_DEFAULT);

    // Validate username and password
    if (strlen($username) < 3 || strlen($data['password']) < 6) {
        jsonResponse(['error' => 'Invalid username or password'], 400);
    }

    try {
        $db = new PDO('sqlite:../config/database.db');
        $stmt = $db->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $stmt->execute([$username, $password]);
        jsonResponse(['success' => true]);
    } catch (PDOException $e) {
        logError($e->getMessage());
        jsonResponse(['error' => 'Registration failed'], 500);
    }
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
