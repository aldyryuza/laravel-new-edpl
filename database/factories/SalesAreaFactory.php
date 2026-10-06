<?php

namespace Database\Factories;

use App\Models\SalesArea;
use App\Models\SalesRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesArea>
 */
class SalesAreaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('A-###??'),
            'name' => 'Area '.fake()->city(),
            'region_id' => SalesRegion::factory(),
            'is_active' => true,
        ];
    }
}
