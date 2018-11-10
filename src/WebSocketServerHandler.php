<?php

namespace ResizeServer;

use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Lock;
use Psr\Log\LoggerTrait;
use ResizeServer\WebSocketServerInterface;
use ResizeServer\WebSocket\ConnectionsInterface;
use ResizeServer\WebSocket\Connections;
use ResizeServer\WebSocket\Message;
use ResizeServer\Event\AutoRegisterInterface;
use ResizeServer\WebSocket\MessageHandler;
use ResizeServer\Http\RewriteRuleStorageInterface;
use ResizeServer\Http\RewriteRules;

class WebSocketServerHandler implements WebSocketServerInterface
{
    use LoggerTrait;

    /**
     * @var \ResizeServer\WebSocket\Connections
     */
    private $connections;

    /**
     * Rules storage.
     *
     * @var \ResizeServer\Http\RewriteRuleStorageInterface
     */
    public $rewriteRules;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Swoole\Server;
     */
    private $server;

    /**
     * @var \ResizeServer\Event\AutoRegisterInterface[]
     */
    private $handlers = [];

    /**
     * @var \Swoole\Lock;
     */
    private $timer;

    private $timerId = 1;

    public function __construct(\Psr\Log\LoggerInterface $logger, \Swoole\WebSocket\Server $server)
    {
        $this->logger = $logger;
        $this->server = $server;
        $this->connections = new Connections(Connections::buildTable());
        $this->rewriteRules = new RewriteRules(RewriteRules::buildTable(), $logger);
        $this->timer = new Lock();
    }

    public function onHandshake(Request $request, Response $response)
    {

        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $this->error("server: handshake FAILED with fd{$request->fd} from worker#$server->worker_id");
            $response->end();
            return false;
        }

        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        // Response must not include 'Sec-WebSocket-Protocol' header if not present in request: websocket.
        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $response->end();

        $this->confirmHandshake($request->fd, $request->header['sec-websocket-protocol']);

        $this->notice("server: handshake success with fd {$request->fd}");
        return true;
    }

    private function confirmHandshake($fd, $protocol)
    {
        $this->connections->set($fd, $protocol);
        $this->notice("Connections: {data}", ['data' => $this->connections]);
        $this->broadcastConnections($this->server);
    }

    public function onClose(Server $server, $fd)
    {
        $this->connections->remove($fd);
        $this->broadcastConnections($server);
        $this->notice("client {$fd} closed from worker#$server->worker_id");
        $this->notice("Connections: {data}", ['data' => $this->connections]);
    }

    public function broadcastConnections(Server $server)
    {
        $sender = $server ?? $this->server();
        $connections = $this->connections->list();
        $message = ['type' => 'activeConnections', 'connections' => $connections];
        $messageHandler = $this->handlers['messageHandler'];
        /* var \ResizeServer\Event\MessageHandler */
        $messageHandler->send($server, json_encode($message), 'logger');
        $this->debug("broadcastConnections: {data}", ['data' => $message]);
    }

    public function registerHandler(AutoRegisterInterface $handler)
    {
        $this->handlers[$handler::getHandlerType()] = $handler;
    }

    public function removeHandler(AutoRegisterInterface $handler)
    {
        unset($this->handlers[$handler::getHandlerType()]);
    }

    public function getHandler(string $type): ?object
    {
        return (isset($this->handlers[$type])) ? $this->handlers[$type] : null;
    }

    public function getMessageHandler(): MessageHandler
    {
        return $this->getHandler(MessageHandler::getHandlerType());
    }

    /**
     * Returns the connections registered for a type.
     *
     * @param string $protocol
     *   Filter
     * @param boolean $count
     *   If only count should be returned.
     * @return mixed
     *   An array or a number
     */
    public function getConnections($protocol = null, $count = false)
    {
        return $this->connections->getConnections($protocol, $this, $count);
    }

    public function getConnectionsCount($protocol = null)
    {
        return $this->connections->getConnectionsCount($protocol);
    }

    public function addPaths(array $paths): array
    {
        return $this->rewriteRules->addPaths($paths);
    }

    public function getRules(): array
    {
        return $this->rewriteRules->getRules();
    }

    /**
     * LooggerInterface implementation.
     * @param string $level Psr\LogLevel
     * @param mixed $data Data to display.
     * @param array $context Context data for substitutions.
     * @return void
     */
    public function log($level, $data, $context = [])
    {
        $this->logger->log($level, $data, $context);
    }

    public function getWorkerId(): int
    {
        return $this->server->worker_id;
    }

    public function togglePlay($server, $msg, $frame): bool
    {
        $sliding = $this->isSliding();
        $this->debug("sliding {sliding}", ['sliding' => ($sliding) ? 'yes' : 'no']);
        if ($sliding) {
            $this->info("togglePlay OFF: " . $this->timerId);
            $this->timer->unlock();
            return $this->notifySliding($server, false);
        }

        $id = swoole_timer_after(5000, $this->getTimerCallback());

        $this->info("togglePlay ON: $id");
        $this->timerId = $id;
        $this->timer->lock();

        return $this->notifySliding($server, true);
    }

    private function isSliding(): bool
    {
        $unlocked = $this->timer->trylock();
        if ($unlocked) {
            $this->timer->unlock();
        }
        return ! ($unlocked);
    }

    private function getTimerCallback(): callable
    {
        return function ($params = []) {
            $sliding = $this->isSliding();
            $this->debug("Executing timer, we are {not}sliding", ['not' => ($sliding) ? '' : 'not ']);
            if ($sliding) {
                $message = Message::buildNext()->toJson();
                $this->getMessageHandler()->send($this->server, $message, 'all');
                swoole_timer_after(5000, $this->getTimerCallback());
            }
        };
    }

    public function requestCurrent($server, $msg, $frame): void
    {
        $sliding = $this->isSliding();
        $this->debug("We are {not}sliding", ['not' => ($sliding) ? '' : 'not ']);
        $this->notifySliding($server, $sliding);
    }

    private function notifySliding(Server $server, bool $status): bool
    {
        /**
         * @var \ResizeServer\WebSocket\Message;
         */
        $message = Message::buildNotification('sliding', $status);
        $this->debug($message->toJson());
        $this->getMessageHandler()->send($server, $message->toJson(), $message->getDestination());
        return $status;
    }
}
