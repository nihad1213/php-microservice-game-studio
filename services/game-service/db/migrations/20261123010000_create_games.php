<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateGames extends AbstractMigration
{
    public function change(): void
    {
        $this->table('games')
            ->addColumn('title', 'string', ['limit' => 200])
            ->addColumn('genre', 'string', ['limit' => 50])
            ->addColumn('platform', 'string', ['limit' => 30])
            ->addColumn('price_cents', 'integer', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'in_development'])
            ->addColumn('team_id', 'integer', ['signed' => false])
            ->addColumn('release_date', 'date', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['title', 'platform'], ['unique' => true])
            ->addIndex(['status'])
            ->addIndex(['team_id'])
            ->addIndex(['genre'])
            ->create();
    }
}