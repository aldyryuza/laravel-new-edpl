<?php

namespace Database\Factories;

use App\Models\CommercialParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommercialParameter>
 */
class CommercialParameterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PARAM_###??'),
            'value' => ['amount' => fake()->numberBetween(1, 100)],
            'description' => null,
            'is_active' => true,
        ];
    }
}
