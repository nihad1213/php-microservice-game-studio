<?php

declare(strict_types=1);

namespace App\Domain;

interface GameDirectory
{
    /** @return string|null the game status (in_development, released, retired), or null if the game does not exist */
    public function status(int $gameId, ?string $correlationId = null): ?string;
}