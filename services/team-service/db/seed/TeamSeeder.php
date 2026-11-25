<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class TeamSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->execute('SET FOREIGN_KEY_CHECKS = 0');
        $this->table('team_members')->truncate();
        $this->table('teams')->truncate();
        $this->execute('SET FOREIGN_KEY_CHECKS = 1');

        $this->table('teams')->insert([
            ['name' => 'Nova Studio',      'description' => 'RPG and adventure games',    'lead_developer_id' => null],
            ['name' => 'Apex Racing Lab',  'description' => 'Racing and sports games',    'lead_developer_id' => null],
            ['name' => 'Pocket Games',     'description' => 'Mobile and casual games',    'lead_developer_id' => null],
        ])->saveData();
    }
}