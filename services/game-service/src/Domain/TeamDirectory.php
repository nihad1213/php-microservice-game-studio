<?php

declare(strict_types=1);

namespace App\Domain;

interface TeamDirectory
{
    public function exists(int $teamId, ?string $correlationId = null): bool;
}