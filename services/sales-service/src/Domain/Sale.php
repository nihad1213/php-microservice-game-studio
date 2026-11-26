<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeZone;

final readonly class Sale
{
    public function __construct(
        public ?int $id,
        public int $gameId,
        public DateTimeImmutable $soldAt,
        public int $units,
        public int $unitPriceCents,
        public Currency $currency,
        public Region $region,
        public Platform $platform,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function fromRow(array $row): self
    {
        $utc = new DateTimeZone('UTC');

        return new self(
            (int) $row['id'],
            (int) $row['game_id'],
            new DateTimeImmutable($row['sold_at'], $utc),
            (int) $row['units'],
            (int) $row['unit_price_cents'],
            Currency::from($row['currency']),
            Region::from($row['region']),
            Platform::from($row['platform']),
            new DateTimeImmutable($row['created_at'], $utc),
        );
    }

    public function totalCents(): int
    {
        return $this->units * $this->unitPriceCents;
    }

    public function toArray(): array
    {
        return [
            'id'               => $this->id,
            'game_id'          => $this->gameId,
            'sold_at'          => $this->soldAt->format(DATE_ATOM),
            'units'            => $this->units,
            'unit_price_cents' => $this->unitPriceCents,
            'total_cents'      => $this->totalCents(),
            'currency'         => $this->currency->value,
            'region'           => $this->region->value,
            'platform'         => $this->platform->value,
            'created_at'       => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}