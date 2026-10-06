<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\Attachment;
use App\Models\Dpl\DplProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity_type' => DplProposal::class,
            'entity_id' => DplProposal::factory(),
            'file_name' => fake()->word().'.pdf',
            'storage_path' => 'attachments/'.fake()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => fake()->numberBetween(1000, 500000),
            'uploaded_by_user_id' => User::factory(),
        ];
    }
}
