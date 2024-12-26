<?php

// Configuration
$logDirectory = __DIR__ . '/logs'; // Directory containing logs
$maxDays = 30; // Maximum number of days to keep logs
$logFile = $logDirectory . '/app.log'; // Current log file
$dateFormat = 'Y-m-d'; // Format for rotated log file names

// Ensure log directory exists
if (!is_dir($logDirectory)) {
    mkdir($logDirectory, 0755, true);
}

// Rotate logs
function rotateLogs($logFile, $logDirectory, $dateFormat) {
    if (file_exists($logFile)) {
        $timestamp = date($dateFormat);
        $rotatedFile = "$logDirectory/app-$timestamp.log";

        // Rename current log file
        if (rename($logFile, $rotatedFile)) {
            echo "Rotated log to $rotatedFile\n";

            // Compress the rotated log
            $gzFile = $rotatedFile . '.gz';
            $gz = gzopen($gzFile, 'wb9');
            if ($gz) {
                gzwrite($gz, file_get_contents($rotatedFile));
                gzclose($gz);

                // Delete the uncompressed log
                unlink($rotatedFile);
                echo "Compressed log to $gzFile\n";
            } else {
                echo "Failed to compress $rotatedFile\n";
            }
        } else {
            echo "Failed to rotate $logFile\n";
        }
    } else {
        echo "No log file found to rotate.\n";
    }
}

// Delete old logs
function deleteOldLogs($logDirectory, $maxDays) {
    $files = glob("$logDirectory/*");
    $now = time();

    foreach ($files as $file) {
        if (is_file($file)) {
            $fileTime = filemtime($file);
            $ageInDays = ($now - $fileTime) / (60 * 60 * 24);

            if ($ageInDays > $maxDays) {
                if (unlink($file)) {
                    echo "Deleted old log: $file\n";
                } else {
                    echo "Failed to delete $file\n";
                }
            }
        }
    }
}

// Perform log rotation
rotateLogs($logFile, $logDirectory, $dateFormat);

// Delete old logs
deleteOldLogs($logDirectory, $maxDays);

?>
