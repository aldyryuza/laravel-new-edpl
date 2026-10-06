<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalOverrideRequest;
use App\Models\Dpl\DplProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalOverrideRequest>
 */
class ApprovalOverrideRequestFactory extends Factory
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
            'dpl_item_id' => null,
            'requested_by_user_id' => User::factory(),
            'request_reason' => fake()->sentence(),
            'status' => 'PENDING',
            'reviewed_by_user_id' => null,
            'review_reason' => null,
            'requested_at' => now(),
            'reviewed_at' => null,
        ];
    }
}
