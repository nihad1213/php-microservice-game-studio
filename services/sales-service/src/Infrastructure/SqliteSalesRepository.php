<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\Sale;
use App\Domain\SaleFilter;
use App\Domain\SaleRepository;
use PDO;
use Throwable;

final class SqliteSalesRepository implements SaleRepository
{
    private const INSERT = 'INSERT INTO sales (game_id, sold_at, units, unit_price_cents, currency, region, platform)
                            VALUES (:game_id, :sold_at, :units, :unit_price_cents, :currency, :region, :platform)';

    public function __construct(private PDO $pdo) {}

    public function find(int $id): ?Sale
    {
        $stmt = $this->pdo->prepare('SELECT * FROM sales WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? Sale::fromRow($row) : null;
    }

    public function findAll(int $limit, int $offset, SaleFilter $filter): array
    {
        [$where, $params] = SaleQuery::where($filter);

        $stmt = $this->pdo->prepare(
            "SELECT * FROM sales{$where} ORDER BY sold_at DESC, id DESC LIMIT :limit OFFSET :offset"
        );
        SaleQuery::bind($stmt, $params);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(Sale::fromRow(...), $stmt->fetchAll());
    }

    public function count(SaleFilter $filter): int
    {
        [$where, $params] = SaleQuery::where($filter);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM sales{$where}");
        SaleQuery::bind($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    public function insert(Sale $sale): Sale
    {
        $this->pdo->prepare(self::INSERT)->execute($this->params($sale));

        return $this->find((int) $this->pdo->lastInsertId());
    }

    public function insertMany(array $sales): int
    {
        return $this->transaction(function () use ($sales): int {
            $stmt = $this->pdo->prepare(self::INSERT);      // prepared once, executed many times

            foreach ($sales as $sale) {
                $stmt->execute($this->params($sale));
            }

            return count($sales);
        });
    }

    private function params(Sale $s): array
    {
        return [
            'game_id'          => $s->gameId,
            'sold_at'          => SaleQuery::format($s->soldAt),
            'units'            => $s->units,
            'unit_price_cents' => $s->unitPriceCents,
            'currency'         => $s->currency->value,
            'region'           => $s->region->value,
            'platform'         => $s->platform->value,
        ];
    }

    /** BEGIN IMMEDIATE takes the write lock at once, so a batch fails fast instead of deadlocking later. */
    private function transaction(callable $work): mixed
    {
        $this->pdo->exec('BEGIN IMMEDIATE');

        try {
            $result = $work();
            $this->pdo->exec('COMMIT');

            return $result;
        } catch (Throwable $e) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (Throwable) {
                // nothing left to roll back
            }
            throw $e;
        }
    }
}