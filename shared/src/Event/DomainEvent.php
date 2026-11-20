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
        public string $type,
        public array $payload,
        public string $source = '',
        public int $version = 1,
        public ?string $correlationId = null,
        ?string $id = null,
        ?DateTimeImmutable $occurredAt = null,
    ) {
        if (trim($type) === '') {
            throw new InvalidArgumentException('Event type cannot be empty.');
        }
        $this->id = $id ?? Uuid::uuid4()->toString();
        $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'version' => $this->version,
            'source' => $this->source,
            'correlation_id' => $this->correlationId,
            'occurred_at' => $this->occurredAt->format(DateTimeImmutable::ATOM),
            'payload' => $this->payload,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromArray(array $d): self
    {
        return new self(
            $d['type'], $d['payload'], $d['source'] ?? '', (int) ($d['version'] ?? 1),
            $d['correlation_id'] ?? null, $d['id'] ?? null,
            isset($d['occurred_at']) ? new DateTimeImmutable($d['occurred_at']) : null,
        );
    }
}