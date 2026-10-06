<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalStepAssignee;
use App\Models\Dpl\ApprovalStepInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalStepAssignee>
 */
class ApprovalStepAssigneeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'step_instance_id' => ApprovalStepInstance::factory(),
            'user_id' => User::factory(),
        ];
    }
}
