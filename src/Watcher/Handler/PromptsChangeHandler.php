<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Watcher\Handler;

use Butschster\ContextGenerator\McpServer\Prompt\PromptConfigFactory;
use Butschster\ContextGenerator\McpServer\Prompt\PromptRegistryInterface;
use Butschster\ContextGenerator\McpServer\Watcher\Diff\ConfigDiff;
use Mcp\Server\Registry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Handles changes to the prompts configuration section.
 */
final readonly class PromptsChangeHandler implements ChangeHandlerInterface
{
    public function __construct(
        private PromptRegistryInterface $promptRegistry,
        private PromptConfigFactory $promptFactory,
        private Registry $mcpRegistry,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function getSection(): string
    {
        return 'prompts';
    }

    public function apply(ConfigDiff $diff): bool
    {
        if (!$diff->hasChanges()) {
            return false;
        }

        $this->logger->info('Applying prompt changes', [
            'summary' => $diff->getSummary(),
        ]);

        // Process removals first
        foreach ($diff->removed as $id => $promptConfig) {
            $this->removePrompt($id);
        }

        // Process additions
        foreach ($diff->added as $id => $promptConfig) {
            $this->addPrompt($promptConfig);
        }

        // Process modifications (remove old, add new)
        foreach ($diff->modified as $id => $promptConfig) {
            $this->removePrompt($id);
            $this->addPrompt($promptConfig);
        }

        $this->notifyListChanged();

        return true;
    }

    public function reload(array $items): bool
    {
        $this->logger->info('Full prompt reload', [
            'count' => \count($items),
        ]);

        $this->promptRegistry->clear();

        foreach ($items as $promptConfig) {
            $this->addPrompt($promptConfig);
        }

        $this->notifyListChanged();

        return true;
    }

    private function addPrompt(array $config): void
    {
        try {
            $prompt = $this->promptFactory->createFromConfig($config);
            $this->promptRegistry->register($prompt);

            $this->logger->debug('Prompt registered', ['id' => $prompt->id]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to register prompt', [
                'config' => $config,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function removePrompt(string $id): void
    {
        if ($this->promptRegistry->remove($id)) {
            $this->logger->debug('Prompt removed', ['id' => $id]);
        }
    }

    private function notifyListChanged(): void
    {
        $this->mcpRegistry->emit('list_changed', ['prompts']);

        $this->logger->debug('Emitted prompts list_changed notification');
    }
}
