<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use Swoole\Http\Request;

use ResizeServer\Event\AbstractEventHandler;
use ResizeServer\WebSocketServerInterface;

class AbstractEventHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->serverHandler = $this->prophesize(WebSocketServerInterface::class);

        /**
         * @var \Swoole\Http\Request
         */
        $this->request = $this->prophesize(Request::class);

        /**
         * @var \ResizeServer\Event\AbstractEventHandler
         */
        $this->eventHandler = new class($this->serverHandler->reveal()) extends AbstractEventHandler {
            public static function getHandlerType(): string
            {
                return 'testHandler';
            }
        };
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(AbstractEventHandler::class, $this->eventHandler);
    }

    public function testRequestUri()
    {
        $this->request->server = ['request_uri' => 'uri'];
        $uri = $this->eventHandler->requestUri($this->request->reveal());
        $this->assertEquals('uri', $uri);
    }

    public function testLog()
    {
        $this->serverHandler->log('debug', 'message', [])->shouldBeCalled();
        $uri = $this->eventHandler->log('debug', 'message');
        $this->serverHandler->checkProphecyMethodsPredictions();
    }

    public function testGetConnections()
    {
        $this->serverHandler->getConnections(null, $this->eventHandler)->shouldBeCalled();
        $uri = $this->eventHandler->getConnections();
        $this->serverHandler->checkProphecyMethodsPredictions();
    }

    public function testGetConnectionsCount()
    {
        $this->serverHandler->getConnectionsCount(null)->shouldBeCalled();
        $uri = $this->eventHandler->getConnectionsCount();
        $this->serverHandler->checkProphecyMethodsPredictions();
    }
}
