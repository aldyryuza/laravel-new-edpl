<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\DplConsolidation;
use App\Models\Dpl\DplProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplConsolidation>
 */
class DplConsolidationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dpl_proposal_id' => DplProposal::factory()->state(['proposal_type' => DplProposal::TYPE_CONSOLIDATION]),
            'code' => fake()->unique()->bothify('CONS-#####'),
            'created_by_user_id' => User::factory(),
            'status' => DplConsolidation::STATUS_OPEN,
            'mode' => DplConsolidation::MODE_MANUAL,
        ];
    }
}
