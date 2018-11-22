<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use \swoole_websocket_server as Server;
use \swoole_websocket_frame as Frame;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Argument\Token\StringContainsToken;
use Prophecy\Argument\Token\AnyValueToken;
use Psr\Log\LogLevel;

use ResizeServer\WebSocketServerInterface;
use ResizeServer\WebSocket\MessageHandler;
use ResizeServer\WebSocket\Message;

class MessageHandlerTest extends TestCase
{
    public function setUp()
    {
        $this->serverProhecy = $this->prophesize(Server::class);
        $this->server = $this->serverProhecy->reveal();
        $this->serverHandler = $this->prophesize(WebSocketServerInterface::class);
        $this->frame = $this->prophesize(Frame::class);
        $this->messageHandler = new MessageHandler($this->serverHandler->reveal());
    }

    private function setUpFrame(string $type): Message
    {
        $message = new Message($type);
        $this->frame->data = $message->toJson();
        return $message;
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
            $this->server,
            $this->frame->reveal()
        );
        $this->serverHandler->log(
            LogLevel::ERROR,
            new AnyValueToken,
            new AnyValueToken
        )->shouldHaveBeenCalled();
        $this->serverHandler->checkProphecyMethodsPredictions();
    }

    public function testOnMessageWebSocketConnection()
    {
        $message = new Message('WebSocketConnection');
        $this->frame->data = $message->toJson();
        $this->messageHandler->onMessage(
            $this->server,
            $this->frame->reveal()
        );
        $this->serverHandler->log(
            new AnyValueToken(),
            new StringContainsToken('Viewers: '),
            new AnyValueToken()
        )
        ->shouldHaveBeenCalled();
    }

    public function testOnMessageScroll()
    {
        $message = $this->setUpFrame('scroll');
        $this->messageHandler->onMessage(
            $this->server,
            $this->frame->reveal()
        );
        $this->serverHandler->log(
            'info',
            new StringContainsToken("$message->type to"),
            new AnyValueToken()
        )
        ->shouldNotHaveBeenCalled();
    }

    public function testOnMessageScanDir()
    {
        $message = $this->setUpFrame('scanDir');
        $this->serverProhecy->task(
            $message->toStdClass()
        )->shouldBeCalledOnce();
        $this->messageHandler->onMessage(
            $this->serverProhecy->reveal(),
            $this->frame->reveal()
        );
        $this->serverProhecy->checkProphecyMethodsPredictions();
    }

    public function testOnMessageTogglePlay()
    {
        $message = $this->setUpFrame('togglePlay');
        $frame = $this->frame->reveal();
        $this->messageHandler->onMessage(
            $this->server,
            $frame
        );
        $this->serverHandler->togglePlay(
            $this->server
        )->shouldHaveBeenCalledOnce();
    }

    public function testOnMessageRequestCurrent()
    {
        $message = $this->setUpFrame('requestCurrent');
        $frame = $this->frame->reveal();
        $this->messageHandler->onMessage(
            $this->server,
            $frame
        );
        $this->serverHandler->requestCurrent(
            $this->server
        )->shouldHaveBeenCalledOnce();
    }

    public function testOnMessageShowing()
    {
        $any = new AnyValueToken();

        $this->serverHandler->log($any, $any, $any)->shouldBeCalled();
        $this->serverHandler->getConnectionsCount($any)->shouldBeCalled();
        $this->serverHandler->getConnections(
            null,
            $this->messageHandler
        )->willReturn([10 => 'manager']);

        $message = $this->setUpFrame('showing');
        $this->messageHandler->onMessage(
            $this->server,
            $this->frame->reveal()
        );
        $this->serverProhecy->push(
            10,
            $message->toJson()
        )->shouldHaveBeenCalledOnce();
    }

    public function testOnMessageToManager()
    {
        $any = new AnyValueToken();

        $destination = 'manager';

        $this->serverHandler->log($any, $any, $any)->shouldBeCalled();
        $this->serverHandler->getConnectionsCount('manager')->willReturn(1);
        $this->serverHandler->getConnections(
            null,
            $this->messageHandler
        )->willReturn([
            10 => 'manager',
            2  => 'viewer'
        ]);
        $this->serverHandler->getConnections(
            'manager',
            $this->messageHandler
        )->willReturn([
            10 => 'manager'
        ]);

        $message = new Message('anymessage');
        $message->destination = $destination;
        $this->frame->data = $message->toJson();
        $this->frame->fd = 2;
        $this->messageHandler->onMessage(
            $this->server,
            $this->frame->reveal()
        );
        $this->serverProhecy->push(
            10,
            $message->toJson()
        )->shouldHaveBeenCalledOnce();
    }

    public function testOnPipeMessageWithString()
    {
        $any = new AnyValueToken;
        $this->serverHandler->log($any, $any, $any)->shouldBeCalled();
        $this->serverHandler->getConnectionsCount(null)->willReturn(2);
        $this->serverHandler->getConnections(
            null,
            $this->messageHandler
        )->willReturn([
            10 => 'manager',
            2  => 'viewer'
        ]);
        $message = new Message('anymessage');
        $this->messageHandler->onPipeMessage(
            $this->server,
            0,
            $message->toJson()
        );
        $this->serverProhecy->push(
            $any,
            $message->toJson()
        )->shouldHaveBeenCalledTimes(2);
    }

    public function testOnPipeMessageWithObject()
    {
        $any = new AnyValueToken;
        $this->serverHandler->log($any, $any, $any)->shouldBeCalled();
        $this->serverHandler->getConnectionsCount(null)->willReturn(2);
        $this->serverHandler->getConnections(
            null,
            $this->messageHandler
        )->willReturn([
            10 => 'manager',
            2  => 'viewer'
        ]);
        $message = new Message('anymessage');
        $this->messageHandler->onPipeMessage(
            $this->server,
            0,
            $message
        );
        $this->serverProhecy->push(
            $any,
            $message->toJson()
        )->shouldHaveBeenCalledTimes(2);
    }

    public function testOnFinish()
    {
        $any = new AnyValueToken;
        $this->serverHandler->log($any, $any, $any)->shouldBeCalled();
        $this->serverHandler->getConnectionsCount(null)->willReturn(2);
        $this->serverHandler->getConnections(
            null,
            $this->messageHandler
        )->willReturn([
            10 => 'manager',
            2  => 'viewer'
        ]);
        $message = new Message('anymessage');
        $this->messageHandler->onFinish(
            $this->server,
            1,
            $message->toJson()
        );
        $this->serverProhecy->push(
            $any,
            $message->toJson()
        )->shouldHaveBeenCalledTimes(2);
    }

    public function testOnMessageJsonException()
    {
        $any = new AnyValueToken;
        $this->serverHandler->log(LogLevel::DEBUG, $any, $any)->shouldBeCalled();
        $this->serverHandler->log(LogLevel::ERROR, $any, $any)->shouldBeCalled();

        $this->frame->data = "not valid json!";
        $this->messageHandler->onMessage(
            $this->server,
            $this->frame->reveal()
        );
        $this->serverHandler->checkProphecyMethodsPredictions();
    }
}
