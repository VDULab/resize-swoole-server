<?php
/**
 * MessageHandler
 */

namespace ResizeServer\Event;

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use ResizeServer\WebSocketServerInterface;
use Psr\Log\LoggerTrait;

/**
 * MessageHandler class.
 */
class MessageHandler extends AbstractEventHandler
{
    public function onMessage(Server $server, Frame $frame)
    {
        $this->info("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
        $this->handleMessage($server, $frame);
    }

    private function handleMessage(Server $server, Frame $frame)
    {
        try {
            $msg = json_decode($frame->data);
            $dest_txt = $msg->destination ?? "";
            $this->info("$msg->type to $dest_txt");
            if (isset($msg->destination) && $msg->destination !== 'server') {
                return $this->send($server, $frame->data, $msg->destination, $frame);
            }
            switch ($msg->type) {
                case 'WebSocketConnection':
                    $this->sendConnectionCount($server);
                    break;

                case 'scroll':
                case 'getNextSrc':
                    $this->send($server, $frame->data, 'manager', $frame);
                    break;

                case 'messageToAll':
                case 'notification':
                case 'showing':
                    $this->send($server, $frame->data, null, $frame);
                    break;

                default:
                    break;
            }
        } catch (Exception $e) {
            $this->error("exception received from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
            $this->error($e);
        }
    }

    private function sendConnectionCount(Server $server)
    {
        $lenght = $this->getConnectionsCount('viewer');
        $response = '{"type":"WebSocketConnection", "connections": "' . $lenght . '"}';
        $this->info("Viewers: $lenght");
        $this->send($server, $response, 'all');
    }

    private function send(Server $server, $data, $destination, $frame = null)
    {
        $destinationsCount = $this->getConnectionsCount($destination);
        $this->debug("Destinations count: $destinationsCount");
        if ($destinationsCount === 0) {
            $destination = 'manager';
        }
        $destinations = $this->getConnections($destination);
        $this->debug("Destinations: {data}", ['data' => $destinations]);
        foreach ($destinations as $fd => $protocol) {
            if (!$frame || $frame->fd != $fd) {
                $server->push($fd, $data);
                $this->info("Sending to $fd from worker#$server->worker_id");
            }
        }
    }
}
