<?php
$logDir = '/var/log/apache2'; // Directory where log files are stored
//$filename = basename($_GET['file']);
$filename ='error.log';
$filePath = "$logDir/$filename";
$file = $filePath;
$lastpos = 0;
while (true) {
    usleep(300000); //0.3 s
    clearstatcache(false, $file);
    $len = filesize($file);
    if ($len < $lastpos) {
        //file deleted or reset
        $lastpos = $len;
    }
    elseif ($len > $lastpos) {
        $f = fopen($file, "rb");
        if ($f === false)
            die();
        fseek($f, $lastpos);
        while (!feof($f)) {
            $buffer = fread($f, 4096);
            echo $buffer;
            flush();
        }
        $lastpos = ftell($f);
        fclose($f);
    }
}
/*
if (file_exists($filePath)) {
    header('Content-Type: text/plain');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    $handle = fopen($filePath, 'r');
    //fseek($handle, 0, SEEK_END); // Start at the end of the file
    while (true) {
        //echo str_repeat(' ', 1024); // Send some empty data to prevent the connection from timing out
        $line = fgets($handle);
        if ($line !== false) {
            echo $line;
            //flush();
        } else {
            sleep(1); // Wait for new data
            //clearstatcache(); // Clear cache to check for file updates
        }
    }

    fclose($handle);
} else {
    http_response_code(404);
    echo "Log file not found.";
}*/
?>
