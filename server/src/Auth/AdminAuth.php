<?php

declare(strict_types=1);

namespace BlazingSun\Api\Auth;

use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use Closure;

/**
 * The single admin: credentials come from the config (username + argon2id hash), the login lives in the session.
 * - Rate limit: MAX_FAILURES failed logins per IP per WINDOW_SECONDS, then 429.
 * - CSRF: a random token is issued at login; every state-changing request must echo it in X-CSRF-Token.
 * - Idle sessions expire after IDLE_SECONDS.
 */
final readonly class AdminAuth
{
    public const int MAX_FAILURES = 5;

    public const int WINDOW_SECONDS = 900;

    public const int IDLE_SECONDS = 7200;

    /** @param Closure(): int $clock current Unix time */
    public function __construct(
        private Session $session,
        private LoginThrottle $throttle,
        private string $username,
        private string $passwordHash,
        private Closure $clock,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->username !== '' && $this->passwordHash !== '';
    }

    /** @return array{username: string, csrf: string}|JsonResponse the session on success, the error response otherwise */
    public function login(string $ip, string $username, string $password): array|JsonResponse
    {
        if (!$this->isEnabled()) {
            return JsonResponse::error(404, 'Not found');
        }
        if ($this->throttle->failures($ip, self::WINDOW_SECONDS) >= self::MAX_FAILURES) {
            return JsonResponse::error(429, 'Too many failed logins. Try again in 15 minutes.')
                ->withHeader('Retry-After', (string) self::WINDOW_SECONDS);
        }
        // Always run password_verify, so a wrong username takes as long as a wrong password.
        $passwordOk = password_verify($password, $this->passwordHash);
        if (!hash_equals($this->username, $username) || !$passwordOk) {
            $this->throttle->recordFailure($ip);

            return JsonResponse::error(401, 'Wrong username or password');
        }

        $this->throttle->clear($ip);
        $this->session->regenerate();
        $csrf = bin2hex(random_bytes(32));
        $this->session->set('admin', $this->username);
        $this->session->set('csrf', $csrf);
        $this->session->set('seen', ($this->clock)());

        return ['username' => $this->username, 'csrf' => $csrf];
    }

    /** @return array{username: string, csrf: string}|null the logged-in session (refreshing its idle timer), or null */
    public function current(): ?array
    {
        $admin = $this->session->get('admin');
        $csrf = $this->session->get('csrf');
        $seen = $this->session->get('seen');
        if (!is_string($admin) || !is_string($csrf) || !is_int($seen) || !$this->isEnabled() || !hash_equals($this->username, $admin)) {
            return null;
        }
        $now = ($this->clock)();
        if ($now - $seen > self::IDLE_SECONDS) {
            $this->session->destroy();
            return null;
        }
        $this->session->set('seen', $now);

        return ['username' => $admin, 'csrf' => $csrf];
    }

    /** null when the request may proceed, otherwise the 401/403 response. */
    public function guard(Request $request): ?JsonResponse
    {
        $current = $this->current();
        if ($current === null) {
            return JsonResponse::error(401, 'Please log in');
        }
        if ($request->method() !== 'GET') {
            $token = $request->header('X-CSRF-Token') ?? '';
            if (!hash_equals($current['csrf'], $token)) {
                return JsonResponse::error(403, 'Invalid CSRF token, reload the page');
            }
        }

        return null;
    }

    public function logout(): void
    {
        $this->session->destroy();
    }
}
