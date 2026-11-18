<?php

declare(strict_types=1);

namespace BlazingSun\Api\Http;

use Uri\Rfc3986\Uri;

final readonly class Request
{
    private const string BASE_PATH = '/api';

    public string $method;

    /** @var array<string, string> lower-cased header names */
    private array $headers;

    /** @param array<string, string> $headers */
    public function __construct(string $method, private string $uri, array $headers = [])
    {
        $this->method = strtoupper($method);
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }

    public static function fromGlobals(): self
    {
        // Apache/LiteSpeed rewrites move the Authorization header to REDIRECT_HTTP_AUTHORIZATION.
        $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        return new self(
            is_string($method) ? $method : 'GET',
            is_string($uri) ? $uri : '/',
            is_string($authorization) ? ['authorization' => $authorization] : [],
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    /** Path relative to /api, without query string or trailing slash, e.g. "/visits". */
    public function path(): string
    {
        // PHP 8.5 RFC 3986 parser (always built in); invalid URIs are treated as the root.
        return (Uri::parse($this->uri)?->getPath() ?? '/')
            |> self::stripBasePath(...)
            |> (static fn (string $path): string => rtrim($path, '/'))
            |> (static fn (string $path): string => $path === '' ? '/' : $path);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if ($header === null || preg_match('/^Bearer\s+(\S+)$/', $header, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    private static function stripBasePath(string $path): string
    {
        return $path === self::BASE_PATH || str_starts_with($path, self::BASE_PATH . '/')
            ? substr($path, strlen(self::BASE_PATH))
            : $path;
    }
}
