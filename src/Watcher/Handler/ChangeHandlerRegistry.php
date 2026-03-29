<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

/**
 * Registry for managing change handlers.
 */
final class ChangeHandlerRegistry
{
    /** @var array<string, ChangeHandlerInterface> */
    private array $handlers = [];

    public function register(ChangeHandlerInterface $handler): void
    {
        $this->handlers[$handler->getSection()] = $handler;
    }

    public function get(string $section): ?ChangeHandlerInterface
    {
        return $this->handlers[$section] ?? null;
    }

    public function has(string $section): bool
    {
        return isset($this->handlers[$section]);
    }

    /**
     * @return array<string, ChangeHandlerInterface>
     */
    public function all(): array
    {
        return $this->handlers;
    }

    /**
     * @return string[]
     */
    public function getSupportedSections(): array
    {
        return \array_keys($this->handlers);
    }
}
