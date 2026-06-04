<?php
$autoloadPaths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
];

$autoloaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require $path;
        $autoloaded = true;
        break;
    }
}

if (!$autoloaded) {
    echo "ERROR: Composer autoloader not found.\n\n";
    echo "Please install Ratchet first:\n";
    echo "  cd " . dirname(__DIR__) . "\n";
    echo "  composer require cboden/ratchet\n\n";
    echo "Or install globally:\n";
    echo "  composer global require cboden/ratchet\n";
    exit(1);
}

if (!class_exists('Ratchet\Server\IoServer')) {
    echo "ERROR: Ratchet library not found.\n\n";
    echo "Please install it:\n";
    echo "  cd " . dirname(__DIR__) . "\n";
    echo "  composer require cboden/ratchet\n";
    exit(1);
}

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
