<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTeams extends AbstractMigration
{
    public function change(): void
    {
        $this->table('teams', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('description', 'text', ['null' => true])
            ->addColumn('lead_developer_id', 'integer', ['signed' => false, 'null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['name'], ['unique' => true])
            ->create();
    }
}