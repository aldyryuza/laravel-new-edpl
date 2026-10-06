<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\DplItem;
use App\Models\DplProposal;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DplItem>
 */
class DplItemFactory extends Factory
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
            'product_id' => Product::factory(),
            'distributor_id' => Distributor::factory(),
            'minimum_order' => 200,
            'bonus_scheme_id' => null,
            'is_direct_delivery' => false,
            'is_buyer_reward' => false,
            'is_bonus_conversion' => false,
            'is_support_satoria' => false,
            'is_returnable' => false,
            'remark' => null,
            'netsuite_id' => null,
        ];
    }
}
