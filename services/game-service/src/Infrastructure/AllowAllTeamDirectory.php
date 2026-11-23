<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\TeamDirectory;

final class AllowAllTeamDirectory implements TeamDirectory
{
    public function exists(int $teamId, ?string $correlationId = null): bool
    {
        return true;
    }
}