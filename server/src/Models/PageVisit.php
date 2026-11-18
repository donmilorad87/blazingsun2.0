<?php

declare(strict_types=1);

namespace BlazingSun\Api\Models;

use BlazingSun\Api\Database\Connection;

/** Page visit counter, backed by the page_visits table via stored procedures. */
final readonly class PageVisit
{
    public function __construct(private Connection $connection)
    {
    }

    /** Counts one visit and returns the new all-time total. */
    public function record(): int
    {
        return (int) $this->connection->callScalar('sp_page_visit_record');
    }

    public function total(): int
    {
        return (int) $this->connection->callScalar('sp_page_visit_total');
    }
}
