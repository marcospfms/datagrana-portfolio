<?php

namespace Database\Seeders;

use App\Models\EarningType;
use Illuminate\Database\Seeder;

class EarningTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Dividendos',
                'short_name' => 'Divid.',
                'label' => 'Dividendos',
                'key' => 'DIV',
                'icon' => 'wallet',
                'hex_color' => '#16A34A',
            ],
            [
                'name' => 'Juros sobre Capital Proprio',
                'short_name' => 'JCP',
                'label' => 'JCP',
                'key' => 'JCP',
                'icon' => 'coins',
                'hex_color' => '#2563EB',
            ],
            [
                'name' => 'Rendimentos',
                'short_name' => 'Rend.',
                'label' => 'Rendimentos',
                'key' => 'REND',
                'icon' => 'trending-up',
                'hex_color' => '#9333EA',
            ],
            [
                'name' => 'Bonificacao',
                'short_name' => 'Bonif.',
                'label' => 'Bonificacao',
                'key' => 'BON',
                'icon' => 'gift',
                'hex_color' => '#F59E0B',
            ],
            [
                'name' => 'Outros',
                'short_name' => 'Outros',
                'label' => 'Outros',
                'key' => 'OUT',
                'icon' => 'layers',
                'hex_color' => '#6B7280',
            ],
        ];

        foreach ($types as $type) {
            EarningType::updateOrCreate(
                ['key' => $type['key']],
                $type
            );
        }
    }
}
