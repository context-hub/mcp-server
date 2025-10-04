<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CodexClientStrategy implements ClientStrategyInterface
{
    public function getKey(): string
    {
        return 'codex';
    }

    public function getLabel(): string
    {
        return 'Codex';
    }

    public function getGeneratorClientKey(): string
    {
        return 'generic';
    }

    public function renderConfiguration(
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        $output->section('Generated Configuration');

        $output->text('Codex configuration (TOML format):');
        $output->newLine();

        // Format args for TOML
        $args = \array_map(
            static fn(string $arg): string => '"' . \str_replace('"', '\\"', $arg) . '"',
            $config->args,
        );

        $toml = "[mcp_servers.ctx]\n"
            . "command = \"{$config->command}\"\n"
            . 'args = [' . \implode(', ', $args) . "]\n";

        $output->writeln('<comment>' . $toml . '</comment>');
        $output->newLine();
    }

    public function renderInstructions(
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        $output->section('Setup Instructions');

        $steps = [
            '1. Locate your Codex configuration file',
            '2. Copy the TOML snippet above',
            '3. Paste it into the [mcp_servers] section',
            '4. Ensure the ctx binary is available in your PATH',
            '5. Restart Codex to load the new configuration',
        ];

        foreach ($steps as $step) {
            $output->text('   ' . $step);
        }

        $output->newLine();

        $output->note(\implode("\n", [
            'Codex uses TOML format for MCP server configuration.',
            'Make sure ctx is installed and accessible from your terminal.',
        ]));

        $output->newLine();

        $output->section('Troubleshooting');
        $tips = [
            'Server not starting? Verify ctx installation: ctx --version',
            'Check Codex logs for connection errors',
            '',
            'Debug CTX issues:',
            '  • Enable verbose logging: ctx server -vvv',
            '  • Check ctx-<timestamp>.log in your project directory',
            '  • Logs show configuration loading, MCP protocol details, and errors',
        ];

        foreach ($tips as $tip) {
            if ($tip === '') {
                $output->newLine();
            } else {
                $output->text('  • ' . $tip);
            }
        }

        $output->newLine();
    }
}
