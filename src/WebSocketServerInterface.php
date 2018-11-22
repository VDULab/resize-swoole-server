<?php

namespace ResizeServer;

use Psr\Log\LoggerInterface;
use ResizeServer\WebSocket\ConnectionsInterface;

use ResizeServer\Event\AutoRegisterInterface;
use ResizeServer\Http\RewriteRuleStorageInterface;

/**
 * Extends Logger and Connections interfaces
 */
interface WebSocketServerInterface extends LoggerInterface, ConnectionsInterface, RewriteRuleStorageInterface
{
    public function registerHandler(AutoRegisterInterface $handler);
    public function removeHandler(AutoRegisterInterface $handler);
    public function getHandler(string $type): ?object;
    public function togglePlay(\swoole_websocket_server $server): bool;
    public function requestCurrent(\swoole_websocket_server $server): void;
    public function getWorkerId(): ?int;
}
