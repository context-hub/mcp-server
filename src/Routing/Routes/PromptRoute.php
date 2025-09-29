<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Routing\Routes;

use Butschster\ContextGenerator\McpServer\Routing\Mcp2PsrRequestAdapter;
use Laminas\Diactoros\Response\JsonResponse;
use League\Route\Router;
use Mcp\Server\Context;
use Mcp\Server\Contracts\RouteInterface;
use Mcp\Server\Dispatcher\RequestMethod;
use Mcp\Server\Exception\McpServerException;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\GetPromptRequest;
use PhpMcp\Schema\Request\ListPromptsRequest;
use PhpMcp\Schema\Result\GetPromptResult;
use PhpMcp\Schema\Result\ListPromptsResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class PromptRoute implements RouteInterface
{
    public function __construct(
        private Router $router,
        private LoggerInterface $logger = new NullLogger(),
        private Mcp2PsrRequestAdapter $requestFactory = new Mcp2PsrRequestAdapter(),
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::PromptsList->value,
            RequestMethod::PromptsGet->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::PromptsList->value => $this->handlePromptsList(
                ListPromptsRequest::fromRequest($request),
            ),
            RequestMethod::PromptsGet->value => $this->handlePromptGet(
                GetPromptRequest::fromRequest($request),
                $context,
            ),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handlePromptsList(ListPromptsRequest $request): ListPromptsResult
    {
        $method = RequestMethod::PromptsList->value;

        // Create PSR request from MCP method and params
        $request = $this->requestFactory->createPsrRequest($method, []);

        // Dispatch the request through the router
        $response = $this->router->dispatch($request);
        \assert($response instanceof JsonResponse);

        // Convert the response back to appropriate MCP type
        return $response->getPayload();
    }

    /**
     * @throws McpServerException
     */
    private function handlePromptGet(GetPromptRequest $request, Context $context): GetPromptResult
    {
        $name = $request->name;
        $arguments = $request->arguments;

        $method = 'prompt/' . $name;

        $this->logger->debug('Handling prompt get', [
            'prompt' => $name,
            'method' => $method,
            'arguments' => $arguments,
        ]);

        $request = $this->requestFactory->createPsrRequest($method, $arguments);

        $response = $this->router->dispatch($request);
        \assert($response instanceof JsonResponse);

        // Convert the response back to appropriate MCP type
        return $response->getPayload();
    }
}
