<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Prompt\PromptConfigFactory;
use Butschster\ContextGenerator\McpServer\Prompt\PromptRegistryInterface;
use Butschster\ContextGenerator\McpServer\Tool\ToolRegistryInterface;
use Mcp\Server\Registry;
use Psr\Log\LoggerInterface;
use Spiral\Core\Attribute\Proxy;

/**
 * Factory for creating ChangeHandlerRegistry with all handlers.
 */
final readonly class ChangeHandlerRegistryFactory
{
    public function __construct(
        #[Proxy] private ToolRegistryInterface $toolRegistry,
        #[Proxy] private PromptRegistryInterface $promptRegistry,
        #[Proxy] private PromptConfigFactory $promptFactory,
        #[Proxy] private Registry $mcpRegistry,
        #[Proxy] private LoggerInterface $logger,
    ) {}

    public function create(): ChangeHandlerRegistry
    {
        $registry = new ChangeHandlerRegistry();

        $registry->register(new ToolsChangeHandler(
            toolRegistry: $this->toolRegistry,
            mcpRegistry: $this->mcpRegistry,
            logger: $this->logger,
        ));

        $registry->register(new PromptsChangeHandler(
            promptRegistry: $this->promptRegistry,
            promptFactory: $this->promptFactory,
            mcpRegistry: $this->mcpRegistry,
            logger: $this->logger,
        ));

        return $registry;
    }
}
