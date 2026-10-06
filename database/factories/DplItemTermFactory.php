<?php

namespace Database\Factories;

use App\Models\DplItem;
use App\Models\DplItemTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplItemTerm>
 */
class DplItemTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dpl_item_id' => DplItem::factory(),
            'principle_discount_on' => '10.0000',
            'principle_discount_off' => '0.0000',
            'distributor_discount_on' => '0.0000',
            'distributor_discount_off' => '0.0000',
            'negotiated_price' => null,
            'margin' => null,
            'logistic_fee' => null,
            'logistic_support' => null,
            'base_price' => null,
            'final_price' => null,
            'effective_discount' => null,
            'calculator_percentage' => null,
            'price_type' => null,
        ];
    }
}
