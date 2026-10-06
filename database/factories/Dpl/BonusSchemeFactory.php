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
            'name' => '5+1',
            'bonus' => '0.0000',
            'discount_invoice' => '0.0000',
            'discount_bonus' => '0.0000',
            'discount_rounded' => '0.0000',
            'total_discount' => '0.0000',
            'is_active' => true,
        ];
    }
}
