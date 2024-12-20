<?php
$logDir = '/var/log/apache2/';
$files = array_diff(scandir($logDir), ['.', '..']);
echo json_encode($files);
?>
