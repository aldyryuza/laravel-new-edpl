<?php

namespace Database\Factories;

use App\Models\SalesArea;
use App\Models\User;
use App\Models\UserAreaScope;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAreaScope>
 */
class UserAreaScopeFactory extends Factory
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
            'valid_from' => null,
            'valid_until' => null,
        ];
    }
}
