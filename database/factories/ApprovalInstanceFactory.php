<?php

namespace Database\Factories;

use App\Models\ApprovalInstance;
use App\Models\ApprovalWorkflow;
use App\Models\DplProposal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalInstance>
 */
class ApprovalInstanceFactory extends Factory
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
            'workflow_id' => ApprovalWorkflow::factory(),
            'status' => 'PENDING',
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
