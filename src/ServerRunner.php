<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Registry\McpItemsRegistry;
use Butschster\ContextGenerator\McpServer\Routing\RouteRegistrar;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Server;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\Attribute\Singleton;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\Exceptions\ExceptionReporterInterface;

#[Singleton]
final class ServerRunner implements ServerRunnerInterface
{
    /**
     * @var array<class-string>
     */
    private array $actions = [];

    /**
     * @var array<callable(McpItemsRegistry): void>
     */
    private array $dynamicToolRegistrars = [];

    public function __construct(
        #[Proxy] private readonly ScopeInterface $scope,
    ) {}

    /**
     * Register a new action class
     *
     * @param class-string $class
     */
    public function registerAction(string $class): void
    {
        $this->actions[] = $class;
    }

    /**
     * Register a callback that will be called to register dynamic tools.
     *
     * The callback receives the McpItemsRegistry and can register tools directly.
     *
     * @param callable(McpItemsRegistry): void $registrar
     */
    public function registerDynamicToolRegistrar(callable $registrar): void
    {
        $this->dynamicToolRegistrars[] = $registrar;
    }

    public function run(string $name): void
    {
        $this->scope->runScope(
            bindings: new Scope(
                name: 'mcp-server',
            ),
            scope: function (
                RouteRegistrar $registrar,
                McpItemsRegistry $registry,
                ExceptionReporterInterface $reporter,
                Server $server,
                ServerTransportInterface $transport,
            ) use ($name): void {
                // Register all classes with MCP item attributes. Should be before registering controllers!
                $registry->registerMany($this->actions);

                // Register dynamic tools via registered callbacks
                foreach ($this->dynamicToolRegistrars as $dynamicRegistrar) {
                    $dynamicRegistrar($registry);
                }

                // Register all controllers for routing
                $registrar->registerControllers($this->actions);

                try {
                    $server->listen($transport);
                } catch (\Throwable $e) {
                    $reporter->report($e);
                }
            },
        );
    }
}
