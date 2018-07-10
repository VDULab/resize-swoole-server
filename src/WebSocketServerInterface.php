<?php

namespace ResizeServer;

use Psr\Log\LoggerInterface;
use ResizeServer\WebSocket\ConnectionsInterface;

/**
 * Extends Logger and Connections interfaces
 */
interface WebSocketServerInterface extends LoggerInterface, ConnectionsInterface
{
}
