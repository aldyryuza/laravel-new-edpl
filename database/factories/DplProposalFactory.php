<?php

namespace Database\Factories;

use App\Models\DplProposal;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplProposal>
 */
class DplProposalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfDay();

        return [
            'dpl_number' => fake()->unique()->bothify('DPL-######'),
            'outlet_id' => Outlet::factory(),
            'team_code' => 'A',
            'created_by_user_id' => User::factory(),
            'date_offer' => $start,
            'date_start' => $start,
            'date_end' => $start->copy()->addYear()->subDay(),
            'is_contract' => false,
            'bed_count_snapshot' => null,
            'proposal_type' => DplProposal::TYPE_NORMAL,
            'proposal_status' => DplProposal::STATUS_DRAFT,
            'program_status' => DplProposal::PROGRAM_INACTIVE,
            'description' => null,
            'submitted_at' => null,
            'approved_at' => null,
        ];
    }

    /**
     * DPL yang sudah approved dan program-nya aktif.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'proposal_status' => DplProposal::STATUS_APPROVED,
            'program_status' => DplProposal::PROGRAM_ACTIVE,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
    }

    public function team(string $teamCode): static
    {
        return $this->state(fn () => ['team_code' => $teamCode]);
    }
}
