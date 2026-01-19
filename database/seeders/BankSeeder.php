<?php

namespace Database\Seeders;

use App\Models\Bank;
use Illuminate\Database\Seeder;

class BankSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            [
                'name' => 'XP Investimentos',
                'nickname' => 'XP',
                'cnpj' => '02.332.886/0001-04',
                'status' => true,
            ],
            [
                'name' => 'Clear Corretora',
                'nickname' => 'Clear',
                'cnpj' => '02.332.886/0011-78',
                'status' => true,
            ],
            [
                'name' => 'Rico Investimentos',
                'nickname' => 'Rico',
                'cnpj' => '02.332.886/0012-59',
                'status' => true,
            ],
            [
                'name' => 'BTG Pactual',
                'nickname' => 'BTG',
                'cnpj' => '30.306.294/0001-45',
                'status' => true,
            ],
            [
                'name' => 'Nu Invest',
                'nickname' => 'Nubank',
                'cnpj' => '62.169.875/0001-79',
                'status' => true,
            ],
            [
                'name' => 'Inter Invest',
                'nickname' => 'Inter',
                'cnpj' => '18.945.670/0001-46',
                'status' => true,
            ],
            [
                'name' => 'Itau Corretora',
                'nickname' => 'Itau',
                'cnpj' => '61.194.353/0001-64',
                'status' => true,
            ],
            [
                'name' => 'Bradesco Corretora',
                'nickname' => 'Bradesco',
                'cnpj' => '61.855.045/0001-32',
                'status' => true,
            ],
            [
                'name' => 'Genial Investimentos',
                'nickname' => 'Genial',
                'cnpj' => '27.652.684/0001-62',
                'status' => true,
            ],
            [
                'name' => 'Avenue Securities',
                'nickname' => 'Avenue',
                'cnpj' => null,
                'status' => true,
            ],
            [
                'name' => 'Outra Corretora',
                'nickname' => 'Outra',
                'cnpj' => null,
                'status' => true,
            ],
        ];

        foreach ($banks as $bank) {
            Bank::updateOrCreate(
                ['name' => $bank['name']],
                array_merge($bank, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
