<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->router->add('POST', '/visits', static fn (Request $request): JsonResponse => new JsonResponse(200, ['total' => 7]));
        $this->router->add('GET', '/health', static fn (Request $request): JsonResponse => new JsonResponse(200, ['status' => 'ok']));
    }

    public function testDispatchesToTheMatchingHandler(): void
    {
        $response = $this->router->dispatch(new Request('POST', '/api/visits'));

        self::assertSame(200, $response->status());
        self::assertSame(['total' => 7], $response->payload());
    }

    public function testUnknownPathIs404(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/config/config.php'));

        self::assertSame(404, $response->status());
        self::assertSame(['error' => 'Not found'], $response->payload());
    }

    public function testWrongMethodIs405WithAllowHeader(): void
    {
        $response = $this->router->dispatch(new Request('GET', '/api/visits'));

        self::assertSame(405, $response->status());
        self::assertSame('POST', $response->headers()['Allow']);
    }
}
