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
                $sent = $this->_send($server, $frame->data, $msg->destination, $frame);
                if (! in_array($msg->type, $forcedHandled)) {
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
                    $this->_send($server, $frame->data, 'manager', $frame);
                    break;
                case 'scanDir':
                    $this->localDir($server, $msg, $frame);
                    break;
                case 'showing':
                case 'messageToAll':
                case 'notification':
                    $this->_send($server, $frame->data, null, $frame);
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
        $this->_send($server, $response, 'all');
    }

    public function send(Server $server, $data, $destination, $sourceFrame = null)
    {
        return $this->_send($server, $data, $destination, $sourceFrame);
    }

    // phpcs:disable PSR2.Methods.MethodDeclaration.Underscore
    private function _send(Server $server, $data, $destination, $frame = null)
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
    }

    private function scanDir($path, $full = true): array
    {
        $ls = [];
        $toScan = [];
        $this->info("Opening $path");
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (! $this->isBlackListed($entry)) {
                    $child_path = $path . DIRECTORY_SEPARATOR . $entry;
                    if (is_dir($child_path)) {
                        $toScan[$child_path] = 0;
                    } elseif ($this->isImage($entry)) {
                        $ls[] = $child_path;
                    }
                }
            }
            closedir($handle);
            // $this->debug("Closing $path : {toScan} {ls}", ['ls' => $ls, 'toScan' => $toScan]);
            if (! empty($toScan)) {
                foreach ($toScan as $child_path => &$count) {
                    if ($full) {
                        $this->debug("Scanning $child_path");
                        $child = $this->scanDir($child_path);
                        $ls = array_merge($ls, $child);
                    } else {
                        $count = $count + $this->countDir($child_path);
                        $this->debug("$child_path has $count items");
                    }
                }
            }
        }

        return ($full) ? $ls : $toScan;
    }

    private function countDir($path): int
    {
        $this->debug("Counting $path");
        $count = 0;
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (! $this->isBlackListed($entry)) {
                    $child_path = $path . DIRECTORY_SEPARATOR . $entry;
                    if ($this->isImage($entry)) {
                        $count++;
                    }
                }
            }
            closedir($handle);
        }
        return $count;
    }

    public function getBlackList(): array
    {
        return [
            '',
            '.',
            '..',
        ];
    }

    public function isBlackListed(string $entry): bool
    {
        if (in_array($entry, $this->getBlackList())) {
            // $this->debug("$entry is blacklisted.");
            return true;
        }
        return false;
    }

    private function isImage(string $entry): bool
    {
        $length = strlen($entry);
        foreach (['.jpg', '.jpeg'] as $extension) {
            if (stripos($entry, $extension) == $length - strlen($extension)) {
                return true;
            }
            // $this->debug("No match $extension $entry");
        }
        return false;
    }

    const MAX_BATCH_SIZE = 50;
    const LOCAL_DIR_RULE_NAME = 'localDirRewriteRule';

    private function localDir(Server $server, $msg, $frame): void
    {
        if (! isset($msg->full) || (isset($msg->full) && $msg->full === false)) {
            $response = $this->scanDir($msg->path, false);
            $this->sendScanResponse($server, $response);
            return;
        }
        $ls = $this->scanDir($msg->path);
        $rewritePaths = $ls;
        $count = count($ls);
        $this->debug('Found: {ls}', ['ls' => $count]);
        $this->serverHandler->addPaths($ls);

        while ($count > self::MAX_BATCH_SIZE) {
            $batch = array_splice($ls, 0, self::MAX_BATCH_SIZE);
            $count = count($ls);
            $this->debug("Sent " . self::MAX_BATCH_SIZE . ", remaining: {ls}", ['ls' => $count]);
            $this->sendUrls($server, $batch, $frame);
        }
        $this->debug("Sent remaining: {ls}", ['ls' => $count]);
        $this->sendUrls($server, $ls, $frame);
    }

    private function sendUrls(Server $server, $items, $frame): void
    {
        $message = new \stdClass();
        $message->type = 'imageUrls';
        $message->images = $items;
        $message->destination = 'viewers';
        $data = json_encode($message);
        $this->_send($server, $data, $message->destination, $frame);
    }

    private function sendScanResponse(Server $server, array $items): void
    {
        $message = new \stdClass();
        $message->type = 'scanResponse';
        $message->dirs = $items;
        $data = json_encode($message);
        $this->_send($server, $data, 'logger');
    }
}
