<?php

declare(strict_types=1);

namespace BlazingSun\Api\Auth;

/** The admin's server-side session (native PHP session in production, an array in tests). */
interface Session
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value): void;

    /** New session id after login (prevents session fixation). */
    public function regenerate(): void;

    public function destroy(): void;
}
