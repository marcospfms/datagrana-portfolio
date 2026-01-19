<?php

namespace Database\Factories;

use App\Models\Coin;
use Illuminate\Database\Eloquent\Factories\Factory;

class CoinFactory extends Factory
{
    protected $model = Coin::class;

    public function definition(): array
    {
        $codes = ['BRL', 'USD', 'EUR'];
        $code = $this->faker->randomElement($codes);

        return [
            'name' => $this->faker->unique()->currencyCode() . ' Coin',
            'short_name' => $this->faker->word(),
            'currency_symbol' => $this->faker->currencyCode(),
            'currency_code' => $code,
        ];
    }
}
