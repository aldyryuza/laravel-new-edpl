<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\OutletSalesAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletSalesAssignment>
 */
class OutletSalesAssignmentFactory extends Factory
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
            'am_user_id' => User::factory(),
            'spv_user_id' => null,
        ];
    }
}
