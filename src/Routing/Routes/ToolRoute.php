<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Routes;

use Butschster\ContextGenerator\McpServer\Routing\Mcp2PsrRequestAdapter;
use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Router;
use Mcp\Server\Context;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Dispatcher\RequestMethod;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\CallToolRequest;
use PhpMcp\Schema\Request\ListToolsRequest;
use PhpMcp\Schema\Result\CallToolResult;
use PhpMcp\Schema\Result\ListToolsResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ToolRoute implements RouteInterface
{
    public function __construct(
        private Router $router,
        private LoggerInterface $logger = new NullLogger(),
        private Mcp2PsrRequestAdapter $requestFactory = new Mcp2PsrRequestAdapter(),
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::ToolsList->value,
            RequestMethod::ToolsCall->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::ToolsList->value => $this->handleToolList(ListToolsRequest::fromRequest($request)),
            RequestMethod::ToolsCall->value => $this->handleToolCall(CallToolRequest::fromRequest($request), $context),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handleToolList(ListToolsRequest $request): ListToolsResult
    {
        $method = RequestMethod::ToolsList->value;
        $params = [];

        $this->logger->debug("Handling route: $method", $params);

        // Create PSR request from MCP method and params
        $request = $this->requestFactory->createPsrRequest($method, $params);

        // Dispatch the request through the router
        $response = $this->router->dispatch($request);
        \assert($response instanceof JsonResponse);

        return $response->getPayload();
    }

    private function handleToolCall(CallToolRequest $request, Context $context): CallToolResult
    {
        $method = 'tools/call/' . $request->name;
        $arguments = $request->arguments ?? [];

        $this->logger->debug('Handling tool call', [
            'tool' => $request->name,
            'method' => $method,
            'arguments' => $arguments,
        ]);

        // Create PSR request with the tool name in the path and arguments as POST body
        $request = $this->requestFactory->createPsrRequest($method, $arguments);

        $response = $this->router->dispatch($request);
        \assert($response instanceof JsonResponse);

        return $response->getPayload();
    }
}
