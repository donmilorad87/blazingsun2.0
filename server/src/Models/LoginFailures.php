<?php

declare(strict_types=1);

namespace BlazingSun\Api\Models;

use BlazingSun\Api\Auth\LoginThrottle;
use BlazingSun\Api\Database\Connection;

/** Failed admin logins (login_failures table), through stored procedures. */
final readonly class LoginFailures implements LoginThrottle
{
    public function __construct(private Connection $connection)
    {
    }

    public function failures(string $ip, int $windowSeconds): int
    {
        return (int) $this->connection->callScalar('sp_login_failure_count', [$ip, $windowSeconds]);
    }

    public function recordFailure(string $ip): void
    {
        $this->connection->call('sp_login_failure_record', [$ip]);
    }

    public function clear(string $ip): void
    {
        $this->connection->call('sp_login_failure_clear', [$ip]);
    }
}
