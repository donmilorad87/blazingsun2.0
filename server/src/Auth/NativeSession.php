<?php

declare(strict_types=1);

namespace BlazingSun\Api\Auth;

use RuntimeException;

/**
 * PHP's own session, started only when an admin endpoint needs it. The cookie is HttpOnly, SameSite=Strict,
 * Secure over HTTPS, and scoped to /api/admin, so the public page never sends or receives it.
 */
final class NativeSession implements Session
{
    public const string COOKIE = 'blazing_admin';

    private bool $started = false;

    public function __construct(private readonly bool $secure)
    {
    }

    public function get(string $key): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->start();
        if (!session_regenerate_id(true)) {
            throw new RuntimeException('Could not regenerate the session id');
        }
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        setcookie(self::COOKIE, '', ['expires' => 1, 'path' => '/api/admin', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Strict']);
        session_destroy();
        $this->started = false;
    }

    private function start(): void
    {
        if ($this->started) {
            return;
        }
        session_name(self::COOKIE);
        session_set_cookie_params(['lifetime' => 0, 'path' => '/api/admin', 'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Strict']);
        $started = session_start([
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'cookie_httponly' => true,
        ]);
        if (!$started) {
            throw new RuntimeException('Could not start the admin session');
        }
        $this->started = true;
    }
}
