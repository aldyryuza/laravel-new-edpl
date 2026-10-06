<?php

namespace Database\Factories;

use App\Models\SalesRegion;
use App\Models\SalesZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesRegion>
 */
class SalesRegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('R-###??'),
            'name' => 'Region '.fake()->city(),
            'zone_id' => SalesZone::factory(),
            'is_active' => true,
        ];
    }
}
