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
    protected $logger;

    public function __construct($loop, $logger) {
        $this->clients = new \SplObjectStorage;
        $this->logFiles = [];
        $this->loop = $loop;
        $this->logger = $logger;
        $this->logger->info("LogServer started");
    }

    public function onOpen(ConnectionInterface $conn) {
        try {
            $queryString = $conn->httpRequest->getUri()->getQuery();
            parse_str($queryString, $queryParams);

            if (!isset($queryParams['token']) || !isset($queryParams['logFile'])) {
                $this->logger->warning("Unauthorized access or logFile not specified", ['resourceId' => $conn->resourceId]);
                $conn->send(json_encode(['error' => 'Unauthorized or logFile not specified']));
                $conn->close();
                return;
            }

            $token = $queryParams['token'];
            $logFile = $_ENV['LOG_DIR'] . '/' . $queryParams['logFile'];

            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $conn->user = $decoded->sub;
            $this->clients->attach($conn);
            $this->logFiles[$conn->resourceId] = [
                'file' => $logFile,
                'lastPos' => 0
            ];
            $this->logger->info("New connection", ['resourceId' => $conn->resourceId]);
        } catch (Exception $e) {
            $this->logger->error("Error on connection open", ['message' => $e->getMessage()]);
            $conn->send(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        try {
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
        } catch (Exception $e) {
            $this->logger->error("Error on message", ['message' => $e->getMessage()]);
            $from->send(json_encode(['error' => 'Internal server error']));
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        unset($this->logFiles[$conn->resourceId]);
        $this->logger->info("Connection closed", ['resourceId' => $conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->logger->error("An error has occurred", ['resourceId' => $conn->resourceId, 'message' => $e->getMessage()]);
        $conn->close();
    }

    protected function displayLinesInRange(ConnectionInterface $client, $filename, $startLine = null, $endLine = null, $lastLines = null) {
        try {
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
        } catch (Exception $e) {
            $this->logger->error("Error displaying lines in range", ['message' => $e->getMessage()]);
            $client->send(json_encode(['error' => 'Internal server error']));
        }
    }

    public function monitorFile() {
        foreach ($this->clients as $client) {
            try {
                $logFile = $this->logFiles[$client->resourceId]['file'];
                $lastPos = $this->logFiles[$client->resourceId]['lastPos'];
                clearstatcache(true, $logFile);
                $len = filesize($logFile);

                if ($len < $lastPos) {
                    $this->logFiles[$client->resourceId]['lastPos'] = $len;
                } elseif ($len > $lastPos) {
                    $f = fopen($logFile, "rb");
                    if ($f === false) {
                        continue;
                    }
                    fseek($f, $lastPos);
                    while (!feof($f)) {
                        $buffer = fread($f, 4096);
                        $client->send($buffer);
                    }
                    $this->logFiles[$client->resourceId]['lastPos'] = ftell($f);
                    fclose($f);
                }
            } catch (Exception $e) {
                $this->logger->error("Error monitoring file", ['message' => $e->getMessage()]);
                $client->send(json_encode(['error' => 'Internal server error']));
            }
        }
    }

    protected function startMonitoringFile(ConnectionInterface $client, $filename, $lastLines = null) {
        $lastLineCount = 0;

        if ($lastLines !== null) {
            $this->displayLinesInRange($client, $filename, null, null, $lastLines);
            $lastLineCount = count(file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        }

        $this->loop->addPeriodicTimer(2, function() use ($client, $filename, &$lastLineCount) {
            try {
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
                        }
                        $client->send(json_encode(['follow' => $output]));
                        $lastLineCount = $currentLineCount;
                    }
                } else {
                    $client->send(json_encode(['error' => 'File does not exist']));
                }
            } catch (Exception $e) {
                $this->logger->error("Error in periodic file monitoring", ['message' => $e->getMessage()]);
                $client->send(json_encode(['error' => 'Internal server error']));
            }
        });
    }
}

// Create the event loop
$loop = Factory::create();

// Create the WebSocket server
$logServer = new LogServer($loop, $log);
$loop->addPeriodicTimer(1, function() use ($logServer) {
    $logServer->monitorFile();
});

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