<?php

namespace ResizeServer;

use Psr\Log\LoggerInterface;

class Instruments
{
    public static function timerStart(): float
    {
        return -microtime(true);
    }

    public static function timerLog(float $time, string $name, LoggerInterface $logger = null): float
    {
        $time += microtime(true);
        if ($logger) {
            $logger->debug("$name took $time");
        }
        return $time;
    }
}
