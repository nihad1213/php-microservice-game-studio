<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Developer;
use App\Domain\DeveloperRepository;
use App\Domain\EmailAlreadyUsed;
use App\Domain\Role;
use PDO;
use PDOException;
use PDOStatement;

final class PdoDeveloperRepository implements DeveloperRepository
{
    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?Developer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM developers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Developer::fromRow($row) : null;
    }

    public function findAll(int $limit, int $offset, ?Role $role = null): array
    {
        $sql = 'SELECT * FROM developers'
             . ($role ? ' WHERE role = :role' : '')
             . ' ORDER BY id LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        if ($role) {
            $stmt->bindValue(':role', $role->value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Developer::fromRow(...), $stmt->fetchAll());
    }

    public function count(?Role $role = null): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM developers' . ($role ? ' WHERE role = :role' : '')
        );
        $stmt->execute($role ? ['role' => $role->value] : []);

        return (int) $stmt->fetchColumn();
    }

    public function insert(Developer $d): Developer
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO developers (first_name, last_name, email, role, hired_at)
             VALUES (:first_name, :last_name, :email, :role, :hired_at)'
        );
        $this->run($stmt, [
            'first_name' => $d->firstName,
            'last_name'  => $d->lastName,
            'email'      => $d->email,
            'role'       => $d->role->value,
            'hired_at'   => $d->hiredAt->format('Y-m-d'),
        ], $d->email);

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function update(Developer $d): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE developers
             SET first_name = :first_name, last_name = :last_name, email = :email,
                 role = :role, hired_at = :hired_at
             WHERE id = :id'
        );
        $this->run($stmt, [
            'id'         => $d->id,
            'first_name' => $d->firstName,
            'last_name'  => $d->lastName,
            'email'      => $d->email,
            'role'       => $d->role->value,
            'hired_at'   => $d->hiredAt->format('Y-m-d'),
        ], $d->email);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM developers WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function run(PDOStatement $stmt, array $params, string $email): void
    {
        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new EmailAlreadyUsed($email);
            }
            throw $e;
        }
    }
}