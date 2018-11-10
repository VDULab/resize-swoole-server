<?php

namespace ResizeServer\Swoole;

use \Swoole\Table;
use \Swoole\Lock;

abstract class AbstractArrayTable
{
    const TABLE_MAX_COUNT = 400;
    const BATCH_SIZE = 20;
    /**
     * @var \Swoole\Table
     */
    protected $table;

    /**
     * @var \Swoole\Lock
     */
    protected $rw_lock;

    abstract public static function buildTable(): Table;

    public function __construct(Table $table)
    {
        $this->table = $table;
        $this->rw_lock = new Lock(Lock::RWLOCK);
    }

    public function remove($id): void
    {
        $this->rw_lock->lock();
        $this->table->del($id);
        $this->rw_lock->unlock();
    }

    public static function rotateTable(Table &$table): int
    {
        if ($table->count() >= self::TABLE_MAX_COUNT) {
            echo "clening ";
            $table->rewind();
            for ($i = 0; $i < self::BATCH_SIZE; $i++) {
                echo '.' . $i;
                $key = $table->key();
                $table->del($key);
                $table->next();
            }
        }
        return $table->count();
    }
}
