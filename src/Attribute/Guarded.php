<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
final readonly class Guarded
{
    public function __construct(
        private ?array $scopes = null,
    ) {}
}
