<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserVisibilityEdge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserVisibilityEdge>
 */
class UserVisibilityEdgeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'viewer_user_id' => User::factory(),
            'owner_user_id' => User::factory(),
            'relation_type' => null,
            'valid_from' => null,
            'valid_until' => null,
        ];
    }
}
