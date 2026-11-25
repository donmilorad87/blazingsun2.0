<?php

declare(strict_types=1);

namespace BlazingSun\Api\Auth;

/** In-memory session for tests. */
final class ArraySession implements Session
{
    /** @var array<string, mixed> */
    public array $data = [];

    public int $regenerations = 0;

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function regenerate(): void
    {
        $this->regenerations++;
    }

    public function destroy(): void
    {
        $this->data = [];
    }
}
