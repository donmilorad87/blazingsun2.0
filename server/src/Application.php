<?php

declare(strict_types=1);

namespace BlazingSun\Api;

use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Http\Router;
use Closure;
use Throwable;

final readonly class Application
{
    /** @param Closure(string): void $log */
    public function __construct(
        private Router $router,
        private Closure $log,
    ) {
    }

    #[\NoDiscard('the response must be sent')]
    public function handle(Request $request): JsonResponse
    {
        try {
            return $this->router->dispatch($request);
        } catch (Throwable $error) {
            // Full detail goes to the server log; the client only gets a generic message.
            ($this->log)(sprintf('[api] %s %s failed: %s in %s:%d', $request->method(), $request->path(), $error->getMessage(), $error->getFile(), $error->getLine()));

            return JsonResponse::error(500, 'Internal server error');
        }
    }
}
