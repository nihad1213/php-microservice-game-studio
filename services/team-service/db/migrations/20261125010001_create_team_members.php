<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTeamMembers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('team_members', ['id' => false, 'primary_key' => ['team_id', 'developer_id']])
            ->addColumn('team_id', 'integer', ['signed' => false])
            ->addColumn('developer_id', 'integer', ['signed' => false])
            ->addColumn('joined_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['developer_id'])
            ->addForeignKey('team_id', 'teams', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
            ->create();
    }
}