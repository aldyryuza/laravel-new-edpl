<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\DistributorMarginRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributorMarginRule>
 */
class DistributorMarginRuleFactory extends Factory
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
            'product_id' => Product::factory(),
            'city_id' => null,
            'margin' => '7.0000',
            'note' => null,
            'is_active' => true,
        ];
    }
}
