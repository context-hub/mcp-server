<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Strategy;

/**
 * Factory for creating the best available file watch strategy.
 */
final readonly class WatchStrategyFactory
{
    public function __construct(
        private int $pollingIntervalMs = 2000,
    ) {}

    /**
     * Create the best available watch strategy.
     * Prefers inotify on Linux, falls back to polling.
     */
    public function create(): WatchStrategyInterface
    {
        if (\extension_loaded('inotify')) {
            return new InotifyWatchStrategy();
        }

        return new PollingWatchStrategy($this->pollingIntervalMs);
    }

    /**
     * Create a polling strategy explicitly.
     */
    public function createPolling(): PollingWatchStrategy
    {
        return new PollingWatchStrategy($this->pollingIntervalMs);
    }

    /**
     * Create an inotify strategy explicitly.
     *
     * @throws \RuntimeException If ext-inotify is not available
     */
    public function createInotify(): InotifyWatchStrategy
    {
        if (!\extension_loaded('inotify')) {
            throw new \RuntimeException('ext-inotify is not available');
        }

        return new InotifyWatchStrategy();
    }
}
