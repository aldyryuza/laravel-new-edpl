<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\OutletPriceRegion;
use App\Models\Dpl\PriceRegion;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletPriceRegion>
 */
class OutletPriceRegionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outlet_id' => Outlet::factory(),
            'price_region_id' => PriceRegion::factory(),
        ];
    }
}
