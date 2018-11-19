<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument\Token\StringContainsToken;

use Psr\Log\LoggerInterface;
use ResizeServer\Instruments;

class InstrumentsTest extends TestCase
{
    public function setUp()
    {
        $this->logger = $this->prophesize(LoggerInterface::class);
    }

    public function testInstumentsMeasure()
    {
        $start = Instruments::timerStart();
        $result = Instruments::timerLog($start, __FUNCTION__, $this->logger->reveal());
        $this->assertGreaterThan($start, $result);
    }

    public function testInstumentsMeasureWithLogger()
    {
        /**
         * @var \Prophecy\Prophecy\MethodProphecy
         */
        $debug = $this->logger->debug();
        $debug
            ->withArguments([
                new StringContainsToken(__FUNCTION__ . " took ")
            ])
            ->shouldBeCalledOnce();
        $start = Instruments::timerStart();
        $result = Instruments::timerLog($start, __FUNCTION__, $this->logger->reveal());
        $this->logger->checkProphecyMethodsPredictions();
    }
}
