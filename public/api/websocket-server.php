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
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// error
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();

// Create a log channel
$log = new Logger('websocket_server');
$log->pushHandler(new StreamHandler(__DIR__ . '/../../logs/server.log', Logger::DEBUG));

class LogServer implements MessageComponentInterface {
    protected $clients;
    protected $logFiles;
    protected $loop;
    protected $log;

    public function __construct($loop, $log) {
        $this->log = $log;
        $this->log->info("LogServer started");
        $this->clients = new \SplObjectStorage;
        $this->logFiles = [];
        $this->loop = $loop;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->log->info("New connection attempt", ['resourceId' => $conn->resourceId]);
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $queryParams);

        if (!isset($queryParams['token']) || !isset($queryParams['logFile'])) {
            $this->log->warning("Unauthorized access or logFile not specified", ['resourceId' => $conn->resourceId]);
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
            $this->log->info("Connection authorized", ['resourceId' => $conn->resourceId, 'user' => $conn->user]);
        } catch (Exception $e) {
            $this->log->error("Unauthorized access", ['resourceId' => $conn->resourceId, 'message' => $e->getMessage()]);
            $conn->send(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $this->log->info("Message received", ['resourceId' => $from->resourceId, 'message' => $msg]);
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
        $this->log->info("Connection closed", ['resourceId' => $conn->resourceId]);
        $this->clients->detach($conn);
        unset($this->logFiles[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->log->error("An error has occurred", ['resourceId' => $conn->resourceId, 'message' => $e->getMessage()]);
        $conn->close();
    }

    protected function displayLinesInRange(ConnectionInterface $client, $filename, $startLine = null, $endLine = null, $lastLines = null) {
        $this->log->info("Displaying lines in range", ['filename' => $filename, 'startLine' => $startLine, 'endLine' => $endLine, 'lastLines' => $lastLines]);
        if (file_exists($filename)) {
            $lines = file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $totalLines = count($lines);

            if ($lastLines !== null) {
                if ($lastLines < 1 || $lastLines > $totalLines) {
                    $lastLines = $totalLines;
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

    protected function startMonitoringFile(ConnectionInterface $client, $filename, $lastLines = null) {
        $this->log->info("Starting to monitor file", ['filename' => $filename, 'lastLines' => $lastLines]);
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
            new LogServer($loop, $log)
        )
    ),
    $webSock,
    $loop
);

// Run the server with the event loop
$loop->run();