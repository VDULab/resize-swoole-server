<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use ResizeServer\WebSocket\Message;

class MessageTest extends TestCase
{
    public function testConstructor()
    {
        $message = new Message('type', 'destination');
        $this->assertInstanceOf(Message::class, $message);
    }

    public function testBuildNext()
    {
        /**
         * @var \ResizeServer\WebSocket\Message
         */
        $message = Message::buildNext();
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('requestNext', $message->type);
    }

    public function testbuildNotificationWithBoolean()
    {
        /**
         * @var \ResizeServer\WebSocket\Message
         */
        $message = Message::buildNotification('bool', true);
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('notification', $message->type);
        $this->assertEquals('bool', $message->key);
        $this->assertEquals(true, $message->value);
    }

    public function testbuildNotificationWithString()
    {
        /**
         * @var \ResizeServer\WebSocket\Message
         */
        $message = Message::buildNotification('string', 'notification test');
        $this->assertInstanceOf(Message::class, $message);
        $this->assertEquals('notification', $message->type);
        $this->assertEquals('string', $message->key);
        $this->assertEquals('notification test', $message->value);
    }

    public function testToJson()
    {
        $message = new Message('type', 'destination');
        $this->assertJson($message->toJson());
    }

    public function testGetDestination()
    {
        $message = new Message('type', 'destination');
        $this->assertEquals(
            'destination',
            $message->getDestination()
        );
    }

    public function testGetDestinationEmpty()
    {
        $message = new Message('type');
        $this->assertNull($message->getDestination());
    }
}
