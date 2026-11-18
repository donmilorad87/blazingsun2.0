<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Application;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use BlazingSun\Api\Http\Router;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicationTest extends TestCase
{
    public function testUnexpectedErrorsBecomeAGeneric500AndAreLogged(): void
    {
        $router = new Router();
        $router->add('POST', '/visits', static function (Request $request): JsonResponse {
            throw new RuntimeException('SQLSTATE[HY000] secret connection details');
        });
        $logged = [];

        $response = (new Application($router, static function (string $message) use (&$logged): void {
            $logged[] = $message;
        }))->handle(new Request('POST', '/api/visits'));

        self::assertSame(500, $response->status());
        self::assertSame(['error' => 'Internal server error'], $response->payload());
        self::assertCount(1, $logged);
        self::assertStringContainsString('secret connection details', $logged[0]);
    }
}
