<?php

if ($server_id) {
    try {
        // Connect to the SQLite database
        $pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Query the servers table
        $stmt = $pdo->prepare('SELECT * FROM servers WHERE id = :id');
        $stmt->execute(['id' => $server_id]);
        $server = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if the server exists
        if (!$server) {
            jsonResponse(['error' => 'Server not found'], 404);
        }
        // Output the result as JSON
        jsonResponse($server);
    } catch (PDOException $e) {
        // Handle any errors
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}
else
{
    try {
        // Connect to the SQLite database
        $pdo = new PDO('sqlite:' . $_ENV['DB_PATH']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Query the servers table
        $stmt = $pdo->query('SELECT * FROM servers');
        $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        for ($i = 0; $i < count($servers); $i++) {
            if ($servers[$i]['ssl'] == 1) {
                $url = 'https://' . $servers[$i]['ip'];
            } else {
                $url = 'http://' . $servers[$i]['ip'];
            }
            if ($servers[$i]['port'] != 80) {
                $url = $url . ':' . $servers[$i]['port'];
            }
            if ($servers[$i]['path'] != '') {
                $url .= '/' . $servers[$i]['path'];
            }
            $url .= '/api/authenticate';
            echo $url . "\n";
            getServersStatus($url, $servers[$i]['token']);
            getToken($url, $servers[$i]['token']);
            exit;
            /*

            $ch = curl_init("http://www.example.com/");
            $fp = fopen("example_homepage.txt", "w");

            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);

            curl_exec($ch);
            if(curl_error($ch)) {
                fwrite($fp, curl_error($ch));
            }
            curl_close($ch);
            fclose($fp);
            $servers[$i]['status'] = 'running';*/
        }

        // Output the result as JSON
        jsonResponse($servers);
    } catch (PDOException $e) {
        // Handle any errors
        jsonResponse(['error' => 'Database error', 'message' => $e->getMessage()], 500);
    }
}
?>