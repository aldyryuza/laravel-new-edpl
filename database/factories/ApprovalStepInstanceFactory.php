<?php

namespace Database\Factories;

use App\Models\ApprovalInstance;
use App\Models\ApprovalStepInstance;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
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
            'assigned_user_id' => User::factory(),
            'status' => 'PENDING',
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
