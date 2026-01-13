<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing;

use Butschster\ContextGenerator\McpServer\Attribute\Guarded;
use Butschster\ContextGenerator\McpServer\Attribute\InputSchema;
use Butschster\ContextGenerator\McpServer\Interceptor\InterceptorPipeline;
use Mcp\Server\Authentication\Contract\UserProviderInterface;
use Mcp\Server\Authentication\Error\InvalidTokenError;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Core\Attribute\Proxy;
use Spiral\Core\InvokerInterface;
use Spiral\Core\Scope;
use Spiral\Core\ScopeInterface;
use Spiral\McpServer\SchemaMapperInterface;

final readonly class ActionCaller
{
    public function __construct(
        #[Proxy] private ScopeInterface $container,
        #[Proxy] private UserProviderInterface $userProvider,
        private SchemaMapperInterface $schemaMapper,
        private InterceptorPipeline $interceptorPipeline,
        private string $class,
    ) {}

    public function __invoke(ServerRequestInterface $request): mixed
    {
        $bindings = [
            ServerRequestInterface::class => $request,
        ];

        $reflection = new \ReflectionClass($this->class);

        // Parse input schema
        $input = null;
        $inputSchemaClass = $reflection->getAttributes(InputSchema::class)[0] ?? null;
        if ($inputSchemaClass !== null) {
            $inputSchema = $inputSchemaClass->newInstance();

            $input = $this->schemaMapper->toObject(
                json: \json_encode((array) ($request->getParsedBody() ?? []), \JSON_FORCE_OBJECT),
                class: $inputSchema->class,
            );

            $bindings[$inputSchema->class] = $input;
        }

        // Check authentication
        $authRequired = $reflection->getAttributes(Guarded::class)[0] ?? null;
        if ($authRequired !== null && $this->userProvider->getUser() === null) {
            throw new InvalidTokenError();
        }

        // Build action callable
        $action = fn(): mixed => $this->container->runScope(
            bindings: new Scope(
                name: 'mcp-server-request',
                bindings: $bindings,
            ),
            scope: fn(InvokerInterface $invoker): mixed => $invoker->invoke([$this->class, '__invoke']),
        );

        // Execute through interceptor pipeline
        if ($input !== null) {
            return $this->interceptorPipeline->execute($input, $action);
        }

        return $action();
    }
}
