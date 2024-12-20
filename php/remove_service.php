<?php
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str(file_get_contents('php://input'), $data);
    if (isset($data['id'])) {
        $db = new SQLite3('dashboard.db');
        $stmt = $db->prepare('DELETE FROM services WHERE id = :id');
        $stmt->bindValue(':id', intval($data['id']), SQLITE3_INTEGER);
        $stmt->execute();
        echo json_encode(['status' => 'success', 'message' => 'Service removed.']);
    }
}
?>
