<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Dpl\CommercialParameter;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplStatusHistory;
use App\Models\Dpl\ProductPrice;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\Team;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft DPL: nomor {YYMM}-SAI-{zona}-{urut per zona per bulan}, team dari wilayah user, item + terms.
 */
class DplProposalDraftTest extends TestCase
{
    use RefreshDatabase;

    private SalesRegion $region;

    private Outlet $outlet;

    private Product $product;

    private Distributor $distributor;

    private Team $team;

    private User $proposer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));

        $this->region = SalesRegion::factory()->for(SalesZone::factory()->create(['code' => 'E']), 'zone')->create();
        $this->outlet = Outlet::factory()->create(['area_id' => SalesArea::factory()->for($this->region, 'region')->create()->id, 'bed_count' => 120]);
        $this->product = Product::factory()->create();
        $this->distributor = Distributor::factory()->create();
        $this->distributor->regions()->attach($this->region);
        $this->team = Team::factory()->create(['code' => 'A']);
        $this->proposer = $this->proposer($this->region, $this->team);
    }

    private function proposer(SalesRegion $region, ?Team $team): User
    {
        $user = $this->userWithAccess(['dpl-proposals' => ['read', 'create', 'update', 'delete']], national: false);
        UserOrgAssignment::factory()->for($user)->forRegion($region)->create(['team_id' => $team?->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'outlet_id' => $this->outlet->id,
            'proposal_type' => 'NORMAL',
            // Diabaikan server: tanggal penawaran selalu hari ini.
            'date_offer' => '2020-01-01',
            'date_start' => '2026-10-15',
            'date_end' => '2027-10-14',
            'is_contract' => false,
            'description' => null,
            'items' => [[
                'product_id' => $this->product->id,
                'distributor_id' => $this->distributor->id,
                'minimum_order' => 200,
                'principle_discount_on' => '10.5',
                // Harga nego & konversi bonus tidak lagi diinput: diabaikan.
                'negotiated_price' => '125000',
                'is_bonus_conversion' => true,
                'is_direct_delivery' => true,
                'logistic_fee' => '15000',
            ]],
            ...$overrides,
        ];
    }

    public function test_draft_is_created_with_number_team_snapshot_items_and_history(): void
    {
        $response = $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.dpl_number', '2610-SAI-E-0001')
            ->assertJsonPath('data.team_code', 'A')
            ->assertJsonPath('data.proposal_status', DplProposal::STATUS_DRAFT)
            ->assertJsonPath('data.program_status', DplProposal::PROGRAM_INACTIVE)
            ->assertJsonPath('data.bed_count_snapshot', 120)
            ->assertJsonPath('data.date_offer', '2026-10-15')
            ->assertJsonPath('data.date_start', '2026-10-15')
            ->assertJsonPath('data.can_edit', true);

        $item = DplItem::query()->sole();
        $this->assertTrue($item->is_direct_delivery);
        $this->assertSame(200, $item->minimum_order);
        $this->assertSame('10.5000', $item->terms->principle_discount_on);
        $this->assertSame('0.0000', $item->terms->principle_discount_off);
        $this->assertNull($item->terms->negotiated_price);
        $this->assertFalse($item->is_bonus_conversion);
        $this->assertSame('PRICE_BARU_2026', $item->terms->price_type);

        $this->assertDatabaseHas(DplStatusHistory::class, [
            'dpl_proposal_id' => $response->json('data.id'),
            'status_type' => 'PROPOSAL',
            'old_status' => null,
            'new_status' => 'DRAFT',
            'changed_by_user_id' => $this->proposer->id,
        ]);
    }

    public function test_sequence_is_per_zone_and_restarts_each_month_in_office_timezone(): void
    {
        $westRegion = SalesRegion::factory()->for(SalesZone::factory()->create(['code' => 'W']), 'zone')->create();
        $westOutlet = Outlet::factory()->create(['area_id' => SalesArea::factory()->for($westRegion, 'region')->create()->id]);
        $nationalUser = $this->userWithAccess(['dpl-proposals' => ['create']]);
        UserOrgAssignment::query()->where('user_id', $nationalUser->id)->update(['team_id' => $this->team->id]);
        $create = fn (Outlet $outlet, string $start = '2026-10-15') => $this->actingAs($nationalUser)
            ->postJson('/api/dpl/proposals', $this->payload(['outlet_id' => $outlet->id, 'date_start' => $start, 'date_end' => $start, 'items' => []]))
            ->assertCreated()
            ->json('data.dpl_number');

        $this->assertSame('2610-SAI-E-0001', $create($this->outlet));
        $this->assertSame('2610-SAI-E-0002', $create($this->outlet));
        $this->assertSame('2610-SAI-W-0001', $create($westOutlet));

        // 31 Okt 18:00 UTC = 1 Nov 01:00 WIB: sudah bulan baru.
        $this->travelTo(CarbonImmutable::parse('2026-10-31 18:00', 'UTC'));
        $this->assertSame('2611-SAI-E-0001', $create($this->outlet, '2026-11-01'));
    }

    public function test_period_rules_backdate_and_max_one_year(): void
    {
        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['date_start' => '2026-10-14']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_start');

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['date_end' => '2027-10-16']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_end');

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['date_end' => '2027-10-15']))
            ->assertCreated();
    }

    public function test_outlet_and_distributor_must_be_in_user_scope(): void
    {
        $foreignOutlet = Outlet::factory()->create();
        $foreignDistributor = Distributor::factory()->create();
        $foreignDistributor->regions()->attach(SalesRegion::factory()->create());

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['outlet_id' => $foreignOutlet->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('outlet_id');

        $items = $this->payload()['items'];
        $items[0]['distributor_id'] = $foreignDistributor->id;
        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['items' => $items]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.distributor_id');

        $this->assertDatabaseCount(DplProposal::class, 0);
        $this->assertDatabaseCount('dpl.dpl_number_sequences', 0);
    }

    public function test_outlet_without_area_cannot_get_a_number(): void
    {
        $nationalUser = $this->userWithAccess(['dpl-proposals' => ['create']]);
        UserOrgAssignment::query()->where('user_id', $nationalUser->id)->update(['team_id' => $this->team->id]);
        $outlet = Outlet::factory()->create(['area_id' => null]);

        $this->actingAs($nationalUser)->postJson('/api/dpl/proposals', $this->payload(['outlet_id' => $outlet->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('outlet_id');
    }

    public function test_team_comes_from_user_assignment(): void
    {
        $withoutTeam = $this->proposer($this->region, null);
        $this->actingAs($withoutTeam)->postJson('/api/dpl/proposals', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('team_code');

        $twoTeams = $this->proposer($this->region, $this->team);
        UserOrgAssignment::factory()->for($twoTeams)->forRegion($this->region)->create(['team_id' => Team::factory()->create(['code' => 'B'])->id]);

        $this->actingAs($twoTeams)->postJson('/api/dpl/proposals', $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('team_code');
        $this->actingAs($twoTeams)->postJson('/api/dpl/proposals', $this->payload(['team_code' => 'B']))
            ->assertCreated()->assertJsonPath('data.team_code', 'B');
    }

    public function test_update_syncs_items_and_only_creator_can_edit_draft(): void
    {
        $id = $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload())->json('data.id');
        $existing = DplItem::query()->sole();
        $otherProduct = Product::factory()->create();

        $update = $this->payload([
            'outlet_id' => null,
            'proposal_type' => 'CLEARANCE',
            'items' => [
                ['id' => $existing->id, 'product_id' => $this->product->id, 'distributor_id' => $this->distributor->id, 'minimum_order' => 300, 'margin' => '7.5'],
                ['product_id' => $otherProduct->id, 'distributor_id' => $this->distributor->id, 'minimum_order' => 100],
            ],
        ]);

        $this->actingAs($this->proposer)->putJson("/api/dpl/proposals/{$id}", $update)
            ->assertOk()
            ->assertJsonPath('data.proposal_type', 'CLEARANCE')
            ->assertJsonPath('data.outlet_id', $this->outlet->id)
            ->assertJsonCount(2, 'data.items');
        $this->assertSame('7.5000', $existing->fresh()->terms->margin);
        $this->assertSame(300, $existing->fresh()->minimum_order);

        // Item yang tidak dikirim dihapus (soft delete).
        $this->actingAs($this->proposer)->putJson("/api/dpl/proposals/{$id}", $this->payload(['items' => []]))->assertOk();
        $this->assertSoftDeleted($existing);

        $colleague = $this->proposer($this->region, $this->team);
        $this->actingAs($colleague)->getJson("/api/dpl/proposals/{$id}")->assertOk()->assertJsonPath('data.can_edit', false);
        $this->actingAs($colleague)->putJson("/api/dpl/proposals/{$id}", $update)->assertForbidden();
        $this->actingAs($colleague)->deleteJson("/api/dpl/proposals/{$id}")->assertForbidden();

        DplProposal::query()->whereKey($id)->update(['proposal_status' => DplProposal::STATUS_SUBMITTED]);
        $this->actingAs($this->proposer)->putJson("/api/dpl/proposals/{$id}", $update)->assertForbidden();
    }

    public function test_deleted_draft_number_is_not_reused(): void
    {
        $id = $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload())->json('data.id');

        $this->actingAs($this->proposer)->deleteJson("/api/dpl/proposals/{$id}")->assertOk();
        $this->assertSoftDeleted(DplProposal::class, ['id' => $id]);

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload())
            ->assertJsonPath('data.dpl_number', '2610-SAI-E-0002');
    }

    public function test_proposals_are_visible_by_outlet_region(): void
    {
        $id = $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload())->json('data.id');
        $outsider = $this->proposer(SalesRegion::factory()->create(), $this->team);
        $columns = ['dpl_number', 'date_offer', 'date_start', 'date_end', 'proposal_status', 'created_at'];

        $this->actingAs($this->proposer)->getJson('/api/dpl/proposals/datatable?'.$this->dataTableQuery($columns))
            ->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.can_edit', true);
        $this->actingAs($outsider)->getJson('/api/dpl/proposals/datatable?'.$this->dataTableQuery($columns))
            ->assertJsonPath('recordsFiltered', 0);
        $this->actingAs($outsider)->getJson("/api/dpl/proposals/{$id}")->assertNotFound();
        $this->actingAs($outsider)->get("/dpl/proposals/{$id}")->assertNotFound();

        $this->actingAs($this->proposer)->get('/dpl/proposals')->assertOk()->assertSee('proposals.js');
        $this->actingAs($this->proposer)->get('/dpl/proposals/create')->assertOk()->assertSee('proposal-form.js');
        $this->actingAs($this->proposer)->get("/dpl/proposals/{$id}")->assertOk()->assertSee('2610-SAI-E-0001');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(array $overrides = []): array
    {
        return ['product_id' => $this->product->id, 'distributor_id' => $this->distributor->id, 'minimum_order' => 200, ...$overrides];
    }

    public function test_same_product_cannot_appear_twice_for_one_distributor(): void
    {
        $otherDistributor = Distributor::factory()->create();
        $otherDistributor->regions()->attach($this->region);

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['items' => [$this->item(), $this->item()]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload([
            'items' => [$this->item(), $this->item(['distributor_id' => $otherDistributor->id])],
        ]))->assertCreated();
    }

    public function test_principle_off_discount_marks_buyer_reward(): void
    {
        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload([
            'items' => [$this->item(['principle_discount_off' => '2', 'is_buyer_reward' => false])],
        ]))->assertCreated()->assertJsonPath('data.items.0.is_buyer_reward', true);
    }

    public function test_delivery_mode_rules_for_logistic_fee(): void
    {
        $post = fn (array $item) => $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['items' => [$this->item($item)]]));

        $post(['is_direct_delivery' => true])->assertUnprocessable()->assertJsonValidationErrors('items.0.logistic_fee');
        $post(['is_direct_delivery' => true, 'is_factory_pickup' => true, 'logistic_fee' => '1000'])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.is_factory_pickup');

        $post(['is_direct_delivery' => true, 'logistic_fee' => '0'])->assertCreated()->assertJsonPath('data.items.0.terms.logistic_fee', '0.0000');
        $post(['is_factory_pickup' => true, 'logistic_fee' => '5000'])->assertCreated()->assertJsonPath('data.items.0.terms.logistic_fee', '0.0000');
        $post(['logistic_fee' => '5000'])->assertCreated()->assertJsonPath('data.items.0.terms.logistic_fee', null);
    }

    public function test_database_rejects_direct_and_factory_pickup_together(): void
    {
        $this->expectException(QueryException::class);

        DplItem::factory()->create(['is_direct_delivery' => true, 'is_factory_pickup' => true]);
    }

    public function test_hna_snapshot_uses_price_type_valid_on_date_start_and_pricing_endpoint(): void
    {
        ProductPrice::factory()->for($this->product)->create(['price' => '100000', 'regular_price' => '95000', 'valid_from' => '2026-01-01', 'valid_until' => '2026-10-31']);
        ProductPrice::factory()->for($this->product)->create(['price' => '110000', 'regular_price' => '100000', 'valid_from' => '2026-11-01']);
        CommercialParameter::factory()->create(['code' => 'PPN_PERCENT', 'value' => CommercialParameter::wrapValue('number', '11')]);

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['date_start' => '2026-11-05']))
            ->assertCreated()->assertJsonPath('data.items.0.terms.base_price', '110000.0000');

        // PRICE_LAMA_2025 = regular_price.
        $items = [$this->item(['price_type' => 'PRICE_LAMA_2025'])];
        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['date_start' => '2026-11-05', 'items' => $items]))
            ->assertCreated()
            ->assertJsonPath('data.items.0.terms.price_type', 'PRICE_LAMA_2025')
            ->assertJsonPath('data.items.0.terms.base_price', '100000.0000');

        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['items' => [$this->item(['price_type' => 'PRICE_X'])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.price_type');

        $this->actingAs($this->proposer)->getJson('/api/dpl/proposals/pricing?date=2026-10-20')
            ->assertOk()
            ->assertJsonPath('data.ppn_percent', '11')
            ->assertJsonPath('data.default_price_type', 'PRICE_BARU_2026')
            ->assertJsonPath("data.prices.{$this->product->id}.PRICE_BARU_2026", '100000.0000')
            ->assertJsonPath("data.prices.{$this->product->id}.PRICE_LAMA_2025", '95000.0000');
    }

    public function test_minimum_order_is_required(): void
    {
        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['items' => [$this->item(['minimum_order' => null])]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.minimum_order');
    }

    public function test_end_date_cannot_be_backdated(): void
    {
        $this->actingAs($this->proposer)->postJson('/api/dpl/proposals', $this->payload(['date_end' => '2026-10-14']))
            ->assertUnprocessable()->assertJsonValidationErrors('date_end');
    }
}
