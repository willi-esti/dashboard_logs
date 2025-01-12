<?php

if ($server_id) {
    try {
        // Connect to the SQLite database
        $pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Query the servers table
        $stmt = $pdo->prepare('SELECT * FROM servers WHERE id = :id');
        $stmt->execute(['id' => $server_id]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the server exists
        if (!$server) {
            jsonResponse(['error' => 'Server not found'], 404);
        }

        // Output the result as JSON
        jsonResponse($server);
    } catch (PDOException $e) {
        // Handle any errors
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}
else
{
    try {
        // Connect to the SQLite database
        $pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Query the servers table
        $stmt = $pdo->query('SELECT * FROM servers');
        $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Output the result as JSON
        jsonResponse($servers);
    } catch (PDOException $e) {
        // Handle any errors
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}
?>