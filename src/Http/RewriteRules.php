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
        foreach ($this->table as $key => $value) {
            $rewriteRules[] = $value[self::COL_PATH];
        }

        $class = new class($this->logger, $rewriteRules) implements RewriteRuleInterface
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
                        $this->logger->info('Sending : {uri}', ['uri' => $uri]);

                        $response->header('Content-Type', 'image/jpeg');
                        $response->sendfile($uri);
                        return true;
                }

                return false;
            }
        };
        return [$class];
    }

    public function getString(string $key): ?string
    {
        return $this->table->get($key, self::COL_PATH);
    }

    public function setString(string $key, string $data): void
    {
        $this->rw_lock->lock();
        $this->setOrIncrementString($key, $data);
        $this->rw_lock->unlock();
    }

    private function setOrIncrementString(string $key, string $data): void
    {
        if ($this->table->exist($key)) {
            $this->table->incr($key, self::COL_SEEN);
        } else {
            if ($shrink = self::rotateTable($this->table)) {
                $this->logger->debug("Table reached max size, shrinked to $shrink");
            }
            $this->table->set($key, [self::COL_PATH => $data, self::COL_SEEN => 1]);
        }
    }

    public function addPaths(array $paths): array
    {
        $pathCount = count($paths);
        if (count($paths) > self::TABLE_MAX_COUNT) {
            $this->logger->warning("Trying to add $pathCount paths, limit is " . self::TABLE_MAX_COUNT);
            $paths = array_splice($paths, 0, self::TABLE_MAX_COUNT);
            $pathCount = count($paths);
        }
        $this->rw_lock->lock();

        foreach ($paths as $item) {
            $key = md5($item->path);
            $this->setOrIncrementString($key, $item->path);
        }
        $this->rw_lock->unlock();
        $tableCount = $this->table->count();
        $this->logger->info("Added $pathCount rules, now at $tableCount");
        return $paths;
    }
}
