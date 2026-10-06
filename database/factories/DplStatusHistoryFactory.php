<?php

namespace Database\Factories;

use App\Models\DplProposal;
use App\Models\DplStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplStatusHistory>
 */
class DplStatusHistoryFactory extends Factory
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
            'status_type' => DplStatusHistory::TYPE_PROPOSAL,
            'old_status' => DplProposal::STATUS_DRAFT,
            'new_status' => DplProposal::STATUS_SUBMITTED,
            'reason' => null,
            'changed_by_user_id' => User::factory(),
        ];
    }
}
