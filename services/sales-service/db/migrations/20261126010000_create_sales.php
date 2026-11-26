<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSales extends AbstractMigration
{
    public function change(): void
    {
        $this->table('sales')
            ->addColumn('game_id', 'integer')
            ->addColumn('sold_at', 'datetime')
            ->addColumn('units', 'integer')
            ->addColumn('unit_price_cents', 'integer')
            ->addColumn('currency', 'string', ['limit' => 3, 'default' => 'USD'])
            ->addColumn('region', 'string', ['limit' => 20])
            ->addColumn('platform', 'string', ['limit' => 20])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['game_id', 'sold_at'])
            ->addIndex(['sold_at'])
            ->addIndex(['region'])
            ->create();
    }
}