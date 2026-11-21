<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class EmailAlreadyUsed extends ProblemException
{
    public function __construct(string $email)
    {
        parent::__construct("Email {$email} is already used.", 409, ['email' => 'already used']);
    }
}