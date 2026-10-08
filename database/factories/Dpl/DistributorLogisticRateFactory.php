<?php

namespace Database\Factories\Dpl;

use App\Models\City;
use App\Models\Dpl\DistributorLogisticRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorLogisticRate>
 */
class DistributorLogisticRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'distributor_group_code' => fake()->unique()->bothify('GRP##??'),
            'city_id' => City::factory(),
            'logistic_fee' => '4.0000',
            'note' => null,
            'is_active' => true,
        ];
    }
}
