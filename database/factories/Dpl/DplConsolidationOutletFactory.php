<?php

namespace Database\Factories\Dpl;

use App\Models\Dpl\DplConsolidation;
use App\Models\Dpl\DplConsolidationOutlet;
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
