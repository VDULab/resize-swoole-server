<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use ResizeServer\WebSocket\Message\ScanResponse;
use ResizeServer\WebSocket\Message\PathEntry;

class ScanResponseTest extends TestCase
{
    public function setUp()
    {
        $this->pathEntry = $this->prophesize(PathEntry::class);
    }

    public function testConstructor()
    {
        $message = new ScanResponse([$this->pathEntry->reveal()]);
        $this->assertInstanceOf(ScanResponse::class, $message);
    }

    public function testToJson()
    {
        $this->pathEntry->willBeConstructedWith(['/test/']);
        $message = new ScanResponse([$this->pathEntry->reveal()]);
        $this->assertJson($message->toJson());
        $decoded = json_decode($message->toJson());
        $this->assertEquals('/test/', $decoded->entries[0]->path);
    }
}
