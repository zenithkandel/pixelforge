<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/ChatServer.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

echo "PixelForge WebSocket Server\n";
echo "===========================\n";

$port = 8080;

if (isset($argv[1])) {
    $port = intval($argv[1]);
}

echo "Starting on port {$port}...\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChatServer()
        )
    ),
    $port
);

echo "Server running. Press Ctrl+C to stop.\n";
$server->run();
