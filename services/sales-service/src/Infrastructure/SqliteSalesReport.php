<?php

declare(strict_types=1);

namespace App\Infrastructure;

use App\Domain\SaleFilter;
use App\Domain\SalesReport;
use PDO;

final class SqliteSalesReport implements SalesReport
{
    public function __construct(private PDO $pdo) {}

    public function byGame(SaleFilter $filter): array
    {
        return $this->run('game_id', 'game_id, currency', 'revenue_cents DESC, game_id', $filter);
    }

    public function byRegion(SaleFilter $filter): array
    {
        return $this->run('region', 'region, currency', 'revenue_cents DESC, region', $filter);
    }

    public function monthly(SaleFilter $filter): array
    {
        return $this->run("strftime('%Y-%m', sold_at) AS month", 'month, currency', 'month, currency', $filter);
    }

    /**
     * Money in different currencies is never added together: every report is grouped by currency too.
     */
    private function run(string $select, string $groupBy, string $orderBy, SaleFilter $filter): array
    {
        [$where, $params] = SaleQuery::where($filter);

        $stmt = $this->pdo->prepare(
            "SELECT {$select}, currency,
                    COUNT(*) AS sales_count,
                    SUM(units) AS units,
                    SUM(units * unit_price_cents) AS revenue_cents
             FROM sales{$where}
             GROUP BY {$groupBy}
             ORDER BY {$orderBy}"
        );
        SaleQuery::bind($stmt, $params);
        $stmt->execute();

        return array_map(function (array $row): array {
            foreach (['game_id', 'sales_count', 'units', 'revenue_cents'] as $int) {
                if (isset($row[$int])) {
                    $row[$int] = (int) $row[$int];
                }
            }

            return $row;
        }, $stmt->fetchAll());
    }
}