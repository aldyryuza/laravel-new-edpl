<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplRelation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplRelation>
 */
class DplRelationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_dpl_id' => DplProposal::factory(),
            'target_dpl_id' => DplProposal::factory(),
            'relation_type' => DplRelation::TYPE_RENEWAL,
            'created_by_user_id' => User::factory(),
        ];
    }
}
