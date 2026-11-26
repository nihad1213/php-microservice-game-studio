<?php

declare(strict_types=1);

namespace App\Domain;

interface SalesReport
{
    /** @return array<int, array{game_id: int, currency: string, sales_count: int, units: int, revenue_cents: int}> */
    public function byGame(SaleFilter $filter): array;

    /** @return array<int, array{region: string, currency: string, sales_count: int, units: int, revenue_cents: int}> */
    public function byRegion(SaleFilter $filter): array;

    /** @return array<int, array{month: string, currency: string, sales_count: int, units: int, revenue_cents: int}> */
    public function monthly(SaleFilter $filter): array;
}