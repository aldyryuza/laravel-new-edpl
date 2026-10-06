<?php

namespace Database\Factories;

use App\Models\ApprovalItemState;
use App\Models\ApprovalStepInstance;
use App\Models\DplItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalItemState>
 */
class ApprovalItemStateFactory extends Factory
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
            'dpl_item_id' => DplItem::factory(),
            'status' => 'PENDING',
            'approved_at' => null,
            'rejected_at' => null,
            'reject_reason' => null,
        ];
    }
}
