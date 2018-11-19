<?php

namespace ResizeServer\WebSocket\Message;

use ResizeServer\WebSocket\Message as BaseMessage;

class ScanResponse extends BaseMessage
{
    public const TYPE = 'scanResponse';

    /**
     * @var \ResizeServer\WebSocket\Message\PathEntry[];
     */
    public $entries = [];

    public function __construct(array $items)
    {
        parent::__construct(self::TYPE);
        $this->entries = $items;
    }
}
