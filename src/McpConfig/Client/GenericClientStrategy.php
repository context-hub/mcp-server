<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig\Client;

use Butschster\ContextGenerator\McpServer\McpConfig\Model\McpConfig;
use Butschster\ContextGenerator\McpServer\McpConfig\Model\OsInfo;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GenericClientStrategy implements ClientStrategyInterface
{
    public function getKey(): string
    {
        return 'generic';
    }

    public function getLabel(): string
    {
        return 'Generic MCP Client';
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

        $transportMode = ($options['use_sse'] ?? false) ? 'SSE (Server-Sent Events)' : 'STDIO';

        $output->text([
            "Configuration type: <info>Generic MCP</info>",
            "Transport mode: <info>{$transportMode}</info>",
        ]);

        if ($options['use_sse'] ?? false) {
            $output->text([
                "SSE Host: <info>{$options['sse_host']}</info>",
                "SSE Port: <info>{$options['sse_port']}</info>",
            ]);
        }

        $output->newLine();
        $output->text('Generic MCP client configuration (JSON format):');
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
            '1. Locate your MCP client configuration file',
            '2. Add the configuration shown above',
            '3. Ensure ctx is installed and in your PATH',
            '4. Restart your MCP client',
            '5. Verify the server connection',
        ];

        foreach ($steps as $step) {
            $output->text('   ' . $step);
        }

        $output->newLine();

        $output->note(\implode("\n", [
            'This is a generic configuration format.',
            'Consult your MCP client documentation for specific setup instructions.',
            'Some clients may use different formats (TOML, YAML, etc.).',
        ]));

        // Add SSE-specific notes
        if ($options['use_sse'] ?? false) {
            $output->note(\implode("\n", [
                'SSE mode enabled: Server runs as HTTP endpoint.',
                "Access URL: http://{$options['sse_host']}:{$options['sse_port']}",
                'Suitable for remote MCP clients and distributed environments.',
                'Configure your MCP client to connect to this HTTP endpoint.',
            ]));
        }

        $output->newLine();

        $output->section('Troubleshooting');
        $tips = [
            'Server not starting? Check ctx installation: ctx --version',
            'Configuration issues? Verify JSON/TOML syntax is correct',
            'Connection problems? Check your MCP client logs',
            '',
            'Debug CTX server:',
            '  • Run with verbose logging: ctx server -vvv',
            '  • Check log file: ctx-<timestamp>.log in your project directory',
            '  • Logs contain startup info, MCP protocol details, and error traces',
        ];

        if ($options['use_sse'] ?? false) {
            $tips[] = '';
            $tips[] = 'SSE mode specific:';
            $tips[] = '  • Connection refused? Verify port is not in use';
            $tips[] = '  • Remote access issues? Check firewall and network settings';
            $tips[] = "  • Test connectivity: curl http://{$options['sse_host']}:{$options['sse_port']}";
            $tips[] = '  • SSL/TLS: Consider using reverse proxy (nginx/caddy) for HTTPS';
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
}
