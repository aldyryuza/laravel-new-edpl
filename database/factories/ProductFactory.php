<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('P-#####'),
            'name' => fake()->words(3, true),
            'volume' => fake()->randomElement(['500.0000', '250.0000', '100.0000', '25.0000']),
            'is_branded' => false,
            'is_active' => true,
            'netsuite_id' => null,
        ];
    }
}
