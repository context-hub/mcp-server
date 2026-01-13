<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Interceptor;

interface ToolInterceptorInterface
{
    /**
     * Process the request before action execution.
     *
     * @param object $request The request DTO (parsed from InputSchema)
     * @param callable(): mixed $next Next interceptor or action
     * @return mixed Action result
     */
    public function intercept(object $request, callable $next): mixed;
}
