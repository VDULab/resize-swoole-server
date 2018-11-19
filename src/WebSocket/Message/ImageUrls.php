<?php

namespace ResizeServer\WebSocket\Message;

use ResizeServer\WebSocket\Message as BaseMessage;

/**
 * A message with a list of image paths.
 */
class ImageUrls extends BaseMessage
{
    public const TYPE = 'imageUrls';

    /**
     * @var array;
     */
    public $images = [];

    /**
     * Bulid an ImageUrls message form an array of PathEntry.
     *
     * @param \ResizeServer\WebSocket\Message\PathEntry[] $items
     *   PathEntry items
     *
     * @return \ResizeServer\WebSocket\Message\imageUrls
     *   A message with paths form PathEntry
     */
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
