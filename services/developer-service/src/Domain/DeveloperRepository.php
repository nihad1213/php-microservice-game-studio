<?php

declare(strict_types=1);

namespace App\Domain;

interface DeveloperRepository
{
    public function find(int $id): ?Developer;

    /** @return Developer[] */
    public function findAll(int $limit, int $offset, ?Role $role = null): array;

    public function count(?Role $role = null): int;

    /** @throws EmailAlreadyUsed */
    public function insert(Developer $developer): Developer;

    /** @throws EmailAlreadyUsed */
    public function update(Developer $developer): void;

    public function delete(int $id): bool;
}