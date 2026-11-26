<?php

declare(strict_types=1);

namespace App\Domain;

interface SaleRepository
{
    public function find(int $id): ?Sale;

    /** @return Sale[] */
    public function findAll(int $limit, int $offset, SaleFilter $filter): array;

    public function count(SaleFilter $filter): int;

    public function insert(Sale $sale): Sale;

    /**
     * Inserts all sales in one transaction: either every row is saved or none.
     * @param Sale[] $sales
     */
    public function insertMany(array $sales): int;
}