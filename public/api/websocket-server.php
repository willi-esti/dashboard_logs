<?php

require __DIR__ . '/../../vendor/autoload.php';
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// load the env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->load();



class LogServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $queryString = $conn->httpRequest->getUri()->getQuery();
        parse_str($queryString, $queryParams);

        if (!isset($queryParams['token'])) {
            $conn->send(json_encode(['error' => 'Unauthorized']));
            $conn->close();
            return;
        }

        $token = $queryParams['token'];

        try {
            $decoded = JWT::decode($token, new Key($_ENV['JWT_SECRET'], 'HS256'));
            $conn->user = $decoded->sub;
            $this->clients->attach($conn);
            echo "New connection! ({$conn->resourceId})\n";
        } catch (Exception $e) {
            $conn->send(json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]));
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message from {$from->resourceId}: $msg\n";
        //print_r($this->clients);
        // send a message to the client that sent the message
        //$from->send("You said: $msg");
        // the message will be a json containing the file name
        $data = json_decode($msg);

        // send the message to all the clients
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $client->send(json_encode([
                    'user' => $from->user,
                    'message' => $data->message
                ]));
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new LogServer()
        )
    ),
    8080
);

$server->run();