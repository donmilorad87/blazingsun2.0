<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Integration;

final class MigrationsTest extends DatabaseTestCase
{
    public function testMigrationsAreIdempotentAndCanRunOnEveryDeploy(): void
    {
        $applied = $this->migrator()->run();

        self::assertContains('001_create_page_visits.sql', $applied);
        self::assertSame($applied, $this->migrator()->run());
    }

    public function testCreatesTheTableAndProcedures(): void
    {
        $pdo = $this->connection->pdo;
        $routines = $pdo->query(
            "SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE() ORDER BY ROUTINE_NAME"
        );
        self::assertNotFalse($routines);

        self::assertSame(['sp_page_visit_record', 'sp_page_visit_total'], $routines->fetchAll(\PDO::FETCH_COLUMN));
        self::assertNotFalse($pdo->query('SELECT visit_date, visits FROM page_visits LIMIT 1'));
    }
}
