<?php

declare(strict_types=1);

namespace BlazingSun\Api\Http;

final readonly class JsonResponse
{
    private const array DEFAULT_HEADERS = [
        'Content-Type' => 'application/json; charset=utf-8',
        'Cache-Control' => 'no-store',
        'X-Content-Type-Options' => 'nosniff',
    ];

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $status,
        private array $payload,
        private array $headers = [],
    ) {
    }

    public static function error(int $status, string $message): self
    {
        return new self($status, ['error' => $message]);
    }

    /** Returns a copy with one more header (PHP 8.5 clone-with on a readonly class). */
    public function withHeader(string $name, string $value): self
    {
        return clone($this, ['headers' => [...$this->headers, $name => $value]]);
    }

    public function status(): int
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return [...self::DEFAULT_HEADERS, ...$this->headers];
    }

    public function body(): string
    {
        return json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers() as $name => $value) {
            header("$name: $value");
        }
        echo $this->body();
    }
}
