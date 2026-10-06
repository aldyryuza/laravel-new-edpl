<?php

namespace Database\Factories\Dpl;

use App\Models\Distributor;
use App\Models\Dpl\DistributorPriceRegion;
use App\Models\Dpl\PriceRegion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorPriceRegion>
 */
class DistributorPriceRegionFactory extends Factory
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
            'price_region_id' => PriceRegion::factory(),
        ];
    }
}
