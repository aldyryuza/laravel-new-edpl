<?php

namespace Database\Factories;

use App\Models\SalesZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesZone>
 */
class SalesZoneFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('Z-###??'),
            'name' => 'Zona '.fake()->city(),
            'is_active' => true,
        ];
    }
}
