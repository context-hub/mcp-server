<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\McpConfig;

use Butschster\ContextGenerator\McpServer\McpConfig\Client\ClaudeDesktopClientStrategy;
use Butschster\ContextGenerator\McpServer\McpConfig\Client\ClientStrategyRegistry;
use Butschster\ContextGenerator\McpServer\McpConfig\Client\CodexClientStrategy;
use Butschster\ContextGenerator\McpServer\McpConfig\Client\CursorClientStrategy;
use Butschster\ContextGenerator\McpServer\McpConfig\Client\GenericClientStrategy;
use Butschster\ContextGenerator\McpServer\McpConfig\Generator\McpConfigGenerator;
use Butschster\ContextGenerator\McpServer\McpConfig\Service\OsDetectionService;
use Butschster\ContextGenerator\McpServer\McpConfig\Template\ConfigTemplateInterface;
use Butschster\ContextGenerator\McpServer\McpConfig\Template\LinuxConfigTemplate;
use Butschster\ContextGenerator\McpServer\McpConfig\Template\MacOsConfigTemplate;
use Butschster\ContextGenerator\McpServer\McpConfig\Template\WindowsConfigTemplate;
use Butschster\ContextGenerator\McpServer\McpConfig\Template\WslConfigTemplate;
use Spiral\Boot\Bootloader\Bootloader;

final class McpConfigBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ClientStrategyRegistry::class => static fn() => new ClientStrategyRegistry([
                new ClaudeDesktopClientStrategy(),
                new CodexClientStrategy(),
                new CursorClientStrategy(),
                new GenericClientStrategy(),
            ]),

            OsDetectionService::class => OsDetectionService::class,
            ConfigGeneratorInterface::class => static fn(
                LinuxConfigTemplate $linuxTemplate,
                WindowsConfigTemplate $windowsTemplate,
                WslConfigTemplate $wslTemplate,
                MacOsConfigTemplate $macosTemplate,
            ): McpConfigGenerator
                => new McpConfigGenerator(
                windowsTemplate: $windowsTemplate,
                linuxTemplate: $linuxTemplate,
                wslTemplate: $wslTemplate,
                macosTemplate: $macosTemplate,
            ),
            LinuxConfigTemplate::class => LinuxConfigTemplate::class,
            WindowsConfigTemplate::class => WindowsConfigTemplate::class,
            WslConfigTemplate::class => WslConfigTemplate::class,
            MacOsConfigTemplate::class => MacOsConfigTemplate::class,
        ];
    }

    #[\Override]
    public function defineBindings(): array
    {
        return [
            ConfigTemplateInterface::class => LinuxConfigTemplate::class,
        ];
    }
}
