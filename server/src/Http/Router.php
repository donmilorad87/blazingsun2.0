<?php

declare(strict_types=1);

namespace BlazingSun\Api\Http;

final class Router
{
    /** @var array<string, array<string, callable(Request): JsonResponse>> path => method => handler */
    private array $routes = [];

    /** @param callable(Request): JsonResponse $handler */
    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$path][strtoupper($method)] = $handler;
    }

    #[\NoDiscard('the response must be sent')]
    public function dispatch(Request $request): JsonResponse
    {
        $handlers = $this->routes[$request->path()] ?? null;
        if ($handlers === null) {
            return JsonResponse::error(404, 'Not found');
        }

        $handler = $handlers[$request->method()] ?? null;
        if ($handler === null) {
            return JsonResponse::error(405, 'Method not allowed')
                ->withHeader('Allow', implode(', ', array_keys($handlers)));
        }

        return $handler($request);
    }
}
