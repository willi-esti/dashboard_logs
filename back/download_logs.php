<?php
$logDir = '/var/log/apache2';
//$filename = basename($_GET['file']); // Sanitize input
$filename = "access.log";
$filePath = "$logDir/$filename";

if (file_exists($filePath)) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    readfile($filePath);
    //echo $filePath;
} else {
    http_response_code(404);
}
?>
