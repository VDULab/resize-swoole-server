<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use ResizeServer\WebSocket\Connections;
use Psr\Log\LoggerInterface;

class ConnectionsTest extends TestCase
{
    const TEST_STRING = "test string.";

    public function setUp()
    {
        $this->connections = new Connections(Connections::buildTable());
        $this->logger = $this->prophesize(LoggerInterface::class);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(Connections::class, $this->connections);
    }

    public function testSetGetString()
    {
        $this->connections->setString(__FUNCTION__, self::TEST_STRING);
        $this->assertEquals(
            self::TEST_STRING,
            $this->connections->getString(__FUNCTION__)
        );
    }

    public function testList()
    {
        $this->connections->setString(__FUNCTION__, self::TEST_STRING);
        $this->assertEquals(
            [__FUNCTION__ => self::TEST_STRING],
            $this->connections->list()
        );
    }

    private function fillData()
    {
        $this->connections->setString("a", 'protocol1');
        $this->connections->setString("b", 'protocol2');
    }

    public function testGetConnectionsByProtocol()
    {
        $this->fillData();
        $logger = $this->logger->reveal();
        $this->assertEquals(
            ['a' => 'protocol1'],
            $this->connections->getConnections('protocol1', $logger)
        );
        $this->assertEquals(
            ['b' => 'protocol2'],
            $this->connections->getConnections('protocol2', $logger)
        );
    }

    public function testGetConnectionsWithoutLogger()
    {
        $this->fillData();
        $this->assertEquals(
            ['a' => 'protocol1'],
            $this->connections->getConnections('protocol1')
        );
        $this->assertEquals(
            ['b' => 'protocol2'],
            $this->connections->getConnections('protocol2')
        );
    }

    public function testGetConnectionsWithoutFilter()
    {
        $logger = $this->logger->reveal();
        $this->assertEquals(
            [],
            $this->connections->getConnections(null, $logger)
        );
    }

    public function testGetConnectionsWithoutFilterAndLogger()
    {
        $this->connections->setString("a", 'protocol1');
        $this->assertEquals(
            ['a' => 'protocol1'],
            $this->connections->getConnections()
        );
    }

    public function testGetConnectionsCount()
    {
        $this->fillData();
        $this->assertEquals(
            2,
            $this->connections->getConnectionsCount()
        );
    }

    public function testGetConnectionsCountWithFilter()
    {
        $this->fillData();
        $this->assertEquals(
            1,
            $this->connections->getConnectionsCount('protocol1')
        );
    }

    public function testToString()
    {
        $this->fillData();
        $this->assertStringStartsWith('{', (string) $this->connections);
    }

    public function testToArray()
    {
        $this->fillData();
        $this->assertArraySubset(
            ['a' => 'protocol1'],
            $this->connections->__toArray()
        );
    }
}
