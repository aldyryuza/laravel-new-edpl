<?php

namespace Database\Factories;

use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserOrgAssignment>
 */
class UserOrgAssignmentFactory extends Factory
{
    /**
     * Default: penugasan area.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'zone_id' => null,
            'region_id' => null,
            'area_id' => SalesArea::factory(),
            'team_id' => null,
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
        ];
    }

    /**
     * Nasional: melihat semua wilayah.
     */
    public function national(): static
    {
        return $this->state(fn () => ['zone_id' => null, 'region_id' => null, 'area_id' => null]);
    }

    public function forZone(SalesZone $zone): static
    {
        return $this->state(fn () => ['zone_id' => $zone->id, 'region_id' => null, 'area_id' => null]);
    }

    public function forRegion(SalesRegion $region): static
    {
        return $this->state(fn () => ['zone_id' => null, 'region_id' => $region->id, 'area_id' => null]);
    }

    public function forArea(SalesArea $area): static
    {
        return $this->state(fn () => ['zone_id' => null, 'region_id' => null, 'area_id' => $area->id]);
    }
}
