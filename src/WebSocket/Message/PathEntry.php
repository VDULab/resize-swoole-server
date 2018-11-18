<?php

namespace ResizeServer\WebSocket\Message;

class PathEntry
{
    public $path;
    public $count;
    public $type;

    public function __construct($path, string $type = 'dir', int $count = 0)
    {
        $this->path = $path;
        $this->count = $count;
        $this->type = $type;
    }
}
