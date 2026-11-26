<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class SaleFilter
{
    public function __construct(
        public ?int $gameId = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
        public ?Region $region = null,
        public ?Platform $platform = null,
    ) {}
}