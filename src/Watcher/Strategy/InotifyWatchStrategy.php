<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Strategy;

/**
 * Linux-specific file watching strategy using inotify kernel subsystem.
 * Most efficient option with zero CPU overhead when idle.
 *
 * @requires ext-inotify
 */
final class InotifyWatchStrategy implements WatchStrategyInterface
{
    /** @var resource|closed-resource|null */
    private mixed $inotify = null;

    /** @var array<string, int> path => watch descriptor */
    private array $watches = [];

    /** @var array<int, string> watch descriptor => path */
    private array $descriptorToPath = [];

    public function __construct()
    {
        if (!\extension_loaded('inotify')) {
            throw new \RuntimeException('ext-inotify is not available');
        }

        $this->inotify = \inotify_init();

        // Make non-blocking
        \stream_set_blocking($this->inotify, false);
    }

    public function addFile(string $path): void
    {
        if (!\file_exists($path) || !\is_resource($this->inotify)) {
            return;
        }

        if (isset($this->watches[$path])) {
            return; // Already watching
        }

        $wd = \inotify_add_watch(
            $this->inotify,
            $path,
            \IN_MODIFY | \IN_CLOSE_WRITE | \IN_DELETE_SELF | \IN_MOVE_SELF,
        );

        if ($wd !== false) {
            $this->watches[$path] = $wd;
            $this->descriptorToPath[$wd] = $path;
        }
    }

    public function removeFile(string $path): void
    {
        if (!isset($this->watches[$path]) || !\is_resource($this->inotify)) {
            return;
        }

        $wd = $this->watches[$path];
        @\inotify_rm_watch($this->inotify, $wd);

        unset($this->watches[$path], $this->descriptorToPath[$wd]);
    }

    public function clear(): void
    {
        foreach (\array_keys($this->watches) as $path) {
            $this->removeFile($path);
        }
    }

    public function check(): array
    {
        if (!\is_resource($this->inotify)) {
            return [];
        }

        $events = @\inotify_read($this->inotify);

        if ($events === false) {
            return []; // No events (non-blocking)
        }

        $changed = [];

        foreach ($events as $event) {
            $wd = $event['wd'];
            if (isset($this->descriptorToPath[$wd])) {
                $changed[] = $this->descriptorToPath[$wd];
            }
        }

        return \array_unique($changed);
    }

    public function stop(): void
    {
        $this->clear();

        if (\is_resource($this->inotify)) {
            \fclose($this->inotify);
            $this->inotify = null;
        }
    }

    public function __destruct()
    {
        $this->stop();
    }
}
