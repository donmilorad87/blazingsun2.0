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

        $this->dropEverything();
        $this->migrator()->run();
    }

    /** The test database is disposable: every test starts from an empty schema. */
    private function dropEverything(): void
    {
        $pdo = $this->connection->pdo;
        $routines = $pdo->query('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()');
        $tables = $pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        self::assertNotFalse($routines);
        self::assertNotFalse($tables);
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($routines->fetchAll(\PDO::FETCH_COLUMN) as $routine) {
            $pdo->exec("DROP PROCEDURE IF EXISTS `$routine`");
        }
        foreach ($tables->fetchAll(\PDO::FETCH_COLUMN) as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function migrator(): SqlMigrationRunner
    {
        return new SqlMigrationRunner($this->connection, dirname(__DIR__, 2) . '/migrations');
    }
}
