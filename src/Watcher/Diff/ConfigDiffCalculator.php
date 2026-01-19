<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Diff;

/**
 * Calculates differences between configuration states.
 */
final readonly class ConfigDiffCalculator
{
    /**
     * Calculate diff for a specific config section (tools, prompts, etc.).
     *
     * @param array<int, array<string, mixed>> $oldItems Old configuration items
     * @param array<int, array<string, mixed>> $newItems New configuration items
     * @param string $idKey Key used to identify items (default: 'id')
     */
    public function calculate(array $oldItems, array $newItems, string $idKey = 'id'): ConfigDiff
    {
        $oldById = $this->indexById($oldItems, $idKey);
        $newById = $this->indexById($newItems, $idKey);

        $oldIds = \array_keys($oldById);
        $newIds = \array_keys($newById);

        // Find added (in new, not in old)
        $addedIds = \array_diff($newIds, $oldIds);
        $added = \array_intersect_key($newById, \array_flip($addedIds));

        // Find removed (in old, not in new)
        $removedIds = \array_diff($oldIds, $newIds);
        $removed = \array_intersect_key($oldById, \array_flip($removedIds));

        // Find modified and unchanged (in both)
        $commonIds = \array_intersect($oldIds, $newIds);

        $modified = [];
        $unchanged = [];

        foreach ($commonIds as $id) {
            if ($this->itemsEqual($oldById[$id], $newById[$id])) {
                $unchanged[$id] = $newById[$id];
            } else {
                $modified[$id] = $newById[$id];
            }
        }

        return new ConfigDiff(
            added: $added,
            removed: $removed,
            modified: $modified,
            unchanged: $unchanged,
        );
    }

    /**
     * Calculate diff for entire config (all sections).
     *
     * @return array<string, ConfigDiff> Section name => diff (only sections with changes)
     */
    public function calculateAll(array $oldConfig, array $newConfig): array
    {
        $sections = [
            'tools' => 'id',
            'prompts' => 'id',
            'documents' => 'description',
        ];

        $diffs = [];

        foreach ($sections as $section => $idKey) {
            $oldItems = $oldConfig[$section] ?? [];
            $newItems = $newConfig[$section] ?? [];

            $diff = $this->calculate($oldItems, $newItems, $idKey);

            if ($diff->hasChanges()) {
                $diffs[$section] = $diff;
            }
        }

        return $diffs;
    }

    /**
     * Index array items by their ID field.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $items, string $idKey): array
    {
        $indexed = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $id = $item[$idKey] ?? null;

            if ($id === null || $id === '') {
                // Generate ID from hash if not present
                $id = \md5(\json_encode($item, \JSON_THROW_ON_ERROR));
            }

            $indexed[(string) $id] = $item;
        }

        return $indexed;
    }

    /**
     * Compare two items for equality (order-independent).
     */
    private function itemsEqual(array $a, array $b): bool
    {
        $normalizedA = $this->normalizeForComparison($a);
        $normalizedB = $this->normalizeForComparison($b);

        return $normalizedA === $normalizedB;
    }

    /**
     * Normalize array for consistent comparison (recursive key sorting).
     */
    private function normalizeForComparison(array $data): array
    {
        \ksort($data);

        foreach ($data as $key => $value) {
            if (\is_array($value)) {
                $data[$key] = $this->normalizeForComparison($value);
            }
        }

        return $data;
    }
}
