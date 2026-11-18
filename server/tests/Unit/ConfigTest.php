<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Config\ConfigException;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    private function validValues(): array
    {
        return [
            'db' => [
                'host' => 'localhost',
                'port' => '3306',
                'name' => 'u1_blazingsun',
                'user' => 'u1_blazingsun',
                'password' => 's3cr3t',
            ],
            'migration_token' => 'token-123',
        ];
    }

    public function testBuildsDsnAndExposesCredentials(): void
    {
        $config = Config::fromArray($this->validValues());

        self::assertSame('mysql:host=localhost;port=3306;dbname=u1_blazingsun;charset=utf8mb4', $config->dsn());
        self::assertSame('u1_blazingsun', $config->dbUser());
        self::assertSame('s3cr3t', $config->dbPassword());
        self::assertSame('token-123', $config->migrationToken());
    }

    public function testMissingKeysAreReportedByNameWithoutLeakingValues(): void
    {
        $values = $this->validValues();
        unset($values['db']['password'], $values['db']['host']);

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('db.host, db.password');
        (void) Config::fromArray($values);
    }

    public function testEmptyStringCountsAsMissing(): void
    {
        $values = $this->validValues();
        $values['db']['name'] = '';

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('db.name');
        (void) Config::fromArray($values);
    }

    public function testRejectsNonNumericPort(): void
    {
        $values = $this->validValues();
        $values['db']['port'] = '33a6';

        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('db.port');
        (void) Config::fromArray($values);
    }

    public function testMigrationTokenMayBeEmptyToDisableTheEndpoint(): void
    {
        $values = $this->validValues();
        $values['migration_token'] = '';

        self::assertSame('', Config::fromArray($values)->migrationToken());
    }
}
