<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\CommercialParameter;
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
            'value' => CommercialParameter::wrapValue(CommercialParameter::TYPE_NUMBER, (string) fake()->numberBetween(1, 100)),
            'description' => null,
            'is_active' => true,
        ];
    }
}
