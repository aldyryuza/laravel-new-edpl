<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ProductMarginOverride;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductMarginOverride>
 */
class ProductMarginOverrideFactory extends Factory
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
            'distributor_group_code' => null,
            'team_code' => null,
            'margin' => '10.0000',
            'note' => null,
            'is_active' => true,
        ];
    }
}
