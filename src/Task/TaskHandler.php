<?php

namespace ResizeServer\Task;

use Swoole\Server;

use Psr\Log\LoggerTrait;

use ResizeServer\WebSocketServerInterface;

class TaskHandler
{
    use LoggerTrait;

    /**
     * @var \ResizeServer\WebSocketServerInterface
     */
    protected $serverHandler;

    protected $sourceWorkerID;

    public function __construct(WebSocketServerInterface $serverHandler)
    {
        $this->serverHandler = $serverHandler;
    }

    public function onTask(Server $server, int $task_id, int $src_worker_id, $data): ?string
    {
        $this->info("Starting task #$task_id from worker #$src_worker_id");
        $this->sourceWorkerID = $src_worker_id;
        $this->debug("#$task_id got data: {data}", ['data' => $data]);
        if (isset($data->type)) {
            switch ($data->type) {
                case 'scanDir':
                    $result = $this->localDir($server, $data);
                    $this->debug("#$task_id got results: {data}", ['data' => $result]);
                    return json_encode($result);

                case 'imageUrls':
                    return json_encode($result);

                default:
                    $this->warning("#$task_id has type: {type}", ['type' => $data->type]);
                    return null;
            }
        }
        $this->warning("#$task_id data has no type");
        return null;
    }

    public function log($level, $message, array $context = [])
    {
        $this->serverHandler->log($level, $message, $context);
    }
}
