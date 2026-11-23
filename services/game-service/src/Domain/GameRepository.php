<?php

declare(strict_types=1);

namespace App\Domain;

interface GameRepository
{
    public function find(int $id): ?Game;

    /** @return Game[] */
    public function findAll(int $limit, int $offset, GameFilter $filter): array;

    public function count(GameFilter $filter): int;

    /** @throws GameAlreadyExists */
    public function insert(Game $game): Game;

    /** @throws GameAlreadyExists */
    public function update(Game $game): void;

    public function delete(int $id): bool;
}