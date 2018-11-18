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

    public static function buildFromScanResults(array $items): ScanResponse
    {
        $pathEntries = array_map(function ($key, $item) {
            // return ['path' => $key, 'count' => $item];
            return new PathEntry($key, $item);
        }, array_keys($items), $items);

        return new static($pathEntries);
    }

    public function __construct(array $items)
    {
        parent::__construct(self::TYPE);
        $this->entries = $items;
    }
}
