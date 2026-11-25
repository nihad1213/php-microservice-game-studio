<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class Team
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $description,
        public ?int $leadDeveloperId,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            $row['description'],
            $row['lead_developer_id'] !== null ? (int) $row['lead_developer_id'] : null,
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function toArray(): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'description'       => $this->description,
            'lead_developer_id' => $this->leadDeveloperId,
            'created_at'        => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}