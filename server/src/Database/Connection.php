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
     */
    public function callScalar(string $procedure): mixed
    {
        if (preg_match('/^sp_[a-z0-9_]+$/', $procedure) !== 1) {
            throw new \InvalidArgumentException("Invalid procedure name: $procedure");
        }

        $statement = $this->pdo->prepare("CALL $procedure()");
        $statement->execute();
        $value = $statement->fetchColumn();
        // CALL returns an extra status result set; drain it so the connection can run the next query.
        while ($statement->nextRowset()) {
        }
        $statement->closeCursor();

        return $value;
    }
}
