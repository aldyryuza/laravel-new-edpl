<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplStatusHistory;
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
            'date_offer' => '2026-10-15',
            'date_start' => '2026-10-15',
            'date_end' => '2027-10-14',
            'is_contract' => false,
            'description' => null,
            'items' => [[
                'product_id' => $this->product->id,
                'distributor_id' => $this->distributor->id,
                'minimum_order' => 200,
                'principle_discount_on' => '10.5',
                'negotiated_price' => '125000',
                'is_direct_delivery' => true,
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
            ->assertJsonPath('data.date_start', '2026-10-15')
            ->assertJsonPath('data.can_edit', true);

        $item = DplItem::query()->sole();
        $this->assertTrue($item->is_direct_delivery);
        $this->assertSame(200, $item->minimum_order);
        $this->assertSame('10.5000', $item->terms->principle_discount_on);
        $this->assertSame('0.0000', $item->terms->principle_discount_off);
        $this->assertSame('125000.0000', $item->terms->negotiated_price);

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
                ['id' => $existing->id, 'product_id' => $this->product->id, 'distributor_id' => $this->distributor->id, 'negotiated_price' => '99000'],
                ['product_id' => $otherProduct->id, 'distributor_id' => $this->distributor->id],
            ],
        ]);

        $this->actingAs($this->proposer)->putJson("/api/dpl/proposals/{$id}", $update)
            ->assertOk()
            ->assertJsonPath('data.proposal_type', 'CLEARANCE')
            ->assertJsonPath('data.outlet_id', $this->outlet->id)
            ->assertJsonCount(2, 'data.items');
        $this->assertSame('99000.0000', $existing->fresh()->terms->negotiated_price);

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
}
