<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Tool\Config\ToolDefinition;
use Butschster\ContextGenerator\McpServer\Tool\ToolRegistryInterface;
use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiff;
use Mcp\Server\Registry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles changes to the tools configuration section.
 */
final readonly class ToolsChangeHandler implements ChangeHandlerInterface
{
    public function __construct(
        private ToolRegistryInterface $toolRegistry,
        private Registry $mcpRegistry,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getSection(): string
    {
        return 'tools';
    }

    public function apply(ConfigDiff $diff): bool
    {
        if (!$diff->hasChanges()) {
            return false;
        }

        $this->logger->info('Applying tool changes', [
            'summary' => $diff->getSummary(),
        ]);

        // Process removals first
        foreach ($diff->removed as $id => $toolConfig) {
            $this->removeTool($id);
        }

        // Process additions
        foreach ($diff->added as $id => $toolConfig) {
            $this->addTool($toolConfig);
        }

        // Process modifications (remove old, add new)
        foreach ($diff->modified as $id => $toolConfig) {
            $this->removeTool($id);
            $this->addTool($toolConfig);
        }

        $this->notifyListChanged();

        return true;
    }

    public function reload(array $items): bool
    {
        $this->logger->info('Full tool reload', [
            'count' => \count($items),
        ]);

        $this->toolRegistry->clear();

        foreach ($items as $toolConfig) {
            $this->addTool($toolConfig);
        }

        $this->notifyListChanged();

        return true;
    }

    private function addTool(array $config): void
    {
        try {
            $tool = ToolDefinition::fromArray($config);
            $this->toolRegistry->register($tool);

            $this->logger->debug('Tool registered', ['id' => $tool->id]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register tool', [
                'config' => $config,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removeTool(string $id): void
    {
        if ($this->toolRegistry->remove($id)) {
            $this->logger->debug('Tool removed', ['id' => $id]);
        }
    }

    private function notifyListChanged(): void
    {
        $this->mcpRegistry->emit('list_changed', ['tools']);

        $this->logger->debug('Emitted tools list_changed notification');
    }
}
