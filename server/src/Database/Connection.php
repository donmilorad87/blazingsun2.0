<?php

declare(strict_types=1);

namespace BlazingSun\Api\Database;

use BlazingSun\Api\Config\Config;
use PDO;

/** Lazily opens one PDO connection per request, so routes that don't need the database never connect. */
final class Connection
{
    private ?PDO $handle = null;

    /** Connects on first access (PHP 8.4 property hook). */
    public PDO $pdo {
        get => $this->handle ??= new PDO($this->config->dsn(), $this->config->dbUser(), $this->config->dbPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Calls a stored procedure and returns the first column of its first row.
     * Application code only talks to the database through procedures.
     *
     * @param list<string|int|null> $params
     */
    public function callScalar(string $procedure, array $params = []): mixed
    {
        $rows = $this->call($procedure, $params);

        return $rows === [] ? false : reset($rows[0]);
    }

    /**
     * Calls a stored procedure with positional parameters and returns the rows of its first result set.
     *
     * @param list<string|int|null> $params
     * @return list<array<string, mixed>>
     */
    public function call(string $procedure, array $params = []): array
    {
        if (preg_match('/^sp_[a-z0-9_]+$/', $procedure) !== 1) {
            throw new \InvalidArgumentException("Invalid procedure name: $procedure");
        }

        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        $statement = $this->pdo->prepare("CALL $procedure($placeholders)");
        foreach ($params as $index => $value) {
            $statement->bindValue($index + 1, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $statement->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->columnCount() > 0 ? $statement->fetchAll() : [];
        // CALL returns an extra status result set; drain it so the connection can run the next query.
        while ($statement->nextRowset()) {
        }
        $statement->closeCursor();

        return $rows;
    }

    /**
     * Runs $work in a transaction: committed if it returns, rolled back (and rethrown) if it throws.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }
}
