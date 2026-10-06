<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ProductPriceOverride;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceOverride>
 */
class ProductPriceOverrideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_type' => 'JKN',
            'outlet_group_id' => null,
            'product_id' => Product::factory(),
            'price_region_id' => null,
            'price' => fake()->randomFloat(4, 1000, 100000),
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
            'note' => null,
        ];
    }
}
