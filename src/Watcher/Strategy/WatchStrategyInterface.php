<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Strategy;

/**
 * Contract for file watching strategies.
 */
interface WatchStrategyInterface
{
    /**
     * Add a file to the watch list.
     */
    public function addFile(string $path): void;

    /**
     * Remove a file from the watch list.
     */
    public function removeFile(string $path): void;

    /**
     * Clear all watched files.
     */
    public function clear(): void;

    /**
     * Check for changes (non-blocking).
     *
     * @return string[] Array of changed file paths, empty if no changes
     */
    public function check(): array;

    /**
     * Release any resources.
     */
    public function stop(): void;
}
