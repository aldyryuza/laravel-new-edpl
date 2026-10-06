<?php

namespace Database\Factories;

use App\Models\OutletGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletGroup>
 */
class OutletGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('GRP-###??'),
            'name' => 'Group '.fake()->company(),
            'description' => null,
            'is_active' => true,
        ];
    }
}
