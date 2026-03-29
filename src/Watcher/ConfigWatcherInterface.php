<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

/**
 * Contract for configuration file watcher.
 */
interface ConfigWatcherInterface
{
    /**
     * Start watching configuration files.
     *
     * @param string $mainConfigPath Path to main context.yaml
     * @param array<string> $importPaths Paths to imported config files
     */
    public function start(string $mainConfigPath, array $importPaths = []): void;

    /**
     * Check for changes and process them (non-blocking).
     * Should be called periodically from event loop.
     */
    public function tick(): void;

    /**
     * Stop watching and release resources.
     */
    public function stop(): void;

    /**
     * Check if watcher is currently active.
     */
    public function isWatching(): bool;

    /**
     * Update the list of imported files to watch.
     *
     * @param array<string> $importPaths New import paths
     */
    public function updateImports(array $importPaths): void;
}
