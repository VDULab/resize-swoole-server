<?php

namespace ResizeServer\Event;

use Swoole\Http\Request;
use Swoole\Http\Response;

interface RewriteRuleInterface
{
    public function callback(Request $request, Response $response): bool;
}
