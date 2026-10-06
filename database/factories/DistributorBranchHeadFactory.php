<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\DistributorBranchHead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorBranchHead>
 */
class DistributorBranchHeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'distributor_id' => Distributor::factory(),
            'name' => fake()->name(),
            'position' => 'Kepala Cabang',
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }
}
