<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'company_category_id' => CompanyCategory::factory(),
            'name' => $this->faker->unique()->company(),
            'status' => true,
            'cnpj' => $this->faker->numerify('##.###.###/####-##'),
            'nickname' => $this->faker->word(),
            'photo' => $this->faker->imageUrl(100, 100),
            'segment' => $this->faker->word(),
            'sector' => $this->faker->word(),
            'subsector' => $this->faker->word(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
