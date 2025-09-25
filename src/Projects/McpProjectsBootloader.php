<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Projects;

use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepository;
use Butschster\ContextGenerator\McpServer\Projects\Repository\ProjectStateRepositoryInterface;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\Core\FactoryInterface;

final class McpProjectsBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            ProjectStateRepositoryInterface::class => static fn(
                FactoryInterface $factory,
                DirectoriesInterface $dirs,
            ) => $factory->make(ProjectStateRepository::class, [
                'stateDirectory' => $dirs->get('global-state'),
            ]),
            ProjectServiceInterface::class => ProjectService::class,
        ];
    }
}
