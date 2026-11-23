<?php

declare(strict_types=1);

namespace App\Domain;

use Studio\Core\Http\ProblemException;

final class GameAlreadyExists extends ProblemException
{
    public function __construct(string $title, string $platform)
    {
        parent::__construct(
            "A game called {$title} already exists for {$platform}.",
            409,
            ['title' => 'already exists for this platform']
        );
    }
}