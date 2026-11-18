<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testIsNeverCachedAndIsJson(): void
    {
        $headers = (new JsonResponse(200, []))->headers();

        self::assertSame('application/json; charset=utf-8', $headers['Content-Type']);
        self::assertSame('no-store', $headers['Cache-Control']);
        self::assertSame('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testExtraHeadersAreMerged(): void
    {
        self::assertSame('POST', (new JsonResponse(405, [], ['Allow' => 'POST']))->headers()['Allow']);
    }

    public function testBodyIsUnicodeJson(): void
    {
        self::assertSame('{"name":"Đuković"}', (new JsonResponse(200, ['name' => 'Đuković']))->body());
    }
}
