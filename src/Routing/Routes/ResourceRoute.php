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
use Mcp\Server\Session\SubscriptionManager;
use PhpMcp\Schema\JsonRpc\Notification;
use PhpMcp\Schema\JsonRpc\Request;
use PhpMcp\Schema\JsonRpc\Result;
use PhpMcp\Schema\Request\ListResourcesRequest;
use PhpMcp\Schema\Request\ListResourceTemplatesRequest;
use PhpMcp\Schema\Request\ReadResourceRequest;
use PhpMcp\Schema\Request\ResourceSubscribeRequest;
use PhpMcp\Schema\Request\ResourceUnsubscribeRequest;
use PhpMcp\Schema\Result\EmptyResult;
use PhpMcp\Schema\Result\ListResourcesResult;
use PhpMcp\Schema\Result\ListResourceTemplatesResult;
use PhpMcp\Schema\Result\ReadResourceResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ResourceRoute implements RouteInterface
{
    public function __construct(
        private Router $router,
        private SubscriptionManager $subscriptionManager,
        private LoggerInterface $logger = new NullLogger(),
        private Mcp2PsrRequestAdapter $requestFactory = new Mcp2PsrRequestAdapter(),
    ) {}

    public function getMethods(): array
    {
        return [
            RequestMethod::ResourcesList->value,
            RequestMethod::ResourcesTemplatesList->value,
            RequestMethod::ResourcesRead->value,
            RequestMethod::ResourcesSubscribe->value,
            RequestMethod::ResourcesUnsubscribe->value,
        ];
    }

    public function handleRequest(Request $request, Context $context): Result
    {
        return match ($request->method) {
            RequestMethod::ResourcesList->value => $this->handleResourcesList(
                ListResourcesRequest::fromRequest($request),
            ),
            RequestMethod::ResourcesTemplatesList->value => $this->handleResourceTemplateList(
                ListResourceTemplatesRequest::fromRequest($request),
            ),
            RequestMethod::ResourcesRead->value => $this->handleResourceRead(
                ReadResourceRequest::fromRequest($request),
                $context,
            ),
            RequestMethod::ResourcesSubscribe->value => $this->handleResourceSubscribe(
                ResourceSubscribeRequest::fromRequest($request),
                $context,
            ),
            RequestMethod::ResourcesUnsubscribe->value => $this->handleResourceUnsubscribe(
                ResourceUnsubscribeRequest::fromRequest($request),
                $context,
            ),
        };
    }

    public function handleNotification(Notification $notification, Context $context): void
    {
        // No notifications handled by this route
    }

    private function handleResourcesList(ListResourcesRequest $request): ListResourcesResult
    {
        $method = RequestMethod::ResourcesList->value;
        $params = [];

        $this->logger->debug("Handling route: $method", $params);

        // Create PSR request from MCP method and params
        $request = $this->requestFactory->createPsrRequest($method, $params);

        // Dispatch the request through the router
        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logger->error('Route handling error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new McpServerException(
                message: 'Failed to list resources.',
                previous: $e,
            );
        }
    }

    private function handleResourceTemplateList(ListResourceTemplatesRequest $request): ListResourceTemplatesResult
    {
        $method = RequestMethod::ResourcesTemplatesList->value;
        $params = [];

        $this->logger->debug("Handling route: $method", $params);

        // Create PSR request from MCP method and params
        $request = $this->requestFactory->createPsrRequest($method, $params);

        // Dispatch the request through the router
        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            return $response->getPayload();
        } catch (\Throwable $e) {
            $this->logger->error('Route handling error', [
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new McpServerException(
                message: 'Failed to list resources.',
                previous: $e,
            );
        }
    }

    /**
     * @throws McpServerException
     */
    private function handleResourceRead(ReadResourceRequest $request, Context $context): ReadResourceResult
    {
        $uri = $request->uri;
        [$type, $path] = \explode('://', $uri, 2);

        $method = 'resource/' . $type . '/' . $path;

        $this->logger->debug('Handling resource read', [
            'resource' => $uri,
            'type' => $type,
            'path' => $path,
            'method' => $method,
        ]);


        // Create PSR request with the tool name in the path and arguments as POST body
        $request = $this->requestFactory->createPsrRequest($method);

        try {
            $response = $this->router->dispatch($request);
            \assert($response instanceof JsonResponse);

            return $response->getPayload();
        } catch (\JsonException $e) {
            $this->logger->warning('Failed to JSON encode resource content.', ['exception' => $e, 'uri' => $uri]);
            throw McpServerException::internalError("Failed to serialize resource content for '{$uri}'.", $e);
        } catch (McpServerException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Resource read error', [
                'resource' => $uri,
                'error' => $e->getMessage(),
            ]);

            throw McpServerException::resourceReadFailed($uri, $e);
        }
    }

    private function handleResourceSubscribe(ResourceSubscribeRequest $request, Context $context): EmptyResult
    {
        $this->subscriptionManager->subscribe($context->session->getId(), $request->uri);
        return new EmptyResult();
    }

    private function handleResourceUnsubscribe(ResourceUnsubscribeRequest $request, Context $context): EmptyResult
    {
        $this->subscriptionManager->unsubscribe($context->session->getId(), $request->uri);
        return new EmptyResult();
    }
}
