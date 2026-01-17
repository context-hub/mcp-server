<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Tool\Exception;

/**
 * Exception thrown when a blocked argument is used.
 */
final class BlockedArgumentException extends \RuntimeException
{
    /**
     * @param string $argumentName The name of the blocked argument
     * @param list<string> $blockedArguments List of all blocked arguments
     */
    public function __construct(
        public readonly string $argumentName,
        public readonly array $blockedArguments = [],
    ) {
        parent::__construct(
            \sprintf(
                'Argument "%s" is blocked and cannot be used. Blocked arguments: %s',
                $argumentName,
                \implode(', ', $blockedArguments),
            ),
        );
    }
}
