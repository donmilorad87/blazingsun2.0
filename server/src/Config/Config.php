<?php

declare(strict_types=1);

namespace BlazingSun\Api\Config;

/**
 * Validated application settings. Built from config/config.php, which the deploy writes from GitHub secrets.
 */
final readonly class Config
{
    private const array REQUIRED_DB_KEYS = ['host', 'port', 'name', 'user', 'password'];

    /** @param array<string, string> $db */
    private function __construct(
        private array $db,
        private string $migrationToken,
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

        return new self(
            array_map(static fn (mixed $value): string => (string) $value, array_intersect_key($db, array_flip(self::REQUIRED_DB_KEYS))),
            is_string($token) ? $token : '',
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

    /** Empty string disables the migrations endpoint. */
    public function migrationToken(): string
    {
        return $this->migrationToken;
    }
}
