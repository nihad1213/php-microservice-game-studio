<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class InvalidTransition extends ProblemException
{
    public static function cannot(string $action, GameStatus $current): self
    {
        return new self("Cannot {$action} a game that is {$current->value}.", 409);
    }
}