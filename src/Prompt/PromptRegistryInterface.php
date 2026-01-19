<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Prompt;

use Butschster\ContextGenerator\McpServer\Prompt\Extension\PromptDefinition;

interface PromptRegistryInterface
{
    /**
     * Registers a prompt in the registry (upsert semantics - replaces if exists).
     */
    public function register(PromptDefinition $prompt): void;

    /**
     * Removes a prompt from the registry by ID.
     *
     * @return bool True if the prompt existed and was removed, false otherwise
     */
    public function remove(string $id): bool;

    /**
     * Checks if a prompt with the given ID exists.
     */
    public function has(string $id): bool;

    /**
     * Removes all prompts from the registry.
     */
    public function clear(): void;
}
