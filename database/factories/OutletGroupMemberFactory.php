<?php

namespace Database\Factories;

use App\Models\Outlet;
use App\Models\OutletGroup;
use App\Models\OutletGroupMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutletGroupMember>
 */
class OutletGroupMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'outlet_group_id' => OutletGroup::factory(),
            'outlet_id' => Outlet::factory(),
            'valid_from' => null,
            'valid_until' => null,
        ];
    }
}
