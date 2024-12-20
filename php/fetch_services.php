<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $db = new SQLite3('dashboard.db');
    $result = $db->query('SELECT * FROM services');
    $services = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $services[] = $row;
    }
    echo json_encode($services);
}
?>
