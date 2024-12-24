<?php

require __DIR__ . '/../../vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use React\EventLoop\Factory;
use React\Socket\Server as ReactServer;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

// enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// load the env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

class LogServer implements MessageComponentInterface {
    protected $clients;
    protected $logFiles;

    public function __construct() {
        echo "LogServer started\n";
        $this->clients = new \SplObjectStorage;
        $this->logFiles = [];
    }

    public function onOpen(ConnectionInterface $conn) {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $queryParams);

        if (!isset($queryParams['token']) || !isset($queryParams['logFile'])) {
            $conn->send(json_encode(['error' => 'Unauthorized or logFile not specified']));
            $conn->close();
            return;
        }

        $token = $queryParams['token'];
        $logFile = $queryParams['logFile'];

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $conn->user = $decoded->sub;
            $this->clients->attach($conn);
            $this->logFiles[$conn->resourceId] = [
                'file' => $logFile,
                'lastPos' => 0
            ];
            echo "New connection! ({$conn->resourceId})\n";
        } catch (Exception $e) {
            $conn->send(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // No need to handle messages from clients in this example
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->logFiles[$conn->resourceId]);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }

    public function checkForNewLogLines() {
        foreach ($this->clients as $client) {
            $logFile = $this->logFiles[$client->resourceId]['file'];
            $lastPos = $this->logFiles[$client->resourceId]['lastPos'];
            $len = 0;
            clearstatcache(true, $logFile);
            $len = filesize($logFile);
            echo "Logs : $logFile\n LastPos : $lastPos\n Len : $len\n size : ".filesize($logFile)."\n";
            // only show the 20 last lines
            if ($len < $lastPos) {
                // File was truncated or reset
                $this->logFiles[$client->resourceId]['lastPos'] = $len;
            } elseif ($len > $lastPos) {
                $f = fopen($logFile, "rb");
                echo "Opening file\n";
                if ($f === false) {
                    continue;
                }
                fseek($f, $lastPos);
                while (!feof($f)) {
                    $buffer = fread($f, 4096);
                    $client->send($buffer);
                    echo "Sending buffer\n";
                }
                $this->logFiles[$client->resourceId]['lastPos'] = ftell($f);
                echo "Closing file\n";
                fclose($f);
            }
        }
    }
}

// Create the event loop
$loop = Factory::create();

// Set up a periodic timer to check for new log lines
$logServer = new LogServer();
$loop->addPeriodicTimer(1, function() use ($logServer) {
    $logServer->checkForNewLogLines();
});

// Create the WebSocket server
$webSock = new ReactServer('0.0.0.0:8080', $loop);
$webServer = new IoServer(
    new HttpServer(
        new WsServer(
            $logServer
        )
    ),
    $webSock,
    $loop
);

// Run the server with the event loop
$loop->run();