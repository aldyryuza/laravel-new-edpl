<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\CommercialParameter;
use App\Models\Dpl\DplItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\Team;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Kalkulator DPL: Total Diskon = Principle ON + OFF + Bonus + Logistic Fee + Margin + Logistic Support
 * − diskon distributor bila Support Satoria; batas per team dari parameter MAX_DPL_DISCOUNT_TEAM_{kode}.
 */
class DplCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BonusScheme $bonus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->userWithAccess(['dpl-proposals' => ['read', 'create']]);
        $this->bonus = BonusScheme::factory()->create(['total_discount' => '3.25']);
        CommercialParameter::factory()->create(['code' => 'MAX_DPL_DISCOUNT_TEAM_A', 'value' => CommercialParameter::wrapValue('number', '30')]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(array $overrides = []): array
    {
        return [
            'principle_discount_on' => '10',
            'principle_discount_off' => '2',
            'distributor_discount_on' => '5',
            'distributor_discount_off' => '1',
            'margin' => '8',
            'logistic_fee' => '1.5',
            'logistic_support' => '0.5',
            'bonus_scheme_id' => $this->bonus->id,
            'is_support_satoria' => false,
            'is_direct_delivery' => true,
            'is_factory_pickup' => false,
            ...$overrides,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function calculate(array $items, string $team = 'A'): TestResponse
    {
        return $this->actingAs($this->user)->postJson('/api/dpl/proposals/calculate', ['team_code' => $team, 'items' => $items])->assertOk();
    }

    public function test_total_discount_formula_and_support_pickup_rules(): void
    {
        $this->calculate([
            // 10 + 2 + 3,25 + 1,5 + 8 + 0,5 = 25,25 (diskon distributor tidak berpengaruh tanpa Support)
            $this->item(),
            // Support: − (5 + 1) = 19,25
            $this->item(['is_support_satoria' => true]),
            // Ambil Dari Pabrik: logistic fee 0 -> 23,75
            $this->item(['is_direct_delivery' => false, 'is_factory_pickup' => true]),
            // Via distributor (bukan Direct): logistic fee tidak dihitung -> 23,75
            $this->item(['is_direct_delivery' => false]),
            // Tanpa bonus & kosong dianggap 0 -> 10
            ['principle_discount_on' => '10'],
        ])
            ->assertJsonPath('data.0.total_discount', '25.25')
            ->assertJsonPath('data.1.total_discount', '19.25')
            ->assertJsonPath('data.2.total_discount', '23.75')
            ->assertJsonPath('data.3.total_discount', '23.75')
            ->assertJsonPath('data.4.total_discount', '10.00');
    }

    public function test_total_is_rounded_half_up_to_two_decimals(): void
    {
        $this->calculate([['margin' => '10.005'], ['margin' => '10.0049'], ['margin' => '0.125']])
            ->assertJsonPath('data.0.total_discount', '10.01')
            ->assertJsonPath('data.1.total_discount', '10.00')
            ->assertJsonPath('data.2.total_discount', '0.13');
    }

    public function test_limit_comes_from_team_parameter(): void
    {
        $this->calculate([$this->item(), $this->item(['margin' => '20'])])
            ->assertJsonPath('data.0.limit', '30')
            ->assertJsonPath('data.0.exceeds_limit', false)
            ->assertJsonPath('data.1.total_discount', '37.25')
            ->assertJsonPath('data.1.exceeds_limit', true);

        // Team tanpa parameter: batas belum diatur, tidak pernah melewati batas.
        $this->calculate([$this->item(['margin' => '90'])], 'Z')
            ->assertJsonPath('data.0.limit', null)
            ->assertJsonPath('data.0.exceeds_limit', false);
    }

    public function test_saved_draft_stores_calculator_percentage(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $region = SalesRegion::factory()->for(SalesZone::factory()->create(['code' => 'E']), 'zone')->create();
        $outlet = Outlet::factory()->create(['area_id' => SalesArea::factory()->for($region, 'region')->create()->id]);
        $distributor = Distributor::factory()->create();
        $distributor->regions()->attach($region);
        UserOrgAssignment::query()->where('user_id', $this->user->id)->update(['team_id' => Team::factory()->create(['code' => 'A'])->id]);

        $this->actingAs($this->user)->postJson('/api/dpl/proposals', [
            'outlet_id' => $outlet->id,
            'proposal_type' => 'NORMAL',
            'date_start' => '2026-10-15',
            'date_end' => '2026-12-31',
            'is_contract' => false,
            'items' => [[
                ...$this->item(['is_support_satoria' => true]),
                'product_id' => Product::factory()->create(['volume' => 500])->id,
                'distributor_id' => $distributor->id,
                'minimum_order' => 200,
            ]],
        ])->assertCreated()->assertJsonPath('data.items.0.terms.calculator_percentage', '19.2500');

        $this->assertSame('19.2500', DplItem::query()->sole()->terms->calculator_percentage);
    }
}
