<?php

declare(strict_types=1);

namespace Butschster\ContextGenerator\McpServer\Interceptor;

use Spiral\Core\Attribute\Singleton;

#[Singleton]
final class InterceptorPipeline
{
    /** @var array<ToolInterceptorInterface> */
    private array $interceptors = [];

    public function addInterceptor(ToolInterceptorInterface $interceptor): void
    {
        $this->interceptors[] = $interceptor;
    }

    /**
     * @param iterable<ToolInterceptorInterface> $interceptors
     */
    public function addInterceptors(iterable $interceptors): void
    {
        foreach ($interceptors as $interceptor) {
            $this->addInterceptor($interceptor);
        }
    }

    /**
     * Execute the interceptor chain.
     *
     * @param object $request Request DTO
     * @param callable(): mixed $action Final action to execute
     * @return mixed Action result
     */
    public function execute(object $request, callable $action): mixed
    {
        if (empty($this->interceptors)) {
            return $action();
        }

        $pipeline = array_reduce(
            array_reverse($this->interceptors),
            fn(callable $next, ToolInterceptorInterface $interceptor): callable =>
                fn(): mixed => $interceptor->intercept($request, $next),
            $action,
        );

        return $pipeline();
    }
}
