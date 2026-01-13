<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Interceptor;

use Spiral\Boot\Bootloader\Bootloader;

final class McpServerInterceptorBootloader extends Bootloader
{
    public function defineSingletons(): array
    {
        return [
            InterceptorPipeline::class => InterceptorPipeline::class,
        ];
    }

    public function boot(InterceptorPipeline $pipeline): void
    {
        // Interceptors can be registered here or via tagged services
    }
}
