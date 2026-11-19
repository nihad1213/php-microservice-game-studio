<?php

declare(strict_types=1);

namespace Studio\Core\Event;

use DateTimeImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

final readonly class DomainEvent
{
    public string $id;

    public DateTimeImmutable $occurredAt;

    public function __construct(
        public string $name,
        public array $payload,
        public ?string $correlationId = null,
        ?string $id = null,
        ?DateTimeImmutable $occurredAt = null
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException(
                'Event name cannot be empty.'
            );
        }

        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'payload' => $this->payload,
            'correlation_id' => $this->correlationId,
            'occurred_at' => $this->occurredAt->format(
                DateTimeImmutable::ATOM
            ),
        ];
    }
}
