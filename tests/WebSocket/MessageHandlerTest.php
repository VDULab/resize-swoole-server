<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use Swoole\WebSocket\Server;
use Swoole\WebSocket\Frame;
use \swoole_process;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument\Token\StringContainsToken;
use Prophecy\Argument\Token\AnyValueToken;

use ResizeServer\WebSocketServerInterface;
use ResizeServer\WebSocket\MessageHandler;
use ResizeServer\WebSocket\Message;

class MessageHandlerTest extends TestCase
{
    private static $server;

    public static function setUpBeforeClass()
    {
        self::$server = new Server('0.0.0.0');
    }

    public static function tearDownAfterClass()
    {
        self::$server = null;
    }

    public function setUp()
    {
        $this->serverHandler = $this->prophesize(WebSocketServerInterface::class);
        $this->messageHandler = new MessageHandler($this->serverHandler->reveal());
        $this->frame = $this->prophesize(Frame::class);
    }


    public function testConstructor()
    {
        $this->assertInstanceOf(MessageHandler::class, $this->messageHandler);
    }

    public function testGetHandlerType()
    {
        $type = $this->messageHandler->getHandlerType();
        $this->assertEquals('messageHandler', $type);
    }

    public function testOnMessageEmpty()
    {
        $this->messageHandler->onMessage(
            self::$server,
            $this->frame->reveal()
        );
        $this->serverHandler->log(
            new AnyValueToken(),
            new StringContainsToken('has no data'),
            []
        )->shouldHaveBeenCalled();
        $this->serverHandler->checkProphecyMethodsPredictions();
    }

    public function testOnMessageWebSocketConnection()
    {
        $message = new Message('WebSocketConnection');
        $this->frame->data = $message->toJson();
        //$this->serverHandler->getConnections(new AnyValueToken())->willReturn([0 => 'all']);
        $this->messageHandler->onMessage(
            self::$server,
            $this->frame->reveal()
        );
        $this->serverHandler->log(
            new AnyValueToken(),
            new StringContainsToken('Viewers: '),
            new AnyValueToken()
        )
        ->shouldHaveBeenCalled();
    }
}
