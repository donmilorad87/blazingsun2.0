<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Controllers\MigrationController;
use BlazingSun\Api\Database\MigrationRunner;
use BlazingSun\Api\Http\Request;
use PHPUnit\Framework\TestCase;

final class MigrationControllerTest extends TestCase
{
    private function runner(): FakeMigrationRunner
    {
        return new FakeMigrationRunner();
    }

    public function testRunsMigrationsWithTheRightToken(): void
    {
        $runner = $this->runner();
        $response = (new MigrationController($runner, 'good-token'))
            ->run(new Request('POST', '/api/migrations', ['Authorization' => 'Bearer good-token']));

        self::assertSame(200, $response->status());
        self::assertSame(['applied' => ['001_create_page_visits.sql']], $response->payload());
        self::assertSame(1, $runner->runs);
    }

    public function testRejectsAWrongOrMissingToken(): void
    {
        $runner = $this->runner();
        $controller = new MigrationController($runner, 'good-token');

        self::assertSame(401, $controller->run(new Request('POST', '/', ['Authorization' => 'Bearer nope']))->status());
        self::assertSame(401, $controller->run(new Request('POST', '/'))->status());
        self::assertSame(0, $runner->runs);
    }

    public function testEndpointIsHiddenWhenNoTokenIsConfigured(): void
    {
        $runner = $this->runner();
        $response = (new MigrationController($runner, ''))
            ->run(new Request('POST', '/', ['Authorization' => 'Bearer ']));

        self::assertSame(404, $response->status());
        self::assertSame(0, $runner->runs);
    }
}

final class FakeMigrationRunner implements MigrationRunner
{
    public int $runs = 0;

    public function run(): array
    {
        $this->runs++;

        return ['001_create_page_visits.sql'];
    }
}
