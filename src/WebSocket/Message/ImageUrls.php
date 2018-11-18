<?php

namespace ResizeServer\WebSocket\Message;

use ResizeServer\WebSocket\Message as BaseMessage;

class ImageUrls extends BaseMessage
{
    public const TYPE = 'imageUrls';

    /**
     * @var array;
     */
    public $images = [];

    public static function buildFromScanResults(array $items): ImageUrls
    {
        $pathEntries = array_map(function ($item) {
            return $item->path;
        }, $items);

        return new static($pathEntries);
    }

    public function __construct(array $items)
    {
        parent::__construct(self::TYPE);
        $this->images = $items;
    }
}
