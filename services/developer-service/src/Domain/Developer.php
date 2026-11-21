<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final readonly class Developer
{
    public function __construct(
        public ?int $id,
        public string $firstName,
        public string $lastName,
        public string $email,
        public Role $role,
        public DateTimeImmutable $hiredAt,
        public ?DateTimeImmutable $createdAt = null,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['first_name'],
            $row['last_name'],
            $row['email'],
            Role::from($row['role']),
            new DateTimeImmutable($row['hired_at']),
            new DateTimeImmutable($row['created_at']),
        );
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->firstName,
            'last_name'  => $this->lastName,
            'email'      => $this->email,
            'role'       => $this->role->value,
            'hired_at'   => $this->hiredAt->format('Y-m-d'),
            'created_at' => $this->createdAt?->format(DATE_ATOM),
        ];
    }
}