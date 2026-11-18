<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Integration;

use BlazingSun\Api\Models\PageVisit;

final class PageVisitTest extends DatabaseTestCase
{
    public function testStartsAtZero(): void
    {
        self::assertSame(0, (new PageVisit($this->connection))->total());
    }

    public function testEachRecordedVisitIncrementsTheTotal(): void
    {
        $visits = new PageVisit($this->connection);

        self::assertSame(1, $visits->record());
        self::assertSame(2, $visits->record());
        self::assertSame(3, $visits->record());
        self::assertSame(3, $visits->total());
    }

    public function testTotalSumsAllDays(): void
    {
        $this->connection->pdo->exec('INSERT INTO page_visits (visit_date, visits) VALUES (CURRENT_DATE() - INTERVAL 1 DAY, 40)');

        self::assertSame(41, (new PageVisit($this->connection))->record());
    }

    public function testTodaysVisitsShareOneRow(): void
    {
        $visits = new PageVisit($this->connection);
        $visits->record();
        $visits->record();

        $rows = $this->connection->pdo->query('SELECT COUNT(*) FROM page_visits');
        self::assertNotFalse($rows);
        self::assertSame(1, (int) $rows->fetchColumn());
    }
}
