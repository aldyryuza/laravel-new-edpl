<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\SalesArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Outlet>
 */
class OutletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('OUT-#####'),
            'name' => 'RS '.fake()->company(),
            'address' => fake()->address(),
            'area_id' => SalesArea::factory(),
            'outlet_category_id' => null,
            'ownership_type_id' => null,
            'bed_count' => fake()->numberBetween(0, 500),
            'price_region_id' => null,
            'crm_id' => null,
            'latitude' => null,
            'longitude' => null,
            'is_active' => true,
        ];
    }
}
