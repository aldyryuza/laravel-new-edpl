<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\DplProposal;
use App\Models\ExitLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExitLetter>
 */
class ExitLetterFactory extends Factory
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
            'distributor_id' => Distributor::factory(),
            'letter_number' => null,
            'letter_date' => null,
            'branch_head_name_snapshot' => fake()->name(),
            'branch_head_position_snapshot' => 'Kepala Cabang',
            'status' => 'DRAFT',
            'created_by_user_id' => User::factory(),
        ];
    }
}
