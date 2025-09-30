<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer;

use Spiral\Core\InjectableConfig;

final class OauthConfig extends InjectableConfig
{
    public const string CONFIG = 'oauth';

    protected array $config = [
        'enabled' => false,
        'client_id' => null,
        'client_secret' => null,
    ];

    public function isEnabled(): bool
    {
        return (bool)$this->config['enabled'];
    }

    public function getClientId(): ?string
    {
        return $this->config['client_id'] ?? null;
    }

    public function getClientSecret(): ?string
    {
        return $this->config['client_secret'] ?? null;
    }

    public function hasCredentials(): bool
    {
        return $this->getClientId() !== null && $this->getClientSecret() !== null;
    }
}
