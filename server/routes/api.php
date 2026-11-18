<?php

declare(strict_types=1);

use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Controllers\HealthController;
use BlazingSun\Api\Controllers\MigrationController;
use BlazingSun\Api\Controllers\VisitController;
use BlazingSun\Api\Database\Connection;
use BlazingSun\Api\Database\SqlMigrationRunner;
use BlazingSun\Api\Http\Router;
use BlazingSun\Api\Models\PageVisit;

/** Registers every API route. Paths are relative to /api. */
return static function (Router $router, Config $config, string $root): void {
    $connection = new Connection($config);
    $visits = new PageVisit($connection);

    $router->add('POST', '/visits', [new VisitController($visits), 'record']);
    $router->add('GET', '/health', [new HealthController($visits), 'show']);
    $router->add('POST', '/migrations', [
        new MigrationController(new SqlMigrationRunner($connection, $root . '/migrations'), $config->migrationToken()),
        'run',
    ]);
};
