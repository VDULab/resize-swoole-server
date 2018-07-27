<?php

/**
 * @file
 * Resize php server.
 */

require __DIR__ . '/vendor/autoload.php';

use ResizeServer\WebSocketServerHandler;
use ResizeServer\Event\MessageHandler;
use ResizeServer\Event\RequestHandler;
use Psr\Log\LogLevel;
use ResizeServer\ResizeLogger as Logger;

global $argv;

$root = $argv[1] ?? realpath(__DIR__ . '/web');
$debug = $argv[2] ?? LogLevel::WARNING;
$logger = new Logger($debug);

$handler = new WebSocketServerHandler($logger);
$msgHandler = new MessageHandler($handler);
$requestHandler = new RequestHandler($handler, $root);

$wsServer = new swoole_websocket_server("0.0.0.0", 9999);

$wsServer->set([
    'document_root' => $root,
    'enable_static_handler' => true,
    'worker_num' => 4,
]);

$wsServer->on('start', function ($server) use ($root) {
    echo "Started on http://0.0.0.0:9999 with docroot $root \n";
});

$wsServer->on('request', [$requestHandler, 'onRequest']);
$wsServer->on('handshake', [$handler, 'onHandshake']);
$wsServer->on('message', [$msgHandler, 'onMessage']);
$wsServer->on('close', [$handler, 'onClose']);

$wsServer->on('WorkerStart', function ($server, int $worker_id) use ($logger) {
    $role = ($worker_id >= $server->setting['worker_num']) ? 'Task worker' : 'Event worker';
    $logger->info("$role started #$worker_id", ['class' => 'onWorkerStart']);
});

$wsServer->on('WorkerStop', function (swoole_server $server, int $worker_id) use ($logger) {
    $logger->info("WorkerStop #$worker_id", ['class' => 'onWorkerStop']);
});

$wsServer->start();
