<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\Application\Bootloader\ConsoleBootloader;
use Butschster\ContextGenerator\McpServer\McpConfig\McpConfigBootloader;
use Butschster\ContextGenerator\McpServer\Projects\McpProjectsBootloader;
use Butschster\ContextGenerator\McpServer\Prompt\McpPromptBootloader;
use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\McpResponseStrategy;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Butschster\ContextGenerator\McpServer\Tool\McpToolBootloader;
use League\Route\Router;
use League\Route\Strategy\StrategyInterface;
use Psr\Container\ContainerInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Core\Attribute\Proxy;

final class McpServerBootloader extends Bootloader
{
    #[\Override]
    public function defineDependencies(): array
    {
        return [
            McpToolBootloader::class,
            McpPromptBootloader::class,
            McpProjectsBootloader::class,
            McpConfigBootloader::class,
            McpServerCoreBootloader::class,
        ];
    }

    #[\Override]
    public function defineSingletons(): array
    {
        return [
            RouteRegistrar::class => RouteRegistrar::class,
            McpItemsRegistry::class => McpItemsRegistry::class,
            StrategyInterface::class => McpResponseStrategy::class,
            Router::class => static function (StrategyInterface $strategy, #[Proxy] ContainerInterface $container) {
                $router = new Router();
                \assert($strategy instanceof McpResponseStrategy);
                $strategy->setContainer($container);
                $router->setStrategy($strategy);

                return $router;
            },
        ];
    }
}
