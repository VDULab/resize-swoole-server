<?php

namespace ResizeServer\WebSocket;

use Swoole\Serialize;
use Swoole\Table;
use Swoole\Lock;

/**
 * Connections.
 */
class Connections implements ConnectionsInterface
{
    /**
     * @var \Swoole\Table
     */
    protected $list;

    /**
     * @var \Swoole\Lock
     */
    protected $rw_lock;

    public static function buildTable()
    {
        $table = new Table(1);
        $table->column('protocol', Table::TYPE_STRING, 32);
        $table->create();
        return $table;
    }

    public function __construct(Table $table)
    {
        $this->list = $table;
        $this->rw_lock = new Lock(Lock::RWLOCK);
    }

    public function set($id, $protocol = 'unknown')
    {
        $this->rw_lock->lock();
        $this->list->set($id, ['protocol' => $protocol]);
        $this->rw_lock->unlock();
    }

    public function get($id)
    {
        return $this->list->get($id, 'protocol');
    }

    public function remove($id)
    {
        $this->rw_lock->lock();
        $this->list->del($id);
        $this->rw_lock->unlock();
    }

    public function list()
    {
        return $this->list->filterList();
    }

    private function _getConnections($protocol = null, $logger = null, $count = false)
    {

        $protocol = (empty($protocol) || $protocol == 'all') ? null : $protocol;
        $protocol = ($protocol == 'viewers') ? 'viewer' : $protocol;
        $filter = $this->filterList($protocol, $count);
        if ($logger && empty($filter) && !$count) {
            $logger->warning("Filter $protocol empty!");
            $logger->debug("Connections: {connections}", ['connections' => $this]);
        }
        
        return ($count) ? count($filter) : $filter;
    }

    private function filterList($protocol = null, $count = false)
    {
        $filter = [];
        $noFilter = empty($protocol);
        foreach ($this->list as $key => $value) {
            if ($noFilter
                || $value['protocol'] == $protocol
                || ($value['protocol'] == 'logger' && !$count)) {
                $filter[$key] = $value['protocol'];
            }
        }

        return $filter;
    }

    /**
     * Returns the connections registered for a type.
     *
     * @param string $protocol
     *   Filter
     * @param \Psr\Log\LoggerInterface $logger
     *   Filter
     * @return array
     *   An array of connections
     */
    public function getConnections($protocol = null, $logger = null)
    {
        return $this->_getConnections($protocol, $logger);
    }

    public function getConnectionsCount($protocol = null)
    {
        return $this->_getConnections($protocol, null, true);
    }

    public function __toString()
    {
        return json_encode($this->filterList());
    }

    public function __toArray()
    {
        $this->filterList();
    }
}
