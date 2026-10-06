<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApprovalWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalWorkflow>
 */
class ApprovalWorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('WF_###??'),
            'name' => fake()->words(3, true),
            'description' => null,
            'is_active' => true,
        ];
    }
}
