<?php

declare(strict_types=1);

namespace BlazingSun\Api\Content;

use RuntimeException;

/** Submitted data failed validation; becomes a 422 response listing every field error. */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors path => message */
    public function __construct(public readonly array $errors)
    {
        parent::__construct('Validation failed: ' . implode(', ', array_keys($errors)));
    }

    public static function single(string $path, string $message): self
    {
        return new self([$path => $message]);
    }
}
