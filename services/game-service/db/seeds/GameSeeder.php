<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class GameSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('games')->truncate();

        $rows = [
            ['Star Forge',        'rpg',        'pc',          3999, 'released',       1, '2025-03-14'],
            ['Star Forge',        'rpg',        'playstation', 4999, 'released',       1, '2025-06-01'],
            ['Neon Drift',        'racing',     'pc',          2499, 'released',       2, '2025-09-20'],
            ['Pixel Farm',        'simulation', 'mobile',       499, 'released',       3, '2024-11-02'],
            ['Dungeon Bytes',     'roguelike',  'switch',      1999, 'retired',        2, '2022-05-10'],
            ['Skyward',           'adventure',  'xbox',        5999, 'in_development', 1, null],
            ['Quantum Chess',     'puzzle',     'pc',           999, 'in_development', 3, null],
            ['Iron Tide',         'strategy',   'pc',          3499, 'in_development', 2, null],
            ['Echoes of Mars',    'shooter',    'playstation', 5999, 'in_development', 1, null],
            ['Cozy Cafe Tycoon',  'simulation', 'mobile',       299, 'released',       3, '2026-01-30'],
        ];

        $data = array_map(fn (array $r) => [
            'title' => $r[0], 'genre' => $r[1], 'platform' => $r[2], 'price_cents' => $r[3],
            'status' => $r[4], 'team_id' => $r[5], 'release_date' => $r[6],
        ], $rows);

        $this->table('games')->insert($data)->saveData();
    }
}