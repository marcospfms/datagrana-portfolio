<?php

namespace Database\Seeders;

use App\Models\Coin;
use App\Models\CompanyCategory;
use Illuminate\Database\Seeder;

class CompanyCategorySeeder extends Seeder
{
    public function run(): void
    {
        $coin = Coin::firstWhere('currency_code', 'BRL');

        if (!$coin) {
            $coin = Coin::create([
                'name' => 'Real',
                'short_name' => 'Real',
                'currency_symbol' => 'R$',
                'currency_code' => 'BRL',
            ]);
        }

        $categories = [
            [
                'name' => 'Acoes',
                'short_name' => 'Acoes',
                'reference' => 'Acoes',
                'status' => true,
                'color_hex' => '#3B82F6',
                'icon' => 'chart-line',
            ],
            [
                'name' => 'Fundos Imobiliarios',
                'short_name' => 'FIIs',
                'reference' => 'FII',
                'status' => true,
                'color_hex' => '#10B981',
                'icon' => 'building',
            ],
            [
                'name' => 'ETFs',
                'short_name' => 'ETFs',
                'reference' => 'ETF',
                'status' => true,
                'color_hex' => '#8B5CF6',
                'icon' => 'layer-group',
            ],
            [
                'name' => 'BDRs',
                'short_name' => 'BDRs',
                'reference' => 'BDR',
                'status' => true,
                'color_hex' => '#F59E0B',
                'icon' => 'globe',
            ],
        ];

        foreach ($categories as $category) {
            CompanyCategory::updateOrCreate(
                ['reference' => $category['reference']],
                array_merge($category, [
                    'coin_id' => $coin->id,
                ])
            );
        }
    }
}
