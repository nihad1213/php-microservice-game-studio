<?php

declare(strict_types=1);

namespace Studio\Core\Event;

interface EventBus
{
    public function publish(DomainEvent $event): void;
}