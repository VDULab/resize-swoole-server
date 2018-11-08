<?php

namespace ResizeServer\Event;

use Swoole\Http\Request;
use Swoole\Http\Response;
use Psr\Log\LoggerInterface;

class RequestHandler extends AbstractEventHandler
{
    private $webRoot;

    /**
     * @var ResizeServer\Event\RewriteRuleInterface[]
     */
    private $rewriteRules = [];

    public function __construct(LoggerInterface $serverHandler, $root)
    {
        parent::__construct($serverHandler);
        $this->webRoot = $root;
    }

    public static function getHandlerType(): string
    {
        return 'requestHandler';
    }

    public function onRequest(Request $request, Response $response)
    {
        $sent = $this->rewriteRules($request, $response);

        if (! $sent) {
            $this->notFound($request, $response);
        }
    }

    private function notFound(Request $request, Response $response)
    {
        $response->status(404);
        $response->end('Not Found!');
        $this->debug("Not found: {request}", ['request' => $request]);
    }

    public function addRewriteRule(string $name, RewriteRuleInterface $rule): void
    {
        $this->rewriteRules[$name] = $rule;
    }

    private function rewriteRules(Request $request, Response $response)
    {
        if (! empty($this->rewriteRules)) {
            $this->debug("RewriteStart");
            foreach ($this->rewriteRules as $name => $rule) {
                $this->debug("Processing: {rule}", ['rule' => $name]);
                if ($rule->callback($request, $response)) {
                    $this->debug("Processing {rule}: true", ['rule' => $name]);
                    return true;
                }
            }
        } else {
            $this->debug("No added rules");
        }
        $uri = $request->server['request_uri'];
        if (in_array($uri, ["/slideshow", "/remote"])) {
            $response->sendfile($this->webRoot . "$uri.html");
            return true;
        }
        if ($uri == '/') {
            $index = $this->webRoot . '/test.html';
            // ob_start();
            // include $index;
            // $out = ob_get_contents();
            // ob_end_clean();
            // $response->end($out);
            $response->sendfile($index);
            return true;
        }
    }
}
