<?php

declare(strict_types=1);

namespace Studio\Core\Http;

use RuntimeException;
use Throwable;

class ProblemException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}