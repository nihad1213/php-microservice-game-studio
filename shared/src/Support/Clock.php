<?php

declare(strict_types=1);

namespace Studio\Core\Support;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}