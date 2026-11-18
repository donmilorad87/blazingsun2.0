<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function paths(): iterable
    {
        yield 'api prefix stripped' => ['/api/visits', '/visits'];
        yield 'query string ignored' => ['/api/visits?x=1', '/visits'];
        yield 'trailing slash ignored' => ['/api/visits/', '/visits'];
        yield 'api root' => ['/api', '/'];
        yield 'api root with slash' => ['/api/', '/'];
        yield 'without prefix (php -S)' => ['/health', '/health'];
        yield 'prefix only matched as a whole segment' => ['/apivisits', '/apivisits'];
    }

    #[DataProvider('paths')]
    public function testNormalisesPath(string $uri, string $expected): void
    {
        self::assertSame($expected, (new Request('GET', $uri))->path());
    }

    public function testMethodIsUppercased(): void
    {
        self::assertSame('POST', (new Request('post', '/api/visits'))->method());
    }

    public function testReadsBearerTokenCaseInsensitively(): void
    {
        $request = new Request('POST', '/api/migrations', ['authorization' => 'Bearer abc.def']);

        self::assertSame('abc.def', $request->bearerToken());
    }

    public function testBearerTokenIsNullWhenMissingOrMalformed(): void
    {
        self::assertNull((new Request('POST', '/'))->bearerToken());
        self::assertNull((new Request('POST', '/', ['Authorization' => 'Basic abc']))->bearerToken());
        self::assertNull((new Request('POST', '/', ['Authorization' => 'Bearer ']))->bearerToken());
    }
}
