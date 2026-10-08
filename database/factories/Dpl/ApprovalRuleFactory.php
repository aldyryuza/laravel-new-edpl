<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRule>
 */
class ApprovalRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Aturan '.fake()->unique()->word(),
            'discount_lower' => null,
            'discount_upper' => null,
            'sort_order' => 0,
            'note' => null,
            'is_active' => true,
        ];
    }
}
