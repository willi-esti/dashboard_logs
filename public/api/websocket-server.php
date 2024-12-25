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

// error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

class LogServer implements MessageComponentInterface {
    protected $clients;
    protected $logFiles;
    protected $loop;

    public function __construct($loop) {
        echo "LogServer started\n";
        $this->clients = new \SplObjectStorage;
        $this->logFiles = [];
        $this->loop = $loop;
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
        $logFile = $_ENV['LOG_DIR'] . '/' . $queryParams['logFile'];
        //$queryParams['logFile'];

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
        $data = json_decode($msg, true);
        if (isset($data['action'])) {
            switch ($data['action']) {
                case 'follow':
                    $this->startMonitoringFile($from, $this->logFiles[$from->resourceId]['file'], $data['lastLines'] ?? null);
                    break;
                case 'getLogs':
                    $this->displayLinesInRange($from, $this->logFiles[$from->resourceId]['file'], $data['startLine'] ?? null, $data['endLine'] ?? null, $data['lastLines'] ?? null);
                    break;
                default:
                    $from->send(json_encode(['error' => 'Invalid action']));
            }
        } else {
            $from->send(json_encode(['error' => 'Action not specified']));
        }
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

    protected function displayLinesInRange(ConnectionInterface $client, $filename, $startLine = null, $endLine = null, $lastLines = null) {
        if (file_exists($filename)) {
            $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $totalLines = count($lines);

            if ($lastLines !== null) {
                if ($lastLines < 1 || $lastLines > $totalLines) {
                    $client->send(json_encode(['error' => 'Invalid number of last lines']));
                    return;
                }
                $startLine = $totalLines - $lastLines + 1;
                $endLine = $totalLines;
            }

            if ($startLine < 1 || $endLine > $totalLines || $startLine > $endLine) {
                $client->send(json_encode(['error' => 'Invalid range']));
                return;
            }

            $output = [];
            for ($i = $startLine - 1; $i < $endLine; $i++) {
                $output[] = [
                    'line' => ($i + 1),
                    'content' => $lines[$i]
                ];
            }
            $client->send(json_encode(['getLogs' => $output]));
        } else {
            $client->send(json_encode(['error' => 'File does not exist']));
        }
    }

    public function monitorFile($filename, $interval = 2, $lastLines = null) {
        $lastLineCount = 0;

        if ($lastLines !== null) {
            $this->displayLinesInRange(null, $filename, null, null, $lastLines);
            $lastLineCount = count(file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }

        while (true) {
            if (file_exists($filename)) {
                $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $currentLineCount = count($lines);

                if ($currentLineCount > $lastLineCount) {
                    for ($i = $lastLineCount; $i < $currentLineCount; $i++) {
                        echo ($i + 1) . ": " . $lines[$i] . "\n";
                    }
                    $lastLineCount = $currentLineCount;
                }
            } else {
                echo "Error: File does not exist.\n";
            }

            sleep($interval);
        }
    }

    protected function startMonitoringFile(ConnectionInterface $client, $filename, $lastLines = null) {
        echo "Starting monitoring file\n";
        $lastLineCount = 0;

        if ($lastLines !== null) {
            $this->displayLinesInRange($client, $filename, null, null, $lastLines);
            $lastLineCount = count(file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }

        $this->loop->addPeriodicTimer(2, function() use ($client, $filename, &$lastLineCount) {
            if (file_exists($filename)) {
                $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $currentLineCount = count($lines);

                if ($currentLineCount > $lastLineCount) {
                    $output = [];
                    for ($i = $lastLineCount; $i < $currentLineCount; $i++) {
                        $output[] = [
                            'line' => ($i + 1),
                            'content' => $lines[$i]
                        ];
                        //$output[] = ($i + 1) . ": " . $lines[$i];
                    }
                    $client->send(json_encode(['follow' => $output]));
                    $lastLineCount = $currentLineCount;
                }
            } else {
                $client->send(json_encode(['error' => 'File does not exist']));
            }
        });
    }
}

// Create the event loop
$loop = Factory::create();


$webSock = new ReactServer('0.0.0.0:8080', $loop);
$webServer = new IoServer(
    new HttpServer(
        new WsServer(
            new LogServer($loop)
        )
    ),
    $webSock,
    $loop
);

// Run the server with the event loop
$loop->run();