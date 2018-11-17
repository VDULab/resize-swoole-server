<?php

namespace ResizeServer\Task;

use Swoole\Server;

use ResizeServer\WebSocket\Message;
use ResizeServer\WebSocket\Message\ScanResponse;
use ResizeServer\WebSocket\Message\PathEntry;
use ResizeServer\WebSocket\Message\ImageUrls as ImageUrlsMessage;

class ScanTask extends TaskHandler
{
    const MAX_BATCH_SIZE = 50;
    const LOCAL_DIR_RULE_NAME = 'localDirRewriteRule';

    protected function localDir(Server $server, $msg, $frame = null): object
    {
        if (! isset($msg->full) || (isset($msg->full) && $msg->full === false)) {
            $scanResult = $this->scanDir($msg->path, false);
            $response = ScanResponse::buildFromScanResults($scanResult);
            return $response;
        }
        $ls = $this->scanDir($msg->path);
        $rewritePaths = $ls;
        $count = count($ls);
        $this->debug('Found: {ls}', ['ls' => $count]);
        $ls = $this->serverHandler->addPaths($ls);

        while ($count > self::MAX_BATCH_SIZE) {
            $batch = array_splice($ls, 0, self::MAX_BATCH_SIZE);
            $count = count($ls);
            $this->debug("Sent " . self::MAX_BATCH_SIZE . ", remaining: {ls}", ['ls' => $count]);
            $this->sendUrls($server, $batch, $frame);
        }
        $this->debug("Sent {ls} remaining: 0", ['ls' => $count]);
        return new ImageUrlsMessage($ls);
    }

    private function sendUrls(Server $server, $items, $frame): void
    {
        $message = new ImageUrlsMessage($items);
        $server->sendMessage($message, $this->sourceWorkerID);
    }

    private function scanDir($path, $full = true): array
    {
        $ls = [];
        $toScan = [];
        $this->info("Opening $path");
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (! $this->isBlackListed($entry)) {
                    $child_path = $path . DIRECTORY_SEPARATOR . $entry;
                    if (is_dir($child_path)) {
                        $toScan[$child_path] = 0;
                    } elseif ($this->isImage($entry)) {
                        $ls[] = $child_path;
                    }
                }
            }
            closedir($handle);
            // $this->debug("Closing $path : {toScan} {ls}", ['ls' => $ls, 'toScan' => $toScan]);
            if (! empty($toScan)) {
                foreach ($toScan as $child_path => &$count) {
                    if ($full) {
                        $this->debug("Scanning $child_path");
                        $child = $this->scanDir($child_path);
                        $ls = array_merge($ls, $child);
                    } else {
                        $count = $count + $this->countDir($child_path);
                        $this->debug("$child_path has $count items");
                    }
                }
            }
        }

        return ($full) ? $ls : $toScan;
    }

    private function countDir($path): int
    {
        $this->debug("Counting $path");
        $count = 0;
        if ($handle = opendir($path)) {
            while (false !== ($entry = readdir($handle))) {
                if (! $this->isBlackListed($entry)) {
                    $child_path = $path . DIRECTORY_SEPARATOR . $entry;
                    if ($this->isImage($entry)) {
                        $count++;
                    }
                }
            }
            closedir($handle);
        }
        return $count;
    }

    public function getBlackList(): array
    {
        return [
            '',
            '.',
            '..',
        ];
    }

    public function isBlackListed(string $entry): bool
    {
        if (in_array($entry, $this->getBlackList())) {
            // $this->debug("$entry is blacklisted.");
            return true;
        }
        return false;
    }

    private function isImage(string $entry): bool
    {
        $length = strlen($entry);
        foreach (['.jpg', '.jpeg'] as $extension) {
            if (stripos($entry, $extension) == $length - strlen($extension)) {
                return true;
            }
            // $this->debug("No match $extension $entry");
        }
        return false;
    }
}
