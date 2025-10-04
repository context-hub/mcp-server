<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final class CursorClientStrategy implements ClientStrategyInterface
{
    public function getKey(): string
    {
        return 'cursor';
    }

    public function getLabel(): string
    {
        return 'Cursor';
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

        $configType = ($options['use_project_path'] ?? false) ? 'Project-specific' : 'Global project registry';
        $transportMode = ($options['use_sse'] ?? false) ? 'SSE (Server-Sent Events)' : 'STDIO';

        $output->text([
            "Configuration type: <info>Cursor MCP</info>",
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
        $output->text('Add this to your Cursor MCP configuration:');
        $output->newLine();
        $output->writeln('<comment>' . $config->toJson() . '</comment>');
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
            '1. Open Cursor Settings: Ctrl/Cmd + Shift + P → "Cursor Settings"',
            '2. Navigate to MCP Servers section in the sidebar',
            '3. Click "Add new MCP server" or create/edit .cursor/mcp.json in your project',
            '4. Paste the configuration shown above',
            '5. Enable the server using the toggle switch',
            '6. Look for a green dot indicating the server is active',
        ];

        foreach ($steps as $step) {
            $output->text('   ' . $step);
        }

        $output->newLine();

        // Configuration location info
        $output->text('Configuration file locations:');
        $output->text('  • Global: Cursor Settings → MCP Servers');
        $output->text('  • Project-specific: <info>.cursor/mcp.json</info> in your project root');
        $output->newLine();

        // Mode-specific notes
        if ($options['use_project_path'] ?? false) {
            $output->note(\implode("\n", [
                'Project-specific: Create .cursor/mcp.json for this project only.',
                'Good for project-specific CTX configurations.',
            ]));
        } else {
            $output->note(\implode("\n", [
                'Global registry: Configure once, use across projects.',
                'Register projects with "ctx project:add" first.',
            ]));
        }

        // Add SSE-specific notes
        if ($options['use_sse'] ?? false) {
            $output->note(\implode("\n", [
                'SSE mode: Server runs over HTTP for remote access.',
                "Endpoint: http://{$options['sse_host']}:{$options['sse_port']}",
                'Good for distributed teams or cloud development environments.',
            ]));
        }

        // Troubleshooting
        $output->section('Troubleshooting');
        $tips = [
            'No green dot? Check .cursor/mcp.json syntax',
            'Server not connecting? Ensure ctx is in your PATH',
            'Check status: Use "/mcp" command in Cursor chat',
            'Verify Node.js is installed (required for most MCP servers)',
            '',
            'Debug CTX issues:',
            '  • Run ctx manually with verbose logging: ctx server -vvv',
            '  • Check log file: ctx-<timestamp>.log in your project directory',
            '  • Logs contain detailed startup, tool execution, and error traces',
        ];

        if ($options['use_sse'] ?? false) {
            $tips[] = '';
            $tips[] = 'SSE troubleshooting:';
            $tips[] = '  • Port already in use? Try a different port number';
            $tips[] = '  • Can\'t reach server? Check firewall configuration';
            $tips[] = "  • Test connection: curl http://{$options['sse_host']}:{$options['sse_port']}";
        }

        foreach ($tips as $tip) {
            if ($tip === '') {
                $output->newLine();
            } else {
                $output->text('  • ' . $tip);
            }
        }

        $output->newLine();

        $output->text(\implode("\n", [
            'Documentation:',
            '  • Cursor MCP docs: https://docs.cursor.com/context/model-context-protocol',
            '  • CTX project docs: https://docs.ctx.github.io/mcp/projects',
        ]));
    }
}
