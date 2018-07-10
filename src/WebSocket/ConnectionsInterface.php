<?php

namespace ResizeServer\WebSocket;

/**
 * Websocket connections contol interface.
 */
interface ConnectionsInterface
{
    /**
     * Get current connected clients.
     * @param string $protocol
     *   Optional filter
     * @return array
     *   An array of client id => protocol.
     */
    public function getConnections($protocol = null, $logger = null);

    /**
     * Count current connected clients.
     * @param string $protocol
     *   Optional filter
     * @return int
     *   Number of client connected for the protocol.
     */
    public function getConnectionsCount($protocol = null);
}
