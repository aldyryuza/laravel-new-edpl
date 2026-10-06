<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalStepInstance;
use App\Models\Dpl\ApprovalWorkflowStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalStepInstance>
 */
class ApprovalStepInstanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'approval_instance_id' => ApprovalInstance::factory(),
            'workflow_step_id' => ApprovalWorkflowStep::factory(),
            'status' => 'PENDING',
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
