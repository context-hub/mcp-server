<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Strategy;

/**
 * Universal file watching strategy using filemtime() polling.
 * Works on all platforms but has slightly higher CPU usage than inotify.
 */
final class PollingWatchStrategy implements WatchStrategyInterface
{
    /** @var array<string, int> path => last known mtime */
    private array $files = [];

    private float $lastCheckTime = 0;

    public function __construct(
        private readonly int $intervalMs = 2000,
    ) {}

    public function addFile(string $path): void
    {
        if (!\file_exists($path)) {
            return;
        }

        $this->files[$path] = \filemtime($path) ?: 0;
    }

    public function removeFile(string $path): void
    {
        unset($this->files[$path]);
    }

    public function clear(): void
    {
        $this->files = [];
    }

    public function check(): array
    {
        $now = \microtime(true) * 1000;

        // Respect polling interval
        if ($now - $this->lastCheckTime < $this->intervalMs) {
            return [];
        }

        $this->lastCheckTime = $now;

        $changed = [];

        foreach ($this->files as $path => $lastMtime) {
            if (!\file_exists($path)) {
                // File deleted - consider it changed
                $changed[] = $path;
                continue;
            }

            \clearstatcache(true, $path);
            $currentMtime = \filemtime($path) ?: 0;

            if ($currentMtime > $lastMtime) {
                $changed[] = $path;
                $this->files[$path] = $currentMtime;
            }
        }

        return $changed;
    }

    public function stop(): void
    {
        $this->clear();
    }
}
