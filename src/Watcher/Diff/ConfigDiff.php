<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Diff;

/**
 * Immutable DTO representing the difference between two configuration states.
 *
 * @template T of array
 */
final readonly class ConfigDiff
{
    /**
     * @param array<string, T> $added Items present in new but not in old (keyed by ID)
     * @param array<string, T> $removed Items present in old but not in new (keyed by ID)
     * @param array<string, T> $modified Items present in both but with different values (keyed by ID)
     * @param array<string, T> $unchanged Items identical in both (keyed by ID)
     */
    public function __construct(
        public array $added = [],
        public array $removed = [],
        public array $modified = [],
        public array $unchanged = [],
    ) {}

    /**
     * Check if there are any changes.
     */
    public function hasChanges(): bool
    {
        return $this->added !== []
            || $this->removed !== []
            || $this->modified !== [];
    }

    /**
     * Get total number of changes.
     */
    public function changeCount(): int
    {
        return \count($this->added)
            + \count($this->removed)
            + \count($this->modified);
    }

    /**
     * Get human-readable summary for logging.
     */
    public function getSummary(): string
    {
        if (!$this->hasChanges()) {
            return 'No changes';
        }

        $parts = [];

        if ($this->added !== []) {
            $parts[] = \sprintf('%d added', \count($this->added));
        }

        if ($this->removed !== []) {
            $parts[] = \sprintf('%d removed', \count($this->removed));
        }

        if ($this->modified !== []) {
            $parts[] = \sprintf('%d modified', \count($this->modified));
        }

        return \implode(', ', $parts);
    }

    /**
     * Create empty diff (no changes).
     */
    public static function empty(): self
    {
        return new self();
    }
}
