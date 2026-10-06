<?php

namespace Database\Factories;

use App\Models\OutletCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletCategory>
 */
class OutletCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CAT-###??'),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
