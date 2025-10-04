<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ClaudeDesktopClientStrategy implements ClientStrategyInterface
{
    public function getKey(): string
    {
        return 'claude';
    }

    public function getLabel(): string
    {
        return 'Claude Desktop';
    }

    public function getGeneratorClientKey(): string
    {
        return 'claude';
    }

    public function renderConfiguration(
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        $output->section('Generated Configuration');

        $configType = ($options['use_project_path'] ?? false) ? 'Project-specific' : 'Global project registry';
        $transportMode = ($options['use_sse'] ?? false) ? 'SSE (Server-Sent Events)' : 'STDIO';

        $output->text([
            "Configuration type: <info>{$config->clientType}</info>",
            "Operating system: <info>{$osInfo->getDisplayName()}</info>",
            "Project mode: <info>{$configType}</info>",
            "Transport mode: <info>{$transportMode}</info>",
        ]);

        if ($options['use_sse'] ?? false) {
            $output->text([
                "SSE Host: <info>{$options['sse_host']}</info>",
                "SSE Port: <info>{$options['sse_port']}</info>",
            ]);
        }

        $output->newLine();
        $output->text('Add this to your Claude Desktop configuration file:');
        $output->newLine();
        $output->writeln('<comment>' . $config->toJson() . '</comment>');
        $output->newLine();

        $this->showConfigLocation($output, $osInfo);
    }

    public function renderInstructions(
        McpConfig $config,
        OsInfo $osInfo,
        array $options,
        SymfonyStyle $output,
    ): void {
        $output->section('Setup Instructions');

        $steps = [
            '1. Open Claude Desktop settings: Click your name/initials → Settings → Developer',
            '2. Click "Edit Config" to open claude_desktop_config.json',
            '3. Add the configuration shown above to the file',
            '4. Save the file and completely restart Claude Desktop',
            '5. Look for the MCP tools indicator (hammer icon) in the chat input area',
        ];

        foreach ($steps as $step) {
            $output->text('   ' . $step);
        }

        $output->newLine();

        // Add mode-specific notes
        if ($options['use_project_path'] ?? false) {
            $output->note(\implode("\n", [
                'Project-specific mode: CTX will only access the specified project path.',
                'Good for single-project workflows with focused context.',
            ]));
        } else {
            $output->note(\implode("\n", [
                'Global registry mode: Use "ctx project:add" to register projects.',
                'Switch projects dynamically without editing configuration.',
                'Docs: https://docs.ctx.github.io/mcp/projects',
            ]));
        }

        // Add SSE-specific notes
        if ($options['use_sse'] ?? false) {
            $output->note(\implode("\n", [
                'SSE mode enabled: Server runs as HTTP endpoint for remote access.',
                "Access URL: http://{$options['sse_host']}:{$options['sse_port']}",
                'Useful for distributed teams or remote MCP client connections.',
                'Ensure firewall allows connections on the specified port.',
            ]));
        }

        // Add WSL-specific guidance
        if ($osInfo->isWsl()) {
            $output->warning(\implode("\n", [
                'WSL detected: Ensure ctx is installed in your WSL environment.',
                'Use WSL paths (e.g., /home/user/project), not Windows paths.',
                'Environment variables are embedded in the bash command.',
            ]));
        }

        // Troubleshooting section
        $output->section('Troubleshooting');
        $tips = [
            'No hammer icon? Check claude_desktop_config.json for syntax errors',
            'Server not starting? Verify ctx is installed: run "ctx --version"',
            'WSL users: Test manually with "bash.exe -c \'ctx server\'"',
            'Check logs: Settings → Developer → View Logs',
            '',
            'Debug CTX issues:',
            '  • Run with verbose logging: ctx server -vvv',
            '  • Check log file: ctx-<timestamp>.log in your project directory',
            '  • Logs show detailed startup, configuration, and error information',
        ];

        if ($options['use_sse'] ?? false) {
            $tips[] = '';
            $tips[] = 'SSE mode troubleshooting:';
            $tips[] = '  • Connection refused? Check if port is already in use';
            $tips[] = '  • Can\'t connect remotely? Verify firewall settings';
            $tips[] = "  • Test endpoint: curl http://{$options['sse_host']}:{$options['sse_port']}";
        }

        foreach ($tips as $tip) {
            if ($tip === '') {
                $output->newLine();
            } else {
                $output->text('  • ' . $tip);
            }
        }

        $output->newLine();
    }

    private function showConfigLocation(SymfonyStyle $output, OsInfo $osInfo): void
    {
        $output->text('Configuration file location:');

        $paths = match (true) {
            $osInfo->isWindows() || $osInfo->isWsl() => [
                '%APPDATA%\Claude\claude_desktop_config.json',
                'C:\Users\<username>\AppData\Roaming\Claude\claude_desktop_config.json',
            ],
            $osInfo->isMacOs() => [
                '~/Library/Application Support/Claude/claude_desktop_config.json',
            ],
            default => [
                '~/.config/Claude/claude_desktop_config.json',
                '$XDG_CONFIG_HOME/Claude/claude_desktop_config.json',
            ],
        };

        foreach ($paths as $path) {
            $output->text("  • <info>{$path}</info>");
        }

        $output->newLine();
    }
}
