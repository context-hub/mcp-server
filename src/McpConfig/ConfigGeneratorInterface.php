<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;

interface ConfigGeneratorInterface
{
    public function generate(
        string $client,
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig;

    public function getSupportedClients(): array;
}
