<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Model;

final readonly class McpConfig
{
    public function __construct(
        public string $clientType,
        public string $osType,
        public array $configData,
        public string $command,
        public array $args,
        public array $env = [],
        public array $metadata = [],
    ) {}

    public function toJson(bool $prettyPrint = true): string
    {
        $flags = JSON_THROW_ON_ERROR;
        if ($prettyPrint) {
            $flags |= JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;
        }

        return \json_encode($this->configData, $flags);
    }
}
