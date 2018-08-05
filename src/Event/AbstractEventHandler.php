<?php

namespace ResizeServer\Event;

use Psr\Log\LoggerTrait;
use Psr\Log\LoggerInterface;
use ResizeServer\WebSocketServerInterface;
use ResizeServer\WebSocket\ConnectionsInterface;

abstract class AbstractEventHandler implements LoggerInterface, ConnectionsInterface, AutoRegisterInterface
{
    use LoggerTrait;
    /**
     * Parent handler.
     *
     * @var \ResizeServer\WebSocketServerTnterface
     */
    protected $serverHandler;

    /**
     * Constructor.
     *
     * @param \ResizeServer\WebSocketServerHandler $handler Parent handler.
     */
    public function __construct(WebSocketServerInterface $handler)
    {
        $handler->registerHandler($this);
        $this->serverHandler = $handler;
    }

    public function log($level, $data, $context = [])
    {
        return $this->serverHandler->log($level, $data, $context);
    }

    public function getConnections($protocol = null, $logger = null)
    {
        $logger = ($logger) ?? $this;
        return $this->serverHandler->getConnections($protocol, $logger);
    }

    public function getConnectionsCount($protocol = null)
    {
        return $this->serverHandler->getConnectionsCount($protocol);
    }

    abstract public function getHandlerType();
}
