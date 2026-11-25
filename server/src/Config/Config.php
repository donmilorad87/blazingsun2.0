<?php

declare(strict_types=1);

namespace BlazingSun\Api\Config;

/**
 * Validated application settings. Built from config/config.php, which the deploy writes from GitHub secrets.
 */
final readonly class Config
{
    private const array REQUIRED_DB_KEYS = ['host', 'port', 'name', 'user', 'password'];

    /** dist: the built site; uploads: admin uploads; cache: rendered pages; hot: the Vite dev-server file. */
    private const array PATHS = ['dist', 'uploads', 'cache', 'hot'];

    /**
     * @param array<string, string> $db
     * @param array<string, string> $paths optional overrides, see PATHS
     * @param array{username: string, password_hash: string} $admin empty strings disable the admin login
     */
    private function __construct(
        private array $db,
        private string $migrationToken,
        private string $siteUrl,
        private array $paths,
        private array $admin,
    ) {
    }

    /** @param array<string, mixed> $values */
    #[\NoDiscard]
    public static function fromArray(array $values): self
    {
        $db = is_array($values['db'] ?? null) ? $values['db'] : [];

        $missing = [];
        foreach (self::REQUIRED_DB_KEYS as $key) {
            if (!isset($db[$key]) || !is_scalar($db[$key]) || (string) $db[$key] === '') {
                $missing[] = "db.$key";
            }
        }
        if ($missing !== []) {
            // Only key names: config values (passwords) must never reach logs.
            throw new ConfigException('Missing config values: ' . implode(', ', $missing));
        }
        if (!ctype_digit((string) $db['port'])) {
            throw new ConfigException('Invalid config value: db.port must be a number');
        }

        $token = $values['migration_token'] ?? '';
        $siteUrl = rtrim(self::string($values['site_url'] ?? null, 'https://blazingsun.space'), '/');
        if (preg_match('~^https?://[^/\s]+$~', $siteUrl) !== 1) {
            throw new ConfigException('Invalid config value: site_url must be like https://example.com');
        }
        $paths = is_array($values['paths'] ?? null) ? $values['paths'] : [];
        $admin = is_array($values['admin'] ?? null) ? $values['admin'] : [];

        return new self(
            array_map(static fn (mixed $value): string => (string) $value, array_intersect_key($db, array_flip(self::REQUIRED_DB_KEYS))),
            is_string($token) ? $token : '',
            $siteUrl,
            array_filter(array_map(static fn (mixed $path): string => self::string($path, ''), array_intersect_key($paths, array_flip(self::PATHS))), static fn (string $path): bool => $path !== ''),
            ['username' => self::string($admin['username'] ?? null, ''), 'password_hash' => self::string($admin['password_hash'] ?? null, '')],
        );
    }

    /** Loads the PHP config file (it returns an array). */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new ConfigException("Config file not found: $path");
        }
        $values = require $path;
        if (!is_array($values)) {
            throw new ConfigException('Config file must return an array');
        }

        return self::fromArray($values);
    }

    public function dsn(): string
    {
        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $this->db['host'], $this->db['port'], $this->db['name']);
    }

    public function dbUser(): string
    {
        return $this->db['user'];
    }

    public function dbPassword(): string
    {
        return $this->db['password'];
    }

    /** Public origin without trailing slash, for canonical and hreflang URLs. */
    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    /** Configured path, or $default. $name: one of PATHS */
    public function path(string $name, string $default): string
    {
        return rtrim($this->paths[$name] ?? $default, '/');
    }

    /** Admin login name; empty string disables the admin portal login. */
    public function adminUsername(): string
    {
        return $this->admin['username'];
    }

    /** password_hash() of the admin password (argon2id). */
    public function adminPasswordHash(): string
    {
        return $this->admin['password_hash'];
    }

    private static function string(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    /** Empty string disables the migrations endpoint. */
    public function migrationToken(): string
    {
        return $this->migrationToken;
    }
}
