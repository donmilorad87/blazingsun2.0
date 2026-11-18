<?php

declare(strict_types=1);

namespace BlazingSun\Api\Database;

use RuntimeException;

/**
 * Runs every migrations/*.sql file in name order, on every deploy.
 * Migrations MUST be idempotent (IF NOT EXISTS / DROP ... IF EXISTS), so no tracking table is needed.
 * Statements inside a file are separated by a line containing only "-- statement-break"
 * (procedure bodies contain semicolons, so files can't be split on ";").
 */
final readonly class SqlMigrationRunner implements MigrationRunner
{
    private const string STATEMENT_BREAK = '/^\s*-- statement-break\s*$/m';

    public function __construct(
        private Connection $connection,
        private string $directory,
    ) {
    }

    public function run(): array
    {
        $files = glob($this->directory . '/*.sql');
        if ($files === false || $files === []) {
            throw new RuntimeException("No migrations found in {$this->directory}");
        }
        sort($files, SORT_STRING);

        $applied = [];
        foreach ($files as $file) {
            $this->runFile($file);
            $applied[] = basename($file);
        }

        return $applied;
    }

    private function runFile(string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Cannot read migration $file");
        }

        $statements = preg_split(self::STATEMENT_BREAK, $sql) ?: [];
        foreach ($statements as $index => $statement) {
            if (trim((string) preg_replace('/^\s*--.*$/m', '', $statement)) === '') {
                continue;
            }
            try {
                $this->connection->pdo->exec($statement);
            } catch (\PDOException $error) {
                throw new RuntimeException(sprintf('Migration %s, statement %d failed: %s', basename($file), $index + 1, $error->getMessage()), 0, $error);
            }
        }
    }
}
