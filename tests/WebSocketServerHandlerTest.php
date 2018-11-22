<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use Psr\Log\LoggerInterface;

use ResizeServer\WebSocketServerHandler;
use ResizeServer\WebSocket\MessageHandler;
use ResizeServer\Http\RequestHandler;

class WebSocketServerHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->serverProphecy = $this->prophesize(\swoole_websocket_server::class);
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->serverHandler = new WebSocketServerHandler(
            $this->logger->reveal(),
            $this->serverProphecy->reveal()
        );
        // $this->messageHandler = $this->prophesize(MessageHandler::class);
        // $this->messageHandler->willBeConstructedWith([$this->serverHandler]);
        // $this->messageHandler->reveal();
        $this->messageHandler = new MessageHandler($this->serverHandler);
        $requestHandler = new RequestHandler($this->serverHandler, '/');
    }

    public function tearDown()
    {
        $this->serverProphecy->reveal()->shutdown();
    }

    public function testTogglePlay()
    {
        $result = $this->serverHandler->togglePlay($this->serverProphecy->reveal());
        $this->assertInternalType('bool', $result);
    }
}
