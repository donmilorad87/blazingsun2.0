<?php

declare(strict_types=1);

namespace BlazingSun\Api\Tests\Unit;

use BlazingSun\Api\Auth\AdminAuth;
use BlazingSun\Api\Auth\ArraySession;
use BlazingSun\Api\Auth\LoginThrottle;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;
use PHPUnit\Framework\TestCase;

final class AdminAuthTest extends TestCase
{
    private ArraySession $session;

    private FakeThrottle $throttle;

    private int $now = 1_000_000;

    protected function setUp(): void
    {
        $this->session = new ArraySession();
        $this->throttle = new FakeThrottle();
    }

    private function auth(string $username = 'admin'): AdminAuth
    {
        // Cheap hash parameters: these tests are about the flow, not hashing cost.
        $hash = password_hash('right-password', PASSWORD_ARGON2ID, ['memory_cost' => 1024, 'time_cost' => 1, 'threads' => 1]);

        return new AdminAuth($this->session, $this->throttle, $username, $hash, fn (): int => $this->now);
    }

    public function testLoginStartsAFreshSessionWithACsrfToken(): void
    {
        $result = $this->auth()->login('1.2.3.4', 'admin', 'right-password');

        self::assertIsArray($result);
        self::assertSame('admin', $result['username']);
        self::assertSame(64, strlen($result['csrf']));
        self::assertSame(1, $this->session->regenerations);
        self::assertSame($result, $this->auth()->current());
        self::assertSame(['1.2.3.4'], $this->throttle->cleared);
    }

    public function testWrongCredentialsAreCountedAndRejected(): void
    {
        foreach ([['admin', 'nope'], ['root', 'right-password']] as [$user, $password]) {
            $result = $this->auth()->login('1.2.3.4', $user, $password);
            self::assertInstanceOf(JsonResponse::class, $result);
            self::assertSame(401, $result->status());
        }
        self::assertSame(2, $this->throttle->failures('1.2.3.4', 900));
        self::assertNull($this->auth()->current());
    }

    public function testTooManyFailuresLockTheIpOut(): void
    {
        $this->throttle->count = AdminAuth::MAX_FAILURES;
        $result = $this->auth()->login('1.2.3.4', 'admin', 'right-password');

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(429, $result->status());
        self::assertSame([], $this->session->data);
    }

    public function testLoginIsDisabledWithoutConfiguredCredentials(): void
    {
        $result = $this->auth('')->login('1.2.3.4', '', 'right-password');

        self::assertInstanceOf(JsonResponse::class, $result);
        self::assertSame(404, $result->status());
    }

    public function testGuardNeedsALoginAndTheCsrfTokenForChanges(): void
    {
        $auth = $this->auth();
        self::assertSame(401, $auth->guard(new Request('GET', '/api/admin/content'))?->status());

        $result = $auth->login('1.2.3.4', 'admin', 'right-password');
        self::assertIsArray($result);
        self::assertNull($auth->guard(new Request('GET', '/api/admin/content')));
        self::assertSame(403, $auth->guard(new Request('POST', '/api/admin/section'))?->status());
        self::assertSame(403, $auth->guard(new Request('POST', '/api/admin/section', ['X-CSRF-Token' => 'forged']))?->status());
        self::assertNull($auth->guard(new Request('POST', '/api/admin/section', ['X-CSRF-Token' => $result['csrf']])));
    }

    public function testIdleSessionsExpire(): void
    {
        $auth = $this->auth();
        $auth->login('1.2.3.4', 'admin', 'right-password');
        $this->now += AdminAuth::IDLE_SECONDS - 1;
        self::assertNotNull($auth->current(), 'activity keeps the session alive');
        $this->now += AdminAuth::IDLE_SECONDS + 1;
        self::assertNull($auth->current());
        self::assertSame([], $this->session->data);
    }

    public function testLogoutEndsTheSession(): void
    {
        $auth = $this->auth();
        $auth->login('1.2.3.4', 'admin', 'right-password');
        $auth->logout();

        self::assertNull($auth->current());
    }
}

final class FakeThrottle implements LoginThrottle
{
    public int $count = 0;

    /** @var list<string> */
    public array $cleared = [];

    public function failures(string $ip, int $windowSeconds): int
    {
        return $this->count;
    }

    public function recordFailure(string $ip): void
    {
        $this->count++;
    }

    public function clear(string $ip): void
    {
        $this->cleared[] = $ip;
        $this->count = 0;
    }
}
