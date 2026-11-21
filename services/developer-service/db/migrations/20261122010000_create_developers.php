<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateDevelopers extends AbstractMigration
{
    public function change(): void
    {
        $this->table('developers')
            ->addColumn('first_name', 'string', ['limit' => 100])
            ->addColumn('last_name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('role', 'string', ['limit' => 30])
            ->addColumn('hired_at', 'date')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['role'])
            ->create();
    }
}