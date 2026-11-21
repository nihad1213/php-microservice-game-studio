<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class DeveloperSeeder extends AbstractSeed
{
    public function run(): void
    {
        $faker = Faker\Factory::create();
        $roles = ['programmer', 'designer', 'artist', 'producer', 'qa'];

        $rows = [];
        for ($i = 0; $i < 30; $i++) {
            $rows[] = [
                'first_name' => $faker->firstName(),
                'last_name'  => $faker->lastName(),
                'email'      => $faker->unique()->safeEmail(),
                'role'       => $faker->randomElement($roles),
                'hired_at'   => $faker->dateTimeBetween('-8 years', 'now')->format('Y-m-d'),
            ];
        }

        $this->table('developers')->insert($rows)->saveData();
    }
}