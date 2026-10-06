<?php

namespace Database\Factories;

use App\Models\PriceRegion;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_region_id' => PriceRegion::factory(),
            'price' => fake()->randomFloat(4, 1000, 100000),
            'regular_price' => null,
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }
}
