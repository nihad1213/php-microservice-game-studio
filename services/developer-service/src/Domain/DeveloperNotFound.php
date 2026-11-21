<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class DeveloperNotFound extends ProblemException
{
    public function __construct(int $id)
    {
        parent::__construct("Developer {$id} was not found.", 404);
    }
}