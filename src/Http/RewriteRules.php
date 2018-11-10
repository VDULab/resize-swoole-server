<?php

namespace ResizeServer\Http;

use Swoole\Table;
use Swoole\Lock;
use Swoole\Http\Request;
use Swoole\Http\Response;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use ResizeServer\Swoole\AbstractArrayTable;
use ResizeServer\Http\RewriteRuleStorageInterface;
use ResizeServer\Instruments;

/**
 * RewriteRules storage table.
 */
class RewriteRules extends AbstractArrayTable implements RewriteRuleStorageInterface
{
    use LoggerAwareTrait;

    const COL_PATH = 'path';
    const COL_SEEN = 'seen';

    public static function buildTable(): Table
    {
        $table = new Table(2);
        $table->column(self::COL_PATH, Table::TYPE_STRING, 255);
        $table->column(self::COL_SEEN, Table::TYPE_INT);

        $table->create();
        return $table;
    }

    public function __construct(Table $table, LoggerInterface $logger)
    {
        parent::__construct($table);
        $this->setLogger($logger);
    }

    public function getRules(): array
    {
        $rewriteRules = [];
        $time = Instruments::timerStart();
        foreach ($this->table as $key => $value) {
            // $this->logger->debug("{val}", ['val' => $value]);
            $rewriteRules[] = new class($this->logger, [$value[self::COL_PATH]]) implements RewriteRuleInterface
            {
                public function __construct($logger, $rewritePaths)
                {
                    $this->logger = $logger;
                    $this->rewritePaths = $rewritePaths;
                }

                public function callback(Request $request, Response $response): bool
                {
                    $uri = urldecode($request->server['request_uri']);

                    if (in_array($uri, $this->rewritePaths)) {
                        $fileTime = Instruments::timerStart();
                        if (is_file($uri)) {
                            Instruments::timerLog($fileTime, 'is_file', $this->logger);
                            $this->logger->info('Sending : {uri}', ['uri' => $uri]);

                            $response->header('Content-Type', 'image/jpeg');
                            $response->sendfile($uri);
                            return true;
                        }
                        $this->logger->notice('Not found: {uri}', ['uri' => $uri]);
                    }
                    return false;
                }
            };
        }
        Instruments::timerLog($time, __FUNCTION__, $this->logger);

        return $rewriteRules;
    }

    public function addPaths(array $paths): void
    {
        $this->rw_lock->lock();

        foreach ($paths as $path) {
            $key = md5($path);
            if ($this->table->exist($key)) {
                $this->table->incr($key, self::COL_SEEN);
            } else {
                $this->table->set($key, [self::COL_PATH => $path, self::COL_SEEN => 1]);
            }
        }
        $this->rw_lock->unlock();
        $pathCount = count($paths);
        $tableCount = $this->table->count();
        $this->logger->debug("Added $pathCount rules, now at $tableCount");
    }
}
