<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Integration;

final class MigrationsTest extends DatabaseTestCase
{
    public function testMigrationsAreIdempotentAndCanRunOnEveryDeploy(): void
    {
        $applied = $this->migrator()->run();

        self::assertSame(['001_create_page_visits.sql', '002_create_content.sql'], $applied);
        self::assertSame($applied, $this->migrator()->run());
    }

    public function testCreatesTheTableAndProcedures(): void
    {
        $pdo = $this->connection->pdo;
        $routines = $pdo->query(
            "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() ORDER BY ROUTINE_NAME"
        );
        self::assertNotFalse($routines);

        $names = $routines->fetchAll(\PDO::FETCH_COLUMN);
        foreach (['sp_page_visit_record', 'sp_page_visit_total', 'sp_value_set', 'sp_language_save', 'sp_item_create'] as $procedure) {
            self::assertContains($procedure, $names);
        }
        self::assertNotFalse($pdo->query('SELECT visit_date, visits FROM page_visits LIMIT 1'));
        self::assertNotFalse($pdo->query('SELECT scope, field, lang, value FROM content_values LIMIT 1'));
    }
}
