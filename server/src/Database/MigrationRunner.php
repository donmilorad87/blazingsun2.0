<?php

declare(strict_types=1);

namespace BlazingSun\Api\Database;

interface MigrationRunner
{
    /** @return list<string> file names of the migrations that were run */
    public function run(): array;
}
