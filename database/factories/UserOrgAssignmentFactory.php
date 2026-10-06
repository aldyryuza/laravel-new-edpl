<?php

namespace Database\Factories;

use App\Models\SalesArea;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserOrgAssignment>
 */
class UserOrgAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'area_id' => SalesArea::factory(),
            'team_code' => 'A',
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }
}
