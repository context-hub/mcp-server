<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Config;

/**
 * Represents a JSON schema for tool arguments.
 */
final readonly class ToolSchema implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $schema The JSON schema definition
     * @param bool $allowAny When true, any arguments are allowed (except blocked ones)
     * @param list<string> $blockedProperties List of property names that are not allowed
     * @param string|null $flexibleArg Name of the argument that contains JSON with additional arguments
     */
    public function __construct(
        private array $schema,
        private bool $allowAny = false,
        private array $blockedProperties = [],
        private ?string $flexibleArg = null,
    ) {}

    /**
     * Creates a ToolSchema from a configuration array.
     *
     * @param array<string, mixed> $config The schema configuration
     * @throws \InvalidArgumentException If the configuration is invalid
     */
    public static function fromArray(array $config): ?self
    {
        // Extract custom fields before processing schema
        $allowAny = (bool) ($config['allowAny'] ?? false);
        $flexibleArg = isset($config['flexibleArg']) && \is_string($config['flexibleArg'])
            ? $config['flexibleArg']
            : null;

        $blockedProperties = [];
        if (isset($config['blockedProperties']) && \is_array($config['blockedProperties'])) {
            $blockedProperties = \array_values(
                \array_filter(
                    $config['blockedProperties'],
                    static fn($v) => \is_string($v) && $v !== '',
                ),
            );
        }

        // Remove non-schema keys for JSON Schema compliance
        unset($config['allowAny'], $config['blockedProperties'], $config['flexibleArg']);

        if (empty($config)) {
            $config = ['properties' => new \stdClass()];
        }

        // Validate basic schema structure
        if (!isset($config['type'])) {
            $config['type'] = 'object';
        }

        return new self($config, $allowAny, $blockedProperties, $flexibleArg);
    }

    /**
     * Gets the required properties from the schema.
     *
     * @return array<string> List of required property names
     */
    public function getRequiredProperties(): array
    {
        return $this->schema['required'] ?? [];
    }

    /**
     * Gets all property definitions from the schema.
     *
     * @return array<string, array<string, mixed>>|\stdClass Property definitions
     */
    public function getProperties(): array|\stdClass
    {
        return $this->schema['properties'] ?? new \stdClass();
    }

    /**
     * Gets the default value for a property if defined in the schema.
     *
     * @param string $propertyName The name of the property
     * @return mixed The default value or null if not defined
     */
    public function getDefaultValue(string $propertyName): mixed
    {
        $properties = $this->getProperties();

        if (!isset($properties[$propertyName])) {
            return null;
        }

        // Return the default value if it exists, otherwise null
        return $properties[$propertyName]['default'] ?? null;
    }

    /**
     * Check if the schema allows any properties (flexible mode).
     */
    public function allowsAnyProperties(): bool
    {
        return $this->allowAny;
    }

    /**
     * Get the list of blocked property names.
     *
     * @return list<string>
     */
    public function getBlockedProperties(): array
    {
        return $this->blockedProperties;
    }

    /**
     * Check if a property is blocked.
     */
    public function isPropertyBlocked(string $propertyName): bool
    {
        return \in_array($propertyName, $this->blockedProperties, true);
    }

    /**
     * Check if a property is allowed based on schema rules.
     *
     * In allowAny mode: property is allowed if not blocked
     * In normal mode: property is allowed if defined in properties
     */
    public function isPropertyAllowed(string $propertyName): bool
    {
        // Always reject blocked properties
        if ($this->isPropertyBlocked($propertyName)) {
            return false;
        }

        // In allowAny mode, all non-blocked properties are allowed
        if ($this->allowAny) {
            return true;
        }

        // In normal mode, only defined properties are allowed
        $properties = $this->getProperties();
        return isset($properties[$propertyName]);
    }

    /**
     * Get the name of the flexible argument container.
     */
    public function getFlexibleArg(): ?string
    {
        return $this->flexibleArg;
    }

    /**
     * Check if schema has a flexible argument container.
     */
    public function hasFlexibleArg(): bool
    {
        return $this->flexibleArg !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $result = $this->schema;

        if ($this->allowAny) {
            $result['allowAny'] = true;
        }

        if (!empty($this->blockedProperties)) {
            $result['blockedProperties'] = $this->blockedProperties;
        }

        if ($this->flexibleArg !== null) {
            $result['flexibleArg'] = $this->flexibleArg;
        }

        return $result;
    }
}
