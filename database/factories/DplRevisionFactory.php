<?php

namespace Database\Factories;

use App\Models\DplProposal;
use App\Models\DplRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplRevision>
 */
class DplRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dpl_proposal_id' => DplProposal::factory(),
            'revision_number' => 1,
            'reason' => null,
            'snapshot' => ['items' => []],
            'created_by_user_id' => User::factory(),
        ];
    }
}
