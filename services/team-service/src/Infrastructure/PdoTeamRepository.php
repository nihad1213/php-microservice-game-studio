<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\MemberAlreadyInTeam;
use App\Domain\Team;
use App\Domain\TeamMember;
use App\Domain\TeamNameTaken;
use App\Domain\TeamRepository;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class PdoTeamRepository implements TeamRepository
{
    // MariaDB error code for a unique / primary key violation
    private const DUPLICATE_KEY = 1062;     

    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?Team
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teams WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Team::fromRow($row) : null;
    }

    public function findAll(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM teams ORDER BY id LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Team::fromRow(...), $stmt->fetchAll());
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM teams')->fetchColumn();
    }

    public function insert(Team $t): Team
    {
        $id = $this->transaction(function () use ($t): int {
            $stmt = $this->pdo->prepare(
                'INSERT INTO teams (name, description, lead_developer_id)
                 VALUES (:name, :description, :lead)'
            );
            $this->execute($stmt, [
                'name'        => $t->name,
                'description' => $t->description,
                'lead'        => $t->leadDeveloperId,
            ], $t->name);

            $id = (int) $this->pdo->lastInsertId();
            $this->registerLead($id, $t->leadDeveloperId);

            return $id;
        });

        return $this->find($id);
    }

    public function update(Team $t): void
    {
        $this->transaction(function () use ($t): void {
            $stmt = $this->pdo->prepare(
                'UPDATE teams SET name = :name, description = :description, lead_developer_id = :lead
                 WHERE id = :id'
            );
            $this->execute($stmt, [
                'id'          => $t->id,
                'name'        => $t->name,
                'description' => $t->description,
                'lead'        => $t->leadDeveloperId,
            ], $t->name);

            $this->registerLead($t->id, $t->leadDeveloperId);
        });
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM teams WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function members(int $teamId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT developer_id, joined_at FROM team_members
             WHERE team_id = :team ORDER BY joined_at, developer_id'
        );
        $stmt->execute(['team' => $teamId]);

        return array_map(TeamMember::fromRow(...), $stmt->fetchAll());
    }

    public function addMember(int $teamId, int $developerId): TeamMember
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO team_members (team_id, developer_id) VALUES (:team, :developer)'
            );
            $stmt->execute(['team' => $teamId, 'developer' => $developerId]);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === self::DUPLICATE_KEY) {
                throw new MemberAlreadyInTeam($teamId, $developerId);
            }
            throw $e;
        }

        $stmt = $this->pdo->prepare(
            'SELECT developer_id, joined_at FROM team_members
             WHERE team_id = :team AND developer_id = :developer'
        );
        $stmt->execute(['team' => $teamId, 'developer' => $developerId]);

        return TeamMember::fromRow($stmt->fetch());
    }

    public function removeMember(int $teamId, int $developerId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM team_members WHERE team_id = :team AND developer_id = :developer'
        );
        $stmt->execute(['team' => $teamId, 'developer' => $developerId]);

        return $stmt->rowCount() > 0;
    }

    /** The lead is always a member. INSERT IGNORE does nothing if the row already exists. */
    private function registerLead(int $teamId, ?int $leadDeveloperId): void
    {
        if ($leadDeveloperId === null) {
            return;
        }

        $this->pdo->prepare('INSERT IGNORE INTO team_members (team_id, developer_id) VALUES (:team, :developer)')
            ->execute(['team' => $teamId, 'developer' => $leadDeveloperId]);
    }

    private function execute(PDOStatement $stmt, array $params, string $teamName): void
    {
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === self::DUPLICATE_KEY) {
                throw new TeamNameTaken($teamName);
            }
            throw $e;
        }
    }

    private function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $work();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}