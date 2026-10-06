<?php

namespace Database\Factories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Distributor>
 */
class DistributorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('D-#####'),
            'name' => 'PT '.fake()->company(),
            'group_code' => null,
            'city_name' => fake()->city(),
            'is_branch' => false,
            'parent_distributor_id' => null,
            'is_active' => true,
            'netsuite_id' => null,
        ];
    }
}
