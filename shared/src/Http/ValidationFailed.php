<?php

declare(strict_types=1);

namespace Studio\Core\Http;

final class ValidationFailed extends ProblemException
{
    public function __construct(array $errors)
    {
        parent::__construct('Validation failed.', 422, $errors);
    }
}