<?php

namespace ResizeServer\Swoole;

use \Swoole\Table;
use \Swoole\Lock;

abstract class AbstractArrayTable
{
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

    public function remove($id)
    {
        $this->rw_lock->lock();
        $this->table->del($id);
        $this->rw_lock->unlock();
    }
}
