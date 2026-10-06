<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\ApproverAssignment;
use App\Models\Role;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApproverAssignment>
 */
class ApproverAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => Role::factory(),
            'region_id' => null,
            'zone_id' => null,
            'user_id' => User::factory(),
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }

    /**
     * Assignment RM: scope region.
     */
    public function forRegion(SalesRegion $region): static
    {
        return $this->state(fn () => ['region_id' => $region->id, 'zone_id' => null]);
    }

    /**
     * Assignment NSM: scope zone.
     */
    public function forZone(SalesZone $zone): static
    {
        return $this->state(fn () => ['region_id' => null, 'zone_id' => $zone->id]);
    }
}
