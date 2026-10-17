<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Represents a business/operational rule violation that should be surfaced
 * to the operator with a clear, actionable message (FR-06, FR-08, FR-15).
 */
class OperationException extends RuntimeException
{
    protected int $statusCode;

    /**
     * @var array<string, array<int, string>>
     */
    protected array $errors;

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        string $message,
        int $statusCode = 422,
        array $errors = [],
    ) {
        parent::__construct($message);

        $this->statusCode = $statusCode;
        $this->errors = $errors !== [] ? $errors : ['operation' => [$message]];
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function make(string $message, int $statusCode = 422, array $errors = []): self
    {
        return new self($message, $statusCode, $errors);
    }
}
