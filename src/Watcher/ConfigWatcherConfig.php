<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

/**
 * Holds configuration paths for the watcher.
 * Populated during server initialization.
 */
final readonly class ConfigWatcherConfig
{
    /**
     * @param string $mainConfigPath Path to main context.yaml
     * @param array<string> $importPaths Paths to imported config files
     */
    public function __construct(
        public string $mainConfigPath,
        public array $importPaths = [],
    ) {}
}
