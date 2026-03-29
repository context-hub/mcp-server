<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher;

use Butschster\ContextGenerator\Config\Loader\ConfigLoaderFactoryInterface;
use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiffCalculator;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\ChangeHandlerRegistry;
use Butschster\ContextGenerator\McpServer\Watcher\Handler\ChangeHandlerRegistryFactory;
use Butschster\ContextGenerator\McpServer\Watcher\Strategy\WatchStrategyFactory;
use Psr\Log\LoggerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Bootloader for config watcher components.
 */
final class ConfigWatcherBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            WatchStrategyFactory::class => static fn(
                EnvironmentInterface $env,
            ): WatchStrategyFactory => new WatchStrategyFactory(
                pollingIntervalMs: (int) $env->get('MCP_HOT_RELOAD_INTERVAL', 2000),
            ),

            ConfigDiffCalculator::class => ConfigDiffCalculator::class,
            ChangeHandlerRegistryFactory::class => ChangeHandlerRegistryFactory::class,

            ChangeHandlerRegistry::class => static fn(
                ChangeHandlerRegistryFactory $factory,
            ): ChangeHandlerRegistry => $factory->create(),

            ConfigWatcherInterface::class => ConfigWatcher::class,
        ];
    }
}
