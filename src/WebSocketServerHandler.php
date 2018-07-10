<?php

namespace ResizeServer;

use Swoole\WebSocket\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Psr\Log\LoggerTrait;
use ResizeServer\WebSocketServerInterface;
use ResizeServer\WebSocket\ConnectionsInterface;
use ResizeServer\WebSocket\Connections;

class WebSocketServerHandler implements WebSocketServerInterface
{
    use LoggerTrait;

    /**
     * @var \ResizeServer\WebSocket\Connections
     */
    private $connections;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->connections = new Connections(Connections::buildTable());
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

        $key = base64_encode(sha1($request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

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
        $this->connections->set($request->fd, $request->header['sec-websocket-protocol']);

        $this->notice("server: handshake success with fd {$request->fd}");
        $this->notice("Connections: {data}", ['data' => $this->connections]);
        return true;
    }

    public function onClose(Server $server, $fd)
    {
        $this->connections->remove($fd);
        $this->notice("client {$fd} closed from worker#$server->worker_id");
        $this->notice("Connections: {data}", ['data' => $this->connections]);
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
}
