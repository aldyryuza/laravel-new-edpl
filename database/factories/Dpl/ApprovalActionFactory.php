<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalAction;
use App\Models\Dpl\ApprovalStepInstance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalAction>
 */
class ApprovalActionFactory extends Factory
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
            'dpl_item_id' => null,
            'actor_user_id' => User::factory(),
            'action' => ApprovalAction::APPROVE,
            'reason' => null,
            'metadata' => null,
        ];
    }
}
