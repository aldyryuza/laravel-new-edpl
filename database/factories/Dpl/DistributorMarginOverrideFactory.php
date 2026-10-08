<?php

namespace Database\Factories\Dpl;

use App\Models\Distributor;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorMarginOverride>
 */
class DistributorMarginOverrideFactory extends Factory
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
            'outlet_id' => Outlet::factory(),
            'margin' => '6.0000',
            'note' => null,
            'is_active' => true,
        ];
    }
}
