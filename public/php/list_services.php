<?php
header('Content-Type: application/json');

$services = ['nginx', 'mysql', 'apache2'];
$status = [];

foreach ($services as $service) {
    $output = shell_exec("sudo systemctl is-active $service");
    $status[$service] = trim($output) === 'active' ? 'active' : 'inactive';
}

echo json_encode($status);
?>
