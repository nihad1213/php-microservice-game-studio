<?php

declare(strict_types=1);

namespace App\Domain;

interface TeamRepository
{
    public function find(int $id): ?Team;

    /** @return Team[] */
    public function findAll(int $limit, int $offset): array;

    public function count(): int;

    /**
     * Invariant: the lead developer of a team is always a member too.
     * @throws TeamNameTaken
     */
    public function insert(Team $team): Team;

    /** @throws TeamNameTaken */
    public function update(Team $team): void;

    /** Members are removed with the team (foreign key cascade). */
    public function delete(int $id): bool;

    /** @return TeamMember[] */
    public function members(int $teamId): array;

    /** @throws MemberAlreadyInTeam */
    public function addMember(int $teamId, int $developerId): TeamMember;

    public function removeMember(int $teamId, int $developerId): bool;
}