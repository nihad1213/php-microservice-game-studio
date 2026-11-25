<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\DeveloperDirectory;
use App\Domain\LeadCannotBeRemoved;
use App\Domain\MemberNotFound;
use App\Domain\Team;
use App\Domain\TeamMember;
use App\Domain\TeamNotFound;
use App\Domain\TeamRepository;
use Psr\Log\LoggerInterface;
use Studio\Core\Event\DomainEvent;
use Studio\Core\Event\EventBus;
use Studio\Core\Http\ValidationFailed;
use Throwable;

final class TeamService
{
    public function __construct(
        private TeamRepository $teams,
        private DeveloperDirectory $developers,
        private EventBus $bus,
        private LoggerInterface $logger,
    ) {}

    /** @return array{0: Team[], 1: int} items and total count */
    public function list(int $page, int $perPage): array
    {
        return [$this->teams->findAll($perPage, ($page - 1) * $perPage), $this->teams->count()];
    }

    public function get(int $id): Team
    {
        return $this->teams->find($id) ?? throw new TeamNotFound($id);
    }

    public function create(TeamInput $in, ?string $correlationId = null): Team
    {
        $this->assertDeveloperExists($in->leadDeveloperId, $correlationId, 'lead_developer_id');

        return $this->teams->insert(new Team(null, $in->name, $in->description, $in->leadDeveloperId));
    }

    public function update(int $id, TeamInput $in, ?string $correlationId = null): Team
    {
        $team = $this->get($id);

        if ($in->leadDeveloperId !== $team->leadDeveloperId) {
            $this->assertDeveloperExists($in->leadDeveloperId, $correlationId, 'lead_developer_id');
        }

        $this->teams->update(
            new Team($id, $in->name, $in->description, $in->leadDeveloperId, $team->createdAt)
        );

        return $this->get($id);
    }

    public function delete(int $id): void
    {
        if (!$this->teams->delete($id)) {
            throw new TeamNotFound($id);
        }
    }

    /** @return array<int, array> members, with the developer data merged in when $expand is true */
    public function members(int $teamId, bool $expand, ?string $correlationId = null): array
    {
        $this->get($teamId);

        $members = $this->teams->members($teamId);
        $rows = array_map(fn (TeamMember $m) => $m->toArray(), $members);

        if (!$expand || $members === []) {
            return $rows;
        }

        $developers = $this->developers->findMany(
            array_map(fn (TeamMember $m) => $m->developerId, $members),
            $correlationId,
        );

        return array_map(
            fn (array $row) => $row + ['developer' => $developers[$row['developer_id']] ?? null],
            $rows,
        );
    }

    public function addMember(int $teamId, int $developerId, ?string $correlationId = null): TeamMember
    {
        $this->get($teamId);
        $this->assertDeveloperExists($developerId, $correlationId, 'developer_id');

        $member = $this->teams->addMember($teamId, $developerId);

        $this->publish(new DomainEvent(
            type: 'team.member_added',
            payload: ['team_id' => $teamId, 'developer_id' => $developerId],
            source: 'team-service',
            correlationId: $correlationId,
        ));

        return $member;
    }

    public function removeMember(int $teamId, int $developerId, ?string $correlationId = null): void
    {
        $team = $this->get($teamId);

        if ($team->leadDeveloperId === $developerId) {
            throw new LeadCannotBeRemoved($teamId, $developerId);
        }
        if (!$this->teams->removeMember($teamId, $developerId)) {
            throw new MemberNotFound($teamId, $developerId);
        }

        $this->publish(new DomainEvent(
            type: 'team.member_removed',
            payload: ['team_id' => $teamId, 'developer_id' => $developerId],
            source: 'team-service',
            correlationId: $correlationId,
        ));
    }

    private function assertDeveloperExists(?int $developerId, ?string $correlationId, string $field): void
    {
        if ($developerId !== null && !$this->developers->exists($developerId, $correlationId)) {
            throw new ValidationFailed([$field => 'developer does not exist']);
        }
    }

    private function publish(DomainEvent $event): void
    {
        try {
            $this->bus->publish($event);
        } catch (Throwable $e) {
            $this->logger->warning('Event publish failed', ['type' => $event->type, 'error' => $e->getMessage()]);
        }
    }
}