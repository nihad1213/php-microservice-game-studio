<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class TeamNameTaken extends ProblemException
{
    public function __construct(string $name)
    {
        parent::__construct("A team called {$name} already exists.", 409, ['name' => 'already used']);
    }
}