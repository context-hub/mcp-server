<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;

interface ToolRegistryInterface
{
    /**
     * Registers a tool in the registry (upsert semantics - replaces if exists).
     */
    public function register(ToolDefinition $tool): void;

    /**
     * Removes a tool from the registry by ID.
     *
     * @return bool True if the tool existed and was removed, false otherwise
     */
    public function remove(string $id): bool;

    /**
     * Checks if a tool with the given ID exists.
     */
    public function has(string $id): bool;

    /**
     * Removes all tools from the registry.
     */
    public function clear(): void;
}
