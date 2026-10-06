<?php

namespace Database\Factories;

use App\Models\OwnershipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnershipType>
 */
class OwnershipTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('OWN-###??'),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }
}
