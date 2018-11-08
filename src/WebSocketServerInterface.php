<?php

namespace ResizeServer;

use Psr\Log\LoggerInterface;
use ResizeServer\Event\AutoRegisterInterface;
use ResizeServer\WebSocket\ConnectionsInterface;

/**
 * Extends Logger and Connections interfaces
 */
interface WebSocketServerInterface extends LoggerInterface, ConnectionsInterface
{
    public function registerHandler(AutoRegisterInterface $handler);
    public function deRegisterHandler(AutoRegisterInterface $handler);
    public function getHandler(string $type): ?object;
}
