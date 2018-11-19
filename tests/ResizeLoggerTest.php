<?php
declare(strict_types=1);

namespace ResizeServer\Tests;

use PHPUnit\Framework\TestCase;

use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use ResizeServer\ResizeLogger;

class ResizeLoggerTest extends TestCase
{
    public function setUp()
    {
        $this->logger = new ResizeLogger(LogLevel::DEBUG);

        $reflection = new \ReflectionClass(ResizeLogger::class);
        /**
         * @var ReflectionMethod
         */
        $this->method = $reflection->getMethod('interpolate');
        $this->method->setAccessible(true);
    }

    public function testInterpolateWithoutSubstitutions()
    {
        $message = 'message';
        $result = $this->method->invokeArgs($this->logger, [$message, [] ]);
        $this->assertRegExp("/\[.*\] $message/", $result);
    }

    public function testInterpolateWithClass()
    {
        $message = 'message';
        $class = 'ClassName';
        $result = $this->method->invokeArgs(
            $this->logger,
            [
                $message,
                ['class' => $class]
            ]
        );
        $this->assertEquals("[$class] $message", $result);
    }

    public function testInterpolateWithSubstitutions()
    {
        $message = '{string}-{array}-{object}-{date}';
        $class = 'ClassName';
        $result = $this->method->invokeArgs(
            $this->logger,
            [
                $message,
                [
                    'class' => $class,
                    'string' => 'subs',
                    'array' => [],
                    'object' => new \stdClass(),
                    'date' => new \DateTime('1 january 1980')
                ],
            ]
        );
        $this->assertEquals("[$class] subs-[]-{}-1980-01-01T00:00:00+00:00", $result);
    }
}
