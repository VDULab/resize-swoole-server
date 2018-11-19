<?php

namespace ResizeServer\Event;

interface AutoRegisterInterface
{
    /**
     * Get the type of handler.
     * @return String
     */
    public static function getHandlerType(): string;
}
