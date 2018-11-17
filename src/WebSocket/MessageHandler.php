<?php
/**
 * MessageHandler
 */

namespace ResizeServer\WebSocket;

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Psr\Log\LoggerTrait;

use ResizeServer\WebSocketServerInterface;
use ResizeServer\Event\AbstractEventHandler;
use ResizeServer\Http\RequestHandler;
use ResizeServer\Http\RewriteRuleInterface;
use ResizeServer\Redis\Logger as MessageLogger;

/**
 * MessageHandler class.
 */
class MessageHandler extends AbstractEventHandler
{
    public static function getHandlerType(): string
    {
        return 'messageHandler';
    }

    public function onMessage(Server $server, Frame $frame)
    {
        $this->debug("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
        $this->handleMessage($server, $frame);
    }

    private function handleMessage(Server $server, Frame $frame)
    {
        try {
            $msg = json_decode($frame->data);
            $dest_txt = $msg->destination ?? "";
            $silent = false;
            if (isset($msg->destination) && $msg->destination !== 'server') {
                $sent = $this->send($server, $frame->data, $msg->destination, $frame);
                if ($sent && ! in_array($msg->type, $forcedHandled)) {
                    return $sent;
                }
            }
            switch ($msg->type) {
                case 'WebSocketConnection':
                    $this->sendConnectionCount($server);
                    break;
                case 'scroll':
                    $silent = true;
                    // no break
                case 'getNextSrc':
                    $this->send($server, $frame->data, 'manager', $frame);
                    break;
                case 'scanDir':
                    $this->scanDir($server, $msg, $frame);
                    break;
                case 'togglePlay':
                case 'requestCurrent':
                    $name = $msg->type;
                    $this->serverHandler->$name($server, $msg, $frame);
                    break;
                case 'showing':
                case 'messageToAll':
                case 'notification':
                    $this->send($server, $frame->data, null, $frame);
                    break;

                default:
                    break;
            }
            if (! $silent) {
                $this->info("$msg->type to $dest_txt");
            }
        } catch (Exception $e) {
            $this->error("exception received from {$frame->fd}:{$frame->data},"
                . "opcode:{$frame->opcode},fin:{$frame->finish}");
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

    public function send(Server $server, $data, $destination, $sourceFrame = null): bool
    {
        return $this->_send($server, $data, $destination, $sourceFrame);
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    private function _send(Server $server, $data, $destination, $frame = null): bool
    {
        $destinationsCount = $this->getConnectionsCount($destination);
        $this->debug("Destinations count: $destinationsCount");
        if ($destinationsCount === 0) {
            $destination = 'manager';
        }
        $destinations = $this->getConnections($destination);
        $this->debug("Destinations: {data}", ['data' => $destinations]);
        foreach ($destinations as $fd => $protocol) {
            if (! $frame || $frame->fd != $fd) {
                $server->push($fd, $data);
                $this->debug("Sending to $fd from worker#$server->worker_id");
            }
        }
        return $destinationsCount > 0;
    }

    private function scanDir(Server $server, $msg, $frame): void
    {
        $server->task($msg);
    }

    public function relayMessage(Server $taskServer, $data, int $sourceId = null): void
    {
        if (! is_string($data)) {
            $data = json_encode($data);
        }
        $this->send($taskServer, $data, null);
    }

    public function onFinish(Server $taskServer, int $taskId, string $data): void
    {
        $this->send($taskServer, $data, null);
        $this->info("finished #$taskId", ['class' => 'onFinish']);
    }

    public function onPipeMessage(Server $taskServer, int $workerId, $data): void
    {
        $this->info("#$workerId sent a message", ['class' => 'onPipeMesssage']);
        $this->relayMessage($taskServer, $data, $workerId);
    }
}
