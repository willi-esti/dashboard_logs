<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $db = new SQLite3('dashboard.db');
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
    $stmt = $db->prepare('INSERT INTO services (name) VALUES (:name)');
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->execute();
    echo json_encode(['status' => 'success', 'message' => 'Service added.']);
}
?>
