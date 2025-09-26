<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Registry;

use Butschster\ContextGenerator\McpServer\Attribute\Prompt;
use Butschster\ContextGenerator\McpServer\Attribute\Resource as ResourceAttr;
use Butschster\ContextGenerator\McpServer\Attribute\Tool;
use Butschster\ContextGenerator\McpServer\Tool\ToolAttributesParser;
use Mcp\Server\Contracts\ReferenceRegistryInterface;
use Psr\Log\LoggerInterface;

final readonly class McpItemsRegistry
{
    public function __construct(
        private ReferenceRegistryInterface $registry,
        private ToolAttributesParser $toolAttributesParser,
        private LoggerInterface $logger,
    ) {}

    /**
     * Register a class and scan for MCP item attributes
     */
    public function register(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        // Check for Prompt attribute
        $promptAttributes = $reflection->getAttributes(Prompt::class);
        if (!empty($promptAttributes)) {
            $prompt = $promptAttributes[0]->newInstance();

            $schema = new \PhpMcp\Schema\Prompt(
                name: $prompt->name,
                description: $prompt->description,
            );

            $this->registry->registerPrompt($schema, new NullHandler());

            $this->logger->info('Registered prompt', [
                'name' => $prompt->name,
                'description' => $prompt->description,
            ]);
        }

        // Check for Resource attribute
        $resourceAttributes = $reflection->getAttributes(ResourceAttr::class);
        if (!empty($resourceAttributes)) {
            $resource = $resourceAttributes[0]->newInstance();

            $this->registry->registerResource(
                new Resource(
                    uri: $resource->uri,
                    name: $resource->name,
                    description: $resource->description,
                    mimeType: $resource->mimeType,
                ),
                new NullHandler(),
            );

            $this->logger->info('Registered resource', [
                'name' => $resource->name,
                'uri' => $resource->uri,
                'description' => $resource->description,
                'mimeType' => $resource->mimeType,
            ]);
        }

        // Check for Tool attribute
        $toolAttributes = $reflection->getAttributes(Tool::class);
        if (!empty($toolAttributes)) {
            $tool = $toolAttributes[0]->newInstance();

            $this->registry->registerTool(
                $this->toolAttributesParser->parse($className),
                new NullHandler(),
            );

            $this->logger->info('Registered tool', [
                'name' => $tool->name,
                'description' => $tool->description,
            ]);
        }
    }

    /**
     * Register multiple classes at once
     */
    public function registerMany(array $classNames): void
    {
        foreach ($classNames as $className) {
            $this->register($className);
        }
    }
}
