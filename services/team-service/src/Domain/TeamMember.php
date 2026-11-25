<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class TeamMember
{
    public function __construct(
        public int $developerId,
        public DateTimeImmutable $joinedAt,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self((int) $row['developer_id'], new DateTimeImmutable($row['joined_at']));
    }

    public function toArray(): array
    {
        return [
            'developer_id' => $this->developerId,
            'joined_at'    => $this->joinedAt->format(DATE_ATOM),
        ];
    }
}