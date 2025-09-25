<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Butschster\ContextGenerator\McpServer\Routing\Routes\PromptRoute;
use Butschster\ContextGenerator\McpServer\Routing\Routes\ResourceRoute;
use Butschster\ContextGenerator\McpServer\Routing\Routes\ToolRoute;
use League\Route\Router;
use Mcp\Server\Configuration;
use Mcp\Server\Contracts\DispatcherRoutesFactoryInterface;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Dispatcher\Routes\CompletionRoute;
use Mcp\Server\Dispatcher\Routes\InitializeRoute;
use Mcp\Server\Dispatcher\Routes\LoggingRoute;
use Mcp\Server\Session\SubscriptionManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class RoutesFactory implements DispatcherRoutesFactoryInterface
{
    public function __construct(
        private Router $router,
        private Configuration $configuration,
        private ReferenceProviderInterface $registry,
        private SubscriptionManager $subscriptionManager,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function create(): array
    {
        return [
            new InitializeRoute(
                configuration: $this->configuration,
            ),
            new ToolRoute(
                router: $this->router,
                logger: $this->logger,
            ),
            new ResourceRoute(
                router: $this->router,
                subscriptionManager: $this->subscriptionManager,
                logger: $this->logger,
            ),
            new PromptRoute(
                router: $this->router,
                logger: $this->logger,
            ),
            new LoggingRoute(
                logger: $this->logger,
            ),
            new CompletionRoute(
                registry: $this->registry,
            ),
        ];
    }
}
