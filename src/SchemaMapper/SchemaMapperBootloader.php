<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\SchemaMapper;

use Butschster\ContextGenerator\McpServer\SchemaMapper\Valinor\MapperBuilder;
use Butschster\ContextGenerator\McpServer\SchemaMapper\Valinor\SchemaMapper;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Boot\DirectoriesInterface;
use Spiral\JsonSchemaGenerator\Generator as JsonSchemaGenerator;

final class SchemaMapperBootloader extends Bootloader
{
    #[\Override]
    public function defineSingletons(): array
    {
        return [
            SchemaMapperInterface::class => static function (
                DirectoriesInterface $dirs,
                JsonSchemaGenerator $generator,
            ): SchemaMapper {
                $mapper = new MapperBuilder();

                $treeMapper = $mapper->build();

                return new SchemaMapper($generator, $treeMapper);
            },
        ];
    }
}
