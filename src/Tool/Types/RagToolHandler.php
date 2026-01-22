<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Types;

use Butschster\ContextGenerator\Application\Logger\LoggerPrefix;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\Exception\ToolExecutionException;
use Butschster\ContextGenerator\McpServer\Tool\RagToolExecutorInterface;
use Psr\Log\LoggerInterface;

/**
 * Handler for RAG-type tools.
 *
 * Executes search and store operations against RAG collections
 * based on tool configuration from context.yaml.
 */
#[LoggerPrefix(prefix: 'tool.rag')]
final readonly class RagToolHandler extends AbstractToolHandler
{
    public function __construct(
        private ?RagToolExecutorInterface $executor = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($logger);
    }

    public function supports(string $type): bool
    {
        return $type === 'rag';
    }

    protected function doExecute(ToolDefinition $tool, array $arguments = []): array
    {
        if ($this->executor === null) {
            throw new ToolExecutionException('RAG tool executor is not configured. Ensure RAG module is enabled.');
        }

        $collection = $tool->extra['collection'] ?? null;
        if ($collection === null || !\is_string($collection)) {
            throw new ToolExecutionException('RAG tool must have a "collection" property');
        }

        $operations = $tool->extra['operations'] ?? ['search', 'store'];
        if (!\is_array($operations)) {
            throw new ToolExecutionException('RAG tool "operations" must be an array');
        }

        // Validate collection exists
        if (!$this->executor->hasCollection($collection)) {
            throw new ToolExecutionException(
                \sprintf('RAG collection "%s" does not exist', $collection),
            );
        }

        // Determine operation based on tool ID or arguments
        $operation = $this->determineOperation($tool, $operations, $arguments);

        $this->logger?->info('Executing RAG operation', [
            'tool' => $tool->id,
            'collection' => $collection,
            'operation' => $operation,
        ]);

        return match ($operation) {
            'search' => $this->executor->search($collection, $arguments),
            'store' => $this->executor->store($collection, $arguments),
            default => throw new ToolExecutionException(
                \sprintf('Unknown RAG operation "%s"', $operation),
            ),
        };
    }

    /**
     * Determine which operation to execute based on tool config and arguments.
     */
    private function determineOperation(ToolDefinition $tool, array $operations, array $arguments): string
    {
        // If only one operation is allowed, use it
        if (\count($operations) === 1) {
            return $operations[0];
        }

        // Check if operation is explicitly specified in arguments
        if (isset($arguments['_operation']) && \in_array($arguments['_operation'], $operations, true)) {
            return $arguments['_operation'];
        }

        // Infer from tool ID suffix
        if (\str_ends_with($tool->id, '-store')) {
            return 'store';
        }

        if (\str_ends_with($tool->id, '-search')) {
            return 'search';
        }

        // Infer from arguments: if 'content' is present, it's likely a store operation
        if (isset($arguments['content']) && !isset($arguments['query'])) {
            return 'store';
        }

        // Default to search
        return 'search';
    }
}
