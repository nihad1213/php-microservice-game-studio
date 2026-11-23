<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class GameFilter
{
    public function __construct(
        public ?GameStatus $status = null,
        public ?Platform $platform = null,
        public ?int $teamId = null,
        public ?string $genre = null,
    ) {}
}