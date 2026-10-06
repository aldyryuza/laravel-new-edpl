<?php

namespace Database\Factories;

use App\Models\DplConsolidation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplConsolidation>
 */
class DplConsolidationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CONS-#####'),
            'created_by_user_id' => User::factory(),
            'status' => 'DRAFT',
        ];
    }
}
