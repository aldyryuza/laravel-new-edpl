<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\BonusScheme;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BonusScheme>
 */
class BonusSchemeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('B-###??'),
            'name' => 'Beli 10 Bonus 1',
            'buy_quantity' => '10.0000',
            'bonus_quantity' => '1.0000',
            'discount_invoice' => null,
            'discount_bonus' => null,
            'effective_discount' => null,
            'is_active' => true,
        ];
    }
}
