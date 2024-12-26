<?php
require __DIR__ . '/../../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    //print_r($_ENV);
    echo $dbPath;

    if ($dbPath === false) {
        http_response_code(500);
        echo "Database path not set.";
        exit;
    }

    $db = new SQLite3($dbPath);
    
    exit;
    $result = $db->query('SELECT * FROM services');
    $services = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $services[] = $row;
    }
    exit;
    echo json_encode($services);
}
?>