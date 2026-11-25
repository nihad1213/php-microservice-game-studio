<?php

declare(strict_types=1);

namespace App\Domain;

interface DeveloperDirectory
{
    public function exists(int $developerId, ?string $correlationId = null): bool;

    /**
     * @param int[] $developerIds
     * @return array<int, array|null> developer id => developer data, or null if it no longer exists
     */
    public function findMany(array $developerIds, ?string $correlationId = null): array;
}