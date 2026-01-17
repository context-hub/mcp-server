<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Provider;

use Butschster\ContextGenerator\Lib\Variable\Provider\VariableProviderInterface;
use Butschster\ContextGenerator\McpServer\Tool\Config\ToolSchema;
use Butschster\ContextGenerator\McpServer\Tool\Exception\BlockedArgumentException;

/**
 * Provider for tool arguments passed during execution.
 * Supports type casting based on schema definition and flexible argument unpacking.
 */
final class ToolArgumentsProvider implements VariableProviderInterface
{
    /** @var array<string, mixed>|null */
    private ?array $mergedArguments = null;

    public function __construct(
        private readonly array $arguments,
        private readonly ?ToolSchema $schema = null,
    ) {}

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->getMergedArguments());
    }

    /**
     * Get a value with proper type casting based on schema (if available).
     * Falls back to default value from schema if property is not provided.
     */
    public function get(string $name): ?string
    {
        $merged = $this->getMergedArguments();

        if ($this->schema === null) {
            return isset($merged[$name]) ? (string) $merged[$name] : null;
        }

        return $this->castValueFromSchema(
            $name,
            $merged[$name] ?? $this->schema->getDefaultValue($name),
        );
    }

    /**
     * Validate all arguments against schema rules.
     * Checks for blocked arguments and throws exception if any are found.
     *
     * @throws BlockedArgumentException If a blocked argument is used
     */
    public function validateArguments(): void
    {
        if ($this->schema === null) {
            return;
        }

        $blockedProperties = $this->schema->getBlockedProperties();
        if (empty($blockedProperties)) {
            return;
        }

        foreach (\array_keys($this->getMergedArguments()) as $name) {
            if ($this->schema->isPropertyBlocked($name)) {
                throw new BlockedArgumentException($name, $blockedProperties);
            }
        }
    }

    /**
     * Get all arguments.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        $merged = $this->getMergedArguments();

        if ($this->schema === null) {
            return $merged;
        }

        $result = [];
        $properties = $this->schema->getProperties();

        // First apply any default values for properties not in arguments
        foreach ($properties as $name => $definition) {
            if (!$this->has($name) && isset($definition['default'])) {
                $result[$name] = $this->castValueFromSchema($name, $definition['default']);
            }
        }

        // Then add explicitly provided arguments
        foreach ($merged as $name => $value) {
            $isFlexibleArg = $this->schema->getFlexibleArg() === $name;
            if ($this->schema->allowsAnyProperties() || $this->schema->hasFlexibleArg() || isset($properties[$name])) {
                // Skip the flexible arg container itself
                if (!$isFlexibleArg) {
                    $result[$name] = $this->castValueFromSchema($name, $value);
                }
            }
        }

        return $result;
    }

    /**
     * Get merged arguments with lazy initialization.
     *
     * @return array<string, mixed>
     */
    private function getMergedArguments(): array
    {
        if ($this->mergedArguments === null) {
            $this->mergedArguments = $this->buildMergedArguments();
        }

        return $this->mergedArguments;
    }

    /**
     * Build merged arguments by unpacking flexible arg if present.
     *
     * @return array<string, mixed>
     */
    private function buildMergedArguments(): array
    {
        if ($this->schema === null || !$this->schema->hasFlexibleArg()) {
            return $this->arguments;
        }

        $flexibleArgName = $this->schema->getFlexibleArg();
        if (!isset($this->arguments[$flexibleArgName])) {
            return $this->arguments;
        }

        $flexibleValue = $this->arguments[$flexibleArgName];

        // Try to parse/extract from the flexible arg
        $unpacked = $this->parseFlexibleArg($flexibleValue);
        if ($unpacked === null) {
            return $this->arguments;
        }

        // Merge: unpacked arguments first, then explicit arguments override
        return \array_merge($unpacked, $this->arguments);
    }

    /**
     * Parse the flexible argument value as JSON or return array directly.
     *
     * @return array<string, mixed>|null
     */
    private function parseFlexibleArg(mixed $value): ?array
    {
        // If already an array (MCP client may parse JSON automatically)
        if (\is_array($value)) {
            return $value;
        }

        if (!\is_string($value)) {
            return null;
        }

        $trimmed = \trim($value);
        if ($trimmed === '') {
            return null;
        }

        // Must start with { or [
        if ($trimmed[0] !== '{' && $trimmed[0] !== '[') {
            return null;
        }

        $decoded = \json_decode($trimmed, true);
        if (\json_last_error() !== JSON_ERROR_NONE || !\is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Cast a value based on schema type definition.
     *
     * @param string $name The argument name
     * @param mixed $value The value to cast
     * @return string The cast value
     */
    private function castValueFromSchema(string $name, mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if ($this->schema === null) {
            return (string) $value;
        }

        $properties = $this->schema->getProperties();
        if (!isset($properties[$name])) {
            // For unpacked flexible args without schema definition
            if (\is_array($value)) {
                return \json_encode($value);
            }
            return (string) $value;
        }

        $propertyDef = $properties[$name];
        $type = $propertyDef['type'] ?? null;

        if ($type === null) {
            return 'null';
        }

        return match ($type) {
            'string' => $this->castToString($value),
            'number' => $this->castToFloat($value),
            'integer' => $this->castToInt($value),
            'boolean' => $this->castToBool($value),
            'array' => \json_encode($this->castToArray($value)),
            'object' => \json_encode($this->castToObject($value)),
            default => (string) $value,
        };
    }

    private function castToString(mixed $value): string
    {
        if (\is_array($value) || \is_object($value)) {
            return \json_encode($value) ?: '';
        }

        return (string) $value;
    }

    private function castToInt(mixed $value): string
    {
        if (\is_string($value) && \trim($value) === '') {
            return '0';
        }

        return (string) $value;
    }

    private function castToFloat(mixed $value): string
    {
        if (\is_string($value) && \trim($value) === '') {
            return '0.0';
        }

        return \number_format((float) $value, 2, '.', '');
    }

    private function castToBool(mixed $value): string
    {
        if (\is_string($value)) {
            $value = \strtolower(\trim($value));
            return ($value === 'true' || $value === '1' || $value === 'yes' || $value === 'y') ? 'true' : 'false';
        }

        return $value ? 'true' : 'false';
    }

    private function castToArray(mixed $value): array
    {
        if (\is_string($value)) {
            $decoded = \json_decode($value, true);
            if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }

            return \array_map('trim', \explode(',', $value));
        }

        if (\is_object($value)) {
            return (array) $value;
        }

        if (!\is_array($value)) {
            return [$value];
        }

        return $value;
    }

    private function castToObject(mixed $value): array
    {
        if (\is_string($value)) {
            $decoded = \json_decode($value, true);
            if (\json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }

            return [];
        }

        if (\is_object($value)) {
            return (array) $value;
        }

        if (!\is_array($value)) {
            return [];
        }

        return $value;
    }
}
