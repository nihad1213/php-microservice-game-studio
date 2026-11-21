<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Developer;
use App\Domain\DeveloperNotFound;
use App\Domain\DeveloperRepository;
use App\Domain\Role;

final class DeveloperService
{
    public function __construct(private DeveloperRepository $repo) {}

    /** @return array{0: Developer[], 1: int} items and total count */
    public function list(int $page, int $perPage, ?Role $role): array
    {
        $items = $this->repo->findAll($perPage, ($page - 1) * $perPage, $role);

        return [$items, $this->repo->count($role)];
    }

    public function get(int $id): Developer
    {
        return $this->repo->find($id) ?? throw new DeveloperNotFound($id);
    }

    public function create(DeveloperInput $in): Developer
    {
        return $this->repo->insert(
            new Developer(null, $in->firstName, $in->lastName, $in->email, $in->role, $in->hiredAt)
        );
    }

    public function update(int $id, DeveloperInput $in): Developer
    {
        $existing = $this->get($id);

        $this->repo->update(new Developer(
            $id, $in->firstName, $in->lastName, $in->email, $in->role, $in->hiredAt, $existing->createdAt
        ));

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        if (!$this->repo->delete($id)) {
            throw new DeveloperNotFound($id);
        }
    }
}