<?php

declare(strict_types=1);

namespace BlazingSun\Api\Controllers\Admin;

use BlazingSun\Api\Auth\AdminAuth;
use BlazingSun\Api\Http\JsonResponse;
use BlazingSun\Api\Http\Request;

/** /api/admin/login, /session and /logout. */
final readonly class AuthController
{
    public function __construct(private AdminAuth $auth)
    {
    }

    /** POST /api/admin/login {username, password} → {username, csrf} */
    public function login(Request $request): JsonResponse
    {
        $body = $request->json();
        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;
        if (!is_string($username) || !is_string($password) || $username === '' || $password === '' || strlen($username) > 200 || strlen($password) > 1000) {
            return JsonResponse::error(400, 'Enter your username and password');
        }
        $result = $this->auth->login($request->ip, $username, $password);

        return $result instanceof JsonResponse ? $result : new JsonResponse(200, $result);
    }

    /** GET /api/admin/session → {username, csrf}, or 401 when not logged in. */
    public function show(Request $request): JsonResponse
    {
        $current = $this->auth->current();

        return $current === null ? JsonResponse::error(401, 'Please log in') : new JsonResponse(200, $current);
    }

    /** POST /api/admin/logout */
    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout();

        return new JsonResponse(200, ['ok' => true]);
    }
}
