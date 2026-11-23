<?php

declare(strict_types=1);

namespace Studio\Core\Event;

use Psr\Log\LoggerInterface;

final class LogEventBus implements EventBus
{
    public function __construct(private LoggerInterface $logger) {}

    public function publish(DomainEvent $event): void
    {
        $this->logger->info('domain_event', $event->toArray());
    }
}