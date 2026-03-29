<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiffCalculator;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\ChangeHandlerRegistry;
use Butschster\ContextGenerator\McpServer\Watcher\Strategy\WatchStrategyFactory;
use Butschster\ContextGenerator\McpServer\Watcher\Strategy\WatchStrategyInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Orchestrates file watching, change detection, and handler execution.
 */
final class ConfigWatcher implements ConfigWatcherInterface
{
    private ?WatchStrategyInterface $strategy = null;
    private ?string $mainConfigPath = null;

    /** @var array<string> */
    private array $importPaths = [];

    /** @var array<string, mixed> */
    private array $lastConfig = [];

    private float $lastChangeTime = 0;
    private bool $pendingReload = false;
    private bool $enabled;

    private const int DEBOUNCE_MS = 1000;

    public function __construct(
        private readonly WatchStrategyFactory $strategyFactory,
        private readonly ConfigDiffCalculator $diffCalculator,
        private readonly ChangeHandlerRegistry $handlerRegistry,
        #[Proxy] private readonly ConfigLoaderFactoryInterface $configLoaderFactory,
        #[Proxy] private readonly LoggerInterface $logger = new NullLogger(),
        EnvironmentInterface $env,
    ) {
        $this->enabled = (bool) $env->get('MCP_HOT_RELOAD', true);
    }

    public function start(string $mainConfigPath, array $importPaths = []): void
    {
        if (!$this->enabled) {
            $this->logger->info('Config watcher is disabled');
            return;
        }

        if (!\file_exists($mainConfigPath)) {
            $this->logger->error('Config file not found, watcher disabled', [
                'path' => $mainConfigPath,
            ]);
            return;
        }

        $this->mainConfigPath = $mainConfigPath;
        $this->importPaths = $importPaths;

        $this->strategy = $this->strategyFactory->create();

        $this->logger->info('Starting config watcher', [
            'strategy' => $this->strategy::class,
            'mainConfig' => $mainConfigPath,
            'imports' => \count($importPaths),
        ]);

        $this->strategy->addFile($mainConfigPath);

        foreach ($importPaths as $path) {
            $this->strategy->addFile($path);
        }

        $this->loadCurrentConfig();
    }

    public function tick(): void
    {
        if ($this->strategy === null || !$this->enabled) {
            return;
        }

        // Check for pending debounced reload
        if ($this->pendingReload) {
            $now = \microtime(true) * 1000;

            if ($now - $this->lastChangeTime >= self::DEBOUNCE_MS) {
                $this->processChanges();
                $this->pendingReload = false;
            }

            return;
        }

        // Check for file changes
        $changedFiles = $this->strategy->check();

        if ($changedFiles !== []) {
            $this->logger->debug('Config file changes detected', [
                'files' => $changedFiles,
            ]);

            $this->lastChangeTime = \microtime(true) * 1000;
            $this->pendingReload = true;
        }
    }

    public function stop(): void
    {
        if ($this->strategy !== null) {
            $this->strategy->stop();
            $this->strategy = null;
        }

        $this->mainConfigPath = null;
        $this->importPaths = [];
        $this->lastConfig = [];
        $this->pendingReload = false;

        $this->logger->info('Config watcher stopped');
    }

    public function isWatching(): bool
    {
        return $this->strategy !== null && $this->enabled;
    }

    public function updateImports(array $importPaths): void
    {
        if ($this->strategy === null) {
            return;
        }

        $newPaths = \array_diff($importPaths, $this->importPaths);
        $removedPaths = \array_diff($this->importPaths, $importPaths);

        foreach ($removedPaths as $path) {
            $this->strategy->removeFile($path);
        }

        foreach ($newPaths as $path) {
            $this->strategy->addFile($path);
        }

        $this->importPaths = $importPaths;

        $this->logger->debug('Import watch list updated', [
            'added' => \count($newPaths),
            'removed' => \count($removedPaths),
        ]);
    }

    private function processChanges(): void
    {
        $this->logger->info('Processing config changes');

        try {
            $newConfig = $this->loadConfig();

            if ($newConfig === null) {
                $this->logger->warning('Failed to load config, keeping current state');
                return;
            }

            $diffs = $this->diffCalculator->calculateAll($this->lastConfig, $newConfig);

            if ($diffs === []) {
                $this->logger->debug('No effective changes detected');
                $this->lastConfig = $newConfig;
                return;
            }

            foreach ($diffs as $section => $diff) {
                $handler = $this->handlerRegistry->get($section);

                if ($handler === null) {
                    $this->logger->debug('No handler for section', ['section' => $section]);
                    continue;
                }

                try {
                    $handler->apply($diff);

                    $this->logger->info('Applied changes', [
                        'section' => $section,
                        'summary' => $diff->getSummary(),
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error('Handler failed', [
                        'section' => $section,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $this->lastConfig = $newConfig;
        } catch (\Throwable $e) {
            $this->logger->error('Config reload failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadConfig(): ?array
    {
        if ($this->mainConfigPath === null) {
            return null;
        }

        try {
            $loader = $this->configLoaderFactory->createForFile($this->mainConfigPath);

            return $loader->loadRawConfig();
        } catch (\Throwable $e) {
            $this->logger->error('Config load error', [
                'path' => $this->mainConfigPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function loadCurrentConfig(): void
    {
        $config = $this->loadConfig();

        if ($config !== null) {
            $this->lastConfig = $config;
        }
    }
}
