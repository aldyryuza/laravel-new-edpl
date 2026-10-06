<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalWorkflow;
use App\Models\Dpl\ApprovalWorkflowStep;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalWorkflowStep>
 */
class ApprovalWorkflowStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => ApprovalWorkflow::factory(),
            'sequence' => 1,
            'role_id' => Role::factory(),
            'can_edit' => false,
            'can_reject' => true,
            'is_required' => true,
        ];
    }
}
