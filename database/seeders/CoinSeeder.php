<?php

namespace Database\Seeders;

use App\Models\Coin;
use Illuminate\Database\Seeder;

class CoinSeeder extends Seeder
{
    public function run(): void
    {
        $coins = [
            [
                'name' => 'Real',
                'short_name' => 'Real',
                'currency_symbol' => 'R$',
                'currency_code' => 'BRL',
            ],
            [
                'name' => 'Dolar',
                'short_name' => 'Dolar',
                'currency_symbol' => '$',
                'currency_code' => 'USD',
            ],
            [
                'name' => 'Euro',
                'short_name' => 'Euro',
                'currency_symbol' => 'EUR',
                'currency_code' => 'EUR',
            ],
        ];

        foreach ($coins as $coin) {
            Coin::updateOrCreate(
                ['currency_code' => $coin['currency_code']],
                $coin
            );
        }
    }
}
