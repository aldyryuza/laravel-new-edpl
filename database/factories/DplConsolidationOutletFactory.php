<?php

namespace Database\Factories;

use App\Models\DplConsolidation;
use App\Models\DplConsolidationOutlet;
use App\Models\Outlet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplConsolidationOutlet>
 */
class DplConsolidationOutletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consolidation_id' => DplConsolidation::factory(),
            'outlet_id' => Outlet::factory(),
            'generated_dpl_id' => null,
        ];
    }
}
