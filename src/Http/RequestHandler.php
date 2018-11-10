<?php

namespace ResizeServer\Http;

use Swoole\Http\Request;
use Swoole\Http\Response;

use ResizeServer\Event\AbstractEventHandler;
use ResizeServer\WebSocketServerInterface;

use ResizeServer\Instruments;

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
        $worker = $this->serverHandler->getWorkerId();
        $uri = self::requestUri($request);
        $this->debug("Worker #$worker is handling $uri");
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
            $this->warning("Not found: {request}", ['request' => self::requestUri($request)]);
            $this->debug("Full request: {request}", ['request' => $request]);
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

        $rulesTimer = Instruments::timerStart();
        $rewriteRules = $this->serverHandler->getRules();
        if (! empty($rewriteRules)) {
            $this->debug("RewriteStart");
            foreach ($rewriteRules as $ruleNumber => $rule) {
                if ($rule->callback($request, $response)) {
                    $this->debug("Matched #{rule}", ['rule' => $ruleNumber]);
                    Instruments::timerLog($rulesTimer, __FUNCTION__, $this);
                    return true;
                }
            }
            $this->debug("No rules match");
        }
        return false;
    }
}
