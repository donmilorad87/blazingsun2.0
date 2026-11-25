<?php

declare(strict_types=1);

namespace BlazingSun\Api\Auth;

/** Counts failed admin logins per IP. */
interface LoginThrottle
{
    /** Failed logins from $ip within the last $windowSeconds. */
    public function failures(string $ip, int $windowSeconds): int;

    public function recordFailure(string $ip): void;

    public function clear(string $ip): void;
}
