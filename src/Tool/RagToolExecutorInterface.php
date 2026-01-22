<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

/**
 * Interface for executing RAG tool operations.
 *
 * This interface allows the main application to provide RAG execution logic
 * without coupling the MCP server to RAG implementation details.
 */
interface RagToolExecutorInterface
{
    /**
     * Execute a RAG search operation.
     *
     * @param string $collection The collection to search in
     * @param array<string, mixed> $arguments Search arguments (query, type, sourcePath, limit)
     * @return array{output: string, success: bool} Execution result
     */
    public function search(string $collection, array $arguments): array;

    /**
     * Execute a RAG store operation.
     *
     * @param string $collection The collection to store in
     * @param array<string, mixed> $arguments Store arguments (content, type, sourcePath, tags)
     * @return array{output: string, success: bool} Execution result
     */
    public function store(string $collection, array $arguments): array;

    /**
     * Check if a collection exists.
     */
    public function hasCollection(string $collection): bool;
}
