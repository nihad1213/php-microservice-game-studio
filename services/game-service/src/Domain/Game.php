<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class Game
{
    public function __construct(
        public ?int $id,
        public string $title,
        public string $genre,
        public Platform $platform,
        public int $priceCents,
        public GameStatus $status,
        public int $teamId,
        public ?DateTimeImmutable $releaseDate = null,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['title'],
            $row['genre'],
            Platform::from($row['platform']),
            (int) $row['price_cents'],
            GameStatus::from($row['status']),
            (int) $row['team_id'],
            $row['release_date'] !== null ? new DateTimeImmutable($row['release_date']) : null,
            new DateTimeImmutable($row['created_at']),
        );
    }

    /** in_development -> released */
    public function release(DateTimeImmutable $now): self
    {
        if ($this->status !== GameStatus::InDevelopment) {
            throw InvalidTransition::cannot('release', $this->status);
        }

        return $this->with(status: GameStatus::Released, releaseDate: $now);
    }

    /** released -> retired */
    public function retire(): self
    {
        if ($this->status !== GameStatus::Released) {
            throw InvalidTransition::cannot('retire', $this->status);
        }

        return $this->with(status: GameStatus::Retired);
    }

    public function withDetails(string $title, string $genre, Platform $platform, int $priceCents, int $teamId): self
    {
        if ($this->status === GameStatus::Retired) {
            throw InvalidTransition::cannot('edit', $this->status);
        }

        return $this->with(
            title: $title, genre: $genre, platform: $platform, priceCents: $priceCents, teamId: $teamId
        );
    }

    public function canBeDeleted(): bool
    {
        return $this->status === GameStatus::InDevelopment;
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'genre'        => $this->genre,
            'platform'     => $this->platform->value,
            'price_cents'  => $this->priceCents,
            'status'       => $this->status->value,
            'team_id'      => $this->teamId,
            'release_date' => $this->releaseDate?->format('Y-m-d'),
            'created_at'   => $this->createdAt?->format(DATE_ATOM),
        ];
    }

    private function with(
        ?string $title = null, ?string $genre = null, ?Platform $platform = null, ?int $priceCents = null,
        ?GameStatus $status = null, ?int $teamId = null, ?DateTimeImmutable $releaseDate = null,
    ): self {
        return new self(
            $this->id,
            $title ?? $this->title,
            $genre ?? $this->genre,
            $platform ?? $this->platform,
            $priceCents ?? $this->priceCents,
            $status ?? $this->status,
            $teamId ?? $this->teamId,
            $releaseDate ?? $this->releaseDate,
            $this->createdAt,
        );
    }
}