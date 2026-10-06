<?php

namespace Database\Factories;

use App\Models\PriceRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceRegion>
 */
class PriceRegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PR-###??'),
            'name' => 'Region Harga '.fake()->city(),
            'description' => null,
            'is_active' => true,
        ];
    }
}
