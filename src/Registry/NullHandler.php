<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Registry;

use Mcp\Server\Context;
use Mcp\Server\Contracts\HandlerInterface;

final readonly class NullHandler implements HandlerInterface
{
    public function handle(array $arguments, Context $context,): mixed
    {
        return null;
    }
}
