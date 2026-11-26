<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class SalesSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('sales')->truncate();
        mt_srand(42);

        $games = [
            1  => ['pc',          3999, '2025-03-14'],
            2  => ['playstation', 4999, '2025-06-01'],
            3  => ['pc',          2499, '2025-09-20'],
            4  => ['mobile',       499, '2024-11-02'],
            10 => ['mobile',       299, '2026-01-30'],
        ];
        $regions = ['north_america', 'europe', 'asia', 'south_america', 'oceania', 'africa'];

        $utc      = new DateTimeZone('UTC');
        $now      = new DateTimeImmutable('now', $utc);
        $earliest = $now->modify('-18 months')->getTimestamp();
        $ids      = array_keys($games);

        $rows = [];
        for ($i = 0; $i < 5000; $i++) {
            $gameId = $ids[mt_rand(0, count($ids) - 1)];
            [$platform, $price, $released] = $games[$gameId];

            $from = max($earliest, (new DateTimeImmutable($released, $utc))->getTimestamp());
            $euro = mt_rand(1, 100) <= 20;

            $rows[] = [
                'game_id'          => $gameId,
                'sold_at'          => gmdate('Y-m-d H:i:s', mt_rand($from, $now->getTimestamp())),
                'units'            => mt_rand(1, 100) <= 85 ? 1 : mt_rand(2, 5),
                'unit_price_cents' => (int) round($price * (mt_rand(50, 100) / 100) * ($euro ? 0.92 : 1)),
                'currency'         => $euro ? 'EUR' : 'USD',
                'region'           => $regions[mt_rand(0, count($regions) - 1)],
                'platform'         => $platform,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            $this->table('sales')->insert($chunk)->saveData();
        }
    }
}