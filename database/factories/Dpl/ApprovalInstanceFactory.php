<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalWorkflow;
use App\Models\Dpl\DplProposal;
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
