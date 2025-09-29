<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Butschster\ContextGenerator\McpServer\Routing\RoutesFactory;
use Butschster\ContextGenerator\McpServer\Transport\StdioTransport;
use Mcp\Server\Configuration;
use Mcp\Server\Contracts\DispatcherInterface;
use Mcp\Server\Contracts\DispatcherRoutesFactoryInterface;
use Mcp\Server\Contracts\HttpServerInterface;
use Mcp\Server\Contracts\ReferenceProviderInterface;
use Mcp\Server\Contracts\ReferenceRegistryInterface;
use Mcp\Server\Contracts\ServerTransportInterface;
use Mcp\Server\Contracts\SessionHandlerInterface;
use Mcp\Server\Contracts\SessionIdGeneratorInterface;
use Mcp\Server\Contracts\ToolExecutorInterface;
use Mcp\Server\Defaults\ArrayCache;
use Mcp\Server\Defaults\FileCache;
use Mcp\Server\Defaults\ToolExecutor;
use Mcp\Server\Dispatcher;
use Mcp\Server\Dispatcher\Paginator;
use Mcp\Server\Protocol;
use Mcp\Server\Registry;
use Mcp\Server\Server;
use Mcp\Server\Session\ArraySessionHandler;
use Mcp\Server\Session\CacheSessionHandler;
use Mcp\Server\Session\SessionIdGenerator;
use Mcp\Server\Session\SessionManager;
use Mcp\Server\Session\SubscriptionManager;
use Mcp\Server\Transports\HttpServer;
use Mcp\Server\Transports\HttpServerTransport;
use Mcp\Server\Transports\StdioServerTransport;
use Mcp\Server\Transports\StreamableHttpServerTransport;
use PhpMcp\Schema\Implementation;
use PhpMcp\Schema\ServerCapabilities;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Boot\EnvironmentInterface;
use Spiral\Core\FactoryInterface;
use Spiral\Exceptions\ExceptionReporterInterface;
use Spiral\McpServer\Bootloader\ValinorMapperBootloader;
use Spiral\McpServer\MiddlewareManager;
use Spiral\McpServer\MiddlewareRegistryInterface;
use Spiral\McpServer\MiddlewareRepositoryInterface;

final class McpServerCoreBootloader extends Bootloader
{
    public function defineDependencies(): array
    {
        return [
            ValinorMapperBootloader::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            // Core Dependencies
            LoopInterface::class => static fn(): LoopInterface => Loop::get(),

            // Middleware Management
            MiddlewareRepositoryInterface::class => MiddlewareManager::class,
            MiddlewareRegistryInterface::class => MiddlewareManager::class,

            // Session Management
            SessionIdGeneratorInterface::class => SessionIdGenerator::class,
            SessionHandlerInterface::class => $this->createSessionHandler(...),
            SessionManager::class => $this->createSessionManager(...),
            SubscriptionManager::class => $this->createSubscriptionManager(...),

            // Cache and Storage
            CacheInterface::class => $this->createCache(...),

            // Registry and Tools
            ReferenceProviderInterface::class => Registry::class,
            ReferenceRegistryInterface::class => Registry::class,
            Registry::class => $this->createRegistry(...),
            ToolExecutorInterface::class => $this->createToolExecutor(...),

            // Configuration
            Configuration::class => $this->createMcpConfiguration(...),

            // Pagination
            Paginator::class => $this->createPaginator(...),

            // Routing and Dispatch
            DispatcherRoutesFactoryInterface::class => RoutesFactory::class,
            DispatcherInterface::class => Dispatcher::class,
            Dispatcher::class => $this->createDispatcher(...),

            // Protocol
            Protocol::class => $this->createProtocol(...),

            // Transport
            HttpServerInterface::class => $this->createHttpServer(...),
            ServerTransportInterface::class => $this->createTransport(...),

            // Main Server
            Server::class => Server::class,
        ];
    }

    private function createSessionHandler(
        EnvironmentInterface $env,
        CacheInterface $cache,
    ): SessionHandlerInterface {
        $sessionType = $env->get('MCP_SESSION_TYPE', 'array');
        $ttl = (int)$env->get('MCP_SESSION_TTL', 3600);

        return match ($sessionType) {
            'cache' => new CacheSessionHandler($cache, $ttl),
            default => new ArraySessionHandler($ttl),
        };
    }

    private function createSessionManager(
        SessionHandlerInterface $sessionHandler,
        LoggerInterface $logger,
        LoopInterface $loop,
        EnvironmentInterface $env,
    ): SessionManager {
        return new SessionManager(
            handler: $sessionHandler,
            logger: $logger,
            loop: $loop,
            ttl: (int)$env->get('MCP_SESSION_TTL', 3600),
            gcInterval: (float)$env->get('MCP_SESSION_GC_INTERVAL', 300),
        );
    }

    private function createSubscriptionManager(LoggerInterface $logger): SubscriptionManager
    {
        return new SubscriptionManager($logger);
    }

    private function createCache(
        EnvironmentInterface $env,
        DirectoriesInterface $dirs,
    ): CacheInterface {
        $cacheType = $env->get('MCP_CACHE_TYPE', 'array');

        return match ($cacheType) {
            'file' => new FileCache($dirs->get('runtime') . 'cache/mcp'),
            default => new ArrayCache(),
        };
    }

    private function createRegistry(
        LoggerInterface $logger,
    ): Registry {
        return new Registry($logger);
    }

    private function createToolExecutor(
        ReferenceRegistryInterface $registry,
        LoggerInterface $logger,
    ): ToolExecutorInterface {
        return new ToolExecutor($registry, $logger);
    }

    private function createMcpConfiguration(
        EnvironmentInterface $env,
    ): Configuration {
        return new Configuration(
            serverInfo: Implementation::make(
                name: trim((string)$env->get('MCP_SERVER_NAME', 'Spiral MCP Server')),
                version: trim((string)$env->get('MCP_SERVER_VERSION', '1.0.0')),
            ),
            capabilities: ServerCapabilities::make(
                tools: $env->get('MCP_ENABLE_TOOLS', true),
                toolsListChanged: $env->get('MCP_ENABLE_TOOLS_LIST_CHANGED', true),
                resources: $env->get('MCP_ENABLE_RESOURCES', true),
                resourcesSubscribe: $env->get('MCP_ENABLE_RESOURCES_SUBSCRIBE', true),
                resourcesListChanged: $env->get('MCP_ENABLE_RESOURCES_LIST_CHANGED', true),
                prompts: $env->get('MCP_ENABLE_PROMPTS', true),
                promptsListChanged: $env->get('MCP_ENABLE_PROMPTS_LIST_CHANGED', true),
                logging: $env->get('MCP_ENABLE_LOGGING', true),
                completions: $env->get('MCP_ENABLE_COMPLETIONS', true),
                experimental: (array)$env->get('MCP_EXPERIMENTAL_CAPABILITIES', []),
            ),
            instructions: $env->get('MCP_INSTRUCTIONS'),
        );
    }

    private function createPaginator(EnvironmentInterface $env): Paginator
    {
        return new Paginator(
            paginationLimit: (int)$env->get('MCP_PAGINATION_LIMIT', 50),
        );
    }

    private function createDispatcher(
        LoggerInterface $logger,
        RoutesFactory $routesFactory,
    ): Dispatcher {
        return new Dispatcher(
            logger: $logger,
            routesFactory: $routesFactory,
        );
    }

    private function createProtocol(
        FactoryInterface $factory,
        LoggerInterface $logger,
        ExceptionReporterInterface $reporter,
    ): Protocol {
        return $factory->make(Protocol::class, [
            'logger' => $logger,
            'reporter' => new class($reporter) implements \Mcp\Server\Exception\ExceptionReporterInterface {
                public function __construct(
                    private readonly ExceptionReporterInterface $reporter,
                ) {}

                public function report(\Throwable $e): void
                {
                    $this->reporter->report($e);
                }
            },
        ]);
    }

    private function createHttpServer(
        EnvironmentInterface $env,
        LoopInterface $loop,
        MiddlewareRepositoryInterface $middleware,
        LoggerInterface $logger,
    ): HttpServerInterface {
        $host = $env->get('MCP_HOST', '127.0.0.1');
        $port = (int)$env->get('MCP_PORT', 8090);
        $mcpPath = $env->get('MCP_PATH', '/mcp');

        return new HttpServer(
            loop: $loop,
            host: $host,
            port: $port,
            mcpPath: $mcpPath,
            sslContext: $env->get('MCP_SSL_CONTEXT'),
            middleware: $middleware->all(),
            logger: $logger,
            runLoop: true,
        );
    }

    private function createTransport(
        ContainerInterface $container,
        EnvironmentInterface $env,
        LoopInterface $loop,
        SessionIdGeneratorInterface $sessionIdGenerator,
        LoggerInterface $logger,
    ): ServerTransportInterface {
        $transportType = $env->get('MCP_TRANSPORT', 'stdio');

        return match ($transportType) {
            'http' => $this->createHttpTransport(
                httpServer: $container->get(HttpServerInterface::class),
                sessionIdGenerator: $sessionIdGenerator,
                logger: $logger,
            ),
            'streamable' => $this->createStreamableHttpTransport(
                httpServer: $container->get(HttpServerInterface::class),
                env: $env,
                sessionIdGenerator: $sessionIdGenerator,
                logger: $logger,
            ),
            'stdio' => new StdioTransport(
                logger: $logger,
            ),
            default => throw new \InvalidArgumentException("Unknown transport type: {$transportType}")
        };
    }

    private function createHttpTransport(
        HttpServerInterface $httpServer,
        SessionIdGeneratorInterface $sessionIdGenerator,
        LoggerInterface $logger,
    ): HttpServerTransport {
        return new HttpServerTransport(
            httpServer: $httpServer,
            sessionId: $sessionIdGenerator,
            logger: $logger,
        );
    }

    private function createStreamableHttpTransport(
        HttpServerInterface $httpServer,
        EnvironmentInterface $env,
        SessionIdGeneratorInterface $sessionIdGenerator,
        LoggerInterface $logger,
    ): StreamableHttpServerTransport {
        return new StreamableHttpServerTransport(
            httpServer: $httpServer,
            sessionId: $sessionIdGenerator,
            logger: $logger,
            enableJsonResponse: (bool)$env->get('MCP_ENABLE_JSON_RESPONSE', true),
            stateless: (bool)$env->get('MCP_STATELESS', false),
        );
    }
}
