<?php

namespace ResizeServer\WebSocket\Message;

class PathEntry
{
    public $path;
    public $count;
    public $type;

    public function __construct($path, int $count = 0, string $type = 'dir')
    {
        $this->path = $path;
        $this->count = $count;
        $this->type = $type;
    }
}
