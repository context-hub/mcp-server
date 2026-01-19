<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

use Butschster\ContextGenerator\DirectoriesInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Factory for creating ConfigWatcherConfig with proper path resolution.
 */
final readonly class ConfigWatcherConfigFactory
{
    public function __construct(
        #[Proxy] private DirectoriesInterface $dirs,
    ) {}

    public function create(): ConfigWatcherConfig
    {
        $configPath = (string) $this->dirs->getConfigPath();

        // If it's a directory, look for context.yaml or context.json
        if (\is_dir($configPath)) {
            foreach (['context.yaml', 'context.yml', 'context.json'] as $filename) {
                $filePath = $configPath . '/' . $filename;
                if (\file_exists($filePath)) {
                    $configPath = $filePath;
                    break;
                }
            }
        }

        return new ConfigWatcherConfig(
            mainConfigPath: $configPath,
            importPaths: [],
        );
    }
}
