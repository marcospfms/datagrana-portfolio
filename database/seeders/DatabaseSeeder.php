<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BankSeeder::class,
            CoinSeeder::class,
            CompanyCategorySeeder::class,
            SubscriptionPlanSeeder::class,
        ]);
    }
}
