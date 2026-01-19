<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiff;

/**
 * Handles configuration changes for a specific section.
 */
interface ChangeHandlerInterface
{
    /**
     * Get the configuration section this handler manages.
     * Must match key in context.yaml (e.g., 'tools', 'prompts').
     */
    public function getSection(): string;

    /**
     * Apply changes from configuration diff.
     *
     * @param ConfigDiff $diff The calculated differences
     * @return bool True if any changes were applied
     */
    public function apply(ConfigDiff $diff): bool;

    /**
     * Full reload - clear and re-register all items.
     * Used when diff calculation is not possible.
     *
     * @param array<int, array<string, mixed>> $items New configuration items
     * @return bool True if any changes were made
     */
    public function reload(array $items): bool;
}
