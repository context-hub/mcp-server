<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Template;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;

final class MacOsConfigTemplate extends BaseConfigTemplate
{
    public function generate(
        string $client,
        OsInfo $osInfo,
        string $projectPath,
        array $options = [],
    ): McpConfig {
        return match ($client) {
            'claude' => $this->generateClaudeConfig($osInfo, $projectPath, $options),
            'generic' => $this->generateGenericConfig($osInfo, $projectPath, $options),
            default => throw new \InvalidArgumentException("Unsupported client: {$client}"),
        };
    }

    protected function getCommand(OsInfo $osInfo): string
    {
        return 'ctx';
    }

    protected function getArgs(OsInfo $osInfo, string $projectPath, array $options = []): array
    {
        $args = ['server'];

        // Only add -c option if project path is explicitly requested
        if (isset($options['use_project_path']) && $options['use_project_path']) {
            $args[] = '-c';
            $args[] = $projectPath;
        }

        // Add SSE options if enabled
        if (isset($options['use_sse']) && $options['use_sse']) {
            $args[] = '--sse';

            if (isset($options['sse_host'])) {
                $args[] = '--host';
                $args[] = $options['sse_host'];
            }

            if (isset($options['sse_port'])) {
                $args[] = '--port';
                $args[] = (string)$options['sse_port'];
            }
        }

        return $args;
    }
}
