
<?php

// env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

if ($requestMethod === 'GET') {
    $services = explode(',', $_ENV['SERVICES']);
    foreach ($services as $service) {
        $data[] = ['name' => $service];
        $result = exec("systemctl is-active --quiet " . $service . " && echo 1 || echo 0", $output, $status);
        $data[count($data) - 1]['status'] = $result;
    }

    jsonResponse($data);
} else {
    jsonResponse(['error' => 'Method not allowed'], 405);
}
?>
