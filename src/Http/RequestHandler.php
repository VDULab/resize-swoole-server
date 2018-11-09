<?php

namespace ResizeServer\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

use ResizeServer\Event\AbstractEventHandler;
use ResizeServer\WebSocketServerInterface;

class RequestHandler extends AbstractEventHandler
{
    private $webRoot;

    /**
     * @var ResizeServer\Event\RewriteRuleInterface[]
     */
    private $rewriteRules = [];

    /**
     * @var string[]
     */
    private $ingoreNotFound = [];

    public function __construct(WebSocketServerInterface $serverHandler, $root)
    {
        parent::__construct($serverHandler);
        $this->webRoot = $root;
    }

    public static function getHandlerType(): string
    {
        return 'requestHandler';
    }

    /**
     * Callback for Swoolw onRequest event.
     */
    public function onRequest(Request $request, Response $response): void
    {
        $sent = $this->rewriteRules($request, $response);
        if (! $sent) {
            $this->notFound($request, $response);
        }
    }

    private function notFound(Request $request, Response $response, $log = true): void
    {
        $response->status(404);
        $response->end('Not Found!');
        if ($log) {
            $this->info("Not found: {request}", ['request' => $request]);
        }
    }

    private function rewriteRules(Request $request, Response $response): bool
    {
        $uri = self::requestUri($request);
        if ($uri == '/') {
            $index = $this->webRoot . '/test.html';
            $response->sendfile($index);
            return true;
        }

        if (in_array($uri, ["/slideshow", "/remote"])) {
            $response->sendfile($this->webRoot . "$uri.html");
            return true;
        }

        $rewriteRules = $this->serverHandler->getRules();
        if (! empty($rewriteRules)) {
            $this->debug("RewriteStart");
            foreach ($rewriteRules as $name => $rule) {
                //$this->debug("Processing: {rule}", ['rule' => $name]);
                if ($rule->callback($request, $response)) {
                    $this->debug("Matched {rule}", ['rule' => $name]);
                    return true;
                }
            }
            $this->debug("No rules match");
        }
    }
}
