<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Integration;

use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Database\Connection;
use BlazingSun\Api\Database\SqlMigrationRunner;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected Connection $connection;

    protected function setUp(): void
    {
        $host = getenv('TEST_DB_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('TEST_DB_HOST is not set; integration tests need a MySQL/MariaDB database.');
        }

        $this->connection = new Connection(Config::fromArray([
            'db' => [
                'host' => $host,
                'port' => getenv('TEST_DB_PORT') ?: '3306',
                'name' => (string) getenv('TEST_DB_NAME'),
                'user' => (string) getenv('TEST_DB_USER'),
                'password' => (string) getenv('TEST_DB_PASSWORD'),
            ],
            'migration_token' => '',
        ]));

        $pdo = $this->connection->pdo;
        $pdo->exec('DROP PROCEDURE IF EXISTS sp_page_visit_record');
        $pdo->exec('DROP PROCEDURE IF EXISTS sp_page_visit_total');
        $pdo->exec('DROP TABLE IF EXISTS page_visits');
        $this->migrator()->run();
    }

    protected function migrator(): SqlMigrationRunner
    {
        return new SqlMigrationRunner($this->connection, dirname(__DIR__, 2) . '/migrations');
    }
}
