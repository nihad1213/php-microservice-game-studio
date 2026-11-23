<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Game;
use App\Domain\GameAlreadyExists;
use App\Domain\GameFilter;
use App\Domain\GameRepository;
use PDO;
use PDOException;
use PDOStatement;

final class PdoGameRepository implements GameRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?Game
    {
        $stmt = $this->pdo->prepare('SELECT * FROM games WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Game::fromRow($row) : null;
    }

    public function findAll(int $limit, int $offset, GameFilter $filter): array
    {
        [$where, $params] = $this->where($filter);

        $stmt = $this->pdo->prepare("SELECT * FROM games{$where} ORDER BY id LIMIT :limit OFFSET :offset");
        $this->bindAll($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Game::fromRow(...), $stmt->fetchAll());
    }

    public function count(GameFilter $filter): int
    {
        [$where, $params] = $this->where($filter);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM games{$where}");
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function insert(Game $g): Game
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO games (title, genre, platform, price_cents, status, team_id, release_date)
             VALUES (:title, :genre, :platform, :price_cents, :status, :team_id, :release_date)'
        );
        $this->run($stmt, $this->params($g), $g);

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function update(Game $g): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE games SET title = :title, genre = :genre, platform = :platform,
                    price_cents = :price_cents, status = :status, team_id = :team_id,
                    release_date = :release_date
             WHERE id = :id'
        );
        $this->run($stmt, $this->params($g) + ['id' => $g->id], $g);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM games WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function params(Game $g): array
    {
        return [
            'title'        => $g->title,
            'genre'        => $g->genre,
            'platform'     => $g->platform->value,
            'price_cents'  => $g->priceCents,
            'status'       => $g->status->value,
            'team_id'      => $g->teamId,
            'release_date' => $g->releaseDate?->format('Y-m-d'),
        ];
    }

    /** @return array{0: string, 1: array<string, int|string>} */
    private function where(GameFilter $f): array
    {
        $parts = [];
        $params = [];

        if ($f->status !== null)   { $parts[] = 'status = :status';     $params['status']   = $f->status->value; }
        if ($f->platform !== null) { $parts[] = 'platform = :platform'; $params['platform'] = $f->platform->value; }
        if ($f->teamId !== null)   { $parts[] = 'team_id = :team_id';   $params['team_id']  = $f->teamId; }
        if ($f->genre !== null)    { $parts[] = 'genre = :genre';       $params['genre']    = $f->genre; }

        return [$parts ? ' WHERE ' . implode(' AND ', $parts) : '', $params];
    }

    private function bindAll(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $name => $value) {
            $stmt->bindValue(':' . $name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }

    private function run(PDOStatement $stmt, array $params, Game $g): void
    {
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new GameAlreadyExists($g->title, $g->platform->value);
            }
            throw $e;
        }
    }
}