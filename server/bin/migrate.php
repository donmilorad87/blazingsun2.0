<?php

declare(strict_types=1);

// CLI for local development and E2E (never deployed): runs the migrations and the one-time content seed.
//   php bin/migrate.php           migrate + seed (same as POST /api/migrations)
//   php bin/migrate.php --fresh   first drop every table and procedure — only for databases named *_e2e or *_test
// Uses the config file from BLAZINGSUN_API_CONFIG (or config/config.php).

use BlazingSun\Api\Config\Config;
use BlazingSun\Api\Content\ContentSeeder;
use BlazingSun\Api\Database\Connection;
use BlazingSun\Api\Database\SqlMigrationRunner;
use BlazingSun\Api\Models\ContentRepository;

$root = dirname(__DIR__);
require $root . '/src/autoload.php';

$config = Config::fromFile(getenv('BLAZINGSUN_API_CONFIG') ?: $root . '/config/config.php');
$connection = new Connection($config);
$pdo = $connection->pdo;

if (in_array('--fresh', $argv, true)) {
    $database = (string) $pdo->query('SELECT DATABASE()')?->fetchColumn();
    if (preg_match('/_(e2e|test)$/', $database) !== 1) {
        fwrite(STDERR, "Refusing --fresh on '$database': only *_e2e or *_test databases can be wiped.\n");
        exit(1);
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = DATABASE()')?->fetchAll(PDO::FETCH_COLUMN) ?? [] as $routine) {
        $pdo->exec("DROP PROCEDURE IF EXISTS `$routine`");
    }
    foreach ($pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')?->fetchAll(PDO::FETCH_COLUMN) ?? [] as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Wiped $database\n";
}

$applied = (new SqlMigrationRunner($connection, $root . '/migrations'))->run();
$seeded = (new ContentSeeder(new ContentRepository($connection), $root . '/content/seed.json'))->run();
echo 'Applied ' . implode(', ', $applied) . ($seeded ? '; seeded the content' : '; content already seeded') . "\n";
