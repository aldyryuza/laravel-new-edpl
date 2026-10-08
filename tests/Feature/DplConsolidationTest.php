<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Dpl\ApprovalStepInstance;
use App\Models\Dpl\ApproverAssignment;
use App\Models\Dpl\DplConsolidation;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplRelation;
use App\Models\Outlet;
use App\Models\OutletGroup;
use App\Models\OutletGroupMember;
use App\Models\OutletSalesAssignment;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\Team;
use App\Models\User;
use App\Models\UserOrgAssignment;
use App\Services\Dpl\DplApprovalService;
use Carbon\CarbonImmutable;
use Database\Seeders\ApprovalReferenceSeeder;
use Database\Seeders\CommercialParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Konsolidasi / Group RS: DPL induk ...-C tanpa outlet, outlet manual / group RS, approver wilayah pengaju,
 * PIC wajib untuk mode Group, pemecahan menjadi DPL per outlet setelah semua item approved.
 */
class DplConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private SalesRegion $region;

    private SalesZone $zone;

    private Outlet $outlet;

    private Outlet $outletNoBed;

    /** Anggota group RS di luar wilayah pengaju (zona W). */
    private Outlet $remoteOutlet;

    private OutletGroup $group;

    private Product $product;

    private Distributor $distributor;

    private User $proposer;

    /** @var array<string, User> */
    private array $approvers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $this->seed([ApprovalReferenceSeeder::class, CommercialParameterSeeder::class]);

        $this->zone = SalesZone::factory()->create(['code' => 'E']);
        $this->region = SalesRegion::factory()->for($this->zone, 'zone')->create();
        $area = SalesArea::factory()->for($this->region, 'region')->create();
        $remoteArea = SalesArea::factory()->for(SalesRegion::factory()->for(SalesZone::factory()->create(['code' => 'W']), 'zone'), 'region')->create();

        $this->outlet = Outlet::factory()->create(['area_id' => $area->id, 'bed_count' => 100]);
        $this->outletNoBed = Outlet::factory()->create(['area_id' => $area->id, 'bed_count' => 0]);
        $this->remoteOutlet = Outlet::factory()->create(['area_id' => $remoteArea->id, 'bed_count' => 40]);

        $this->group = OutletGroup::factory()->create();
        foreach ([$this->outlet, $this->remoteOutlet] as $member) {
            OutletGroupMember::factory()->create(['outlet_group_id' => $this->group->id, 'outlet_id' => $member->id, 'valid_until' => null]);
        }

        $this->product = Product::factory()->create(['volume' => 500]);
        $this->distributor = Distributor::factory()->create();
        $this->distributor->regions()->attach($this->region);

        $this->proposer = $this->proposer(Team::factory()->create(['code' => 'A']));

        foreach (['RM' => ['region_id' => $this->region->id], 'NSM' => ['zone_id' => $this->zone->id], 'GM' => [], 'SMD' => [], 'DMD' => []] as $role => $scope) {
            $user = $this->userWithAccess(['dpl-proposals' => ['read', 'update', 'approve']], national: false);
            ApproverAssignment::factory()->create(['role_id' => Role::query()->where('name', $role)->value('id'), 'user_id' => $user->id, ...$scope]);
            $this->approvers[$role] = $user;
        }
    }

    private function proposer(Team $team): User
    {
        $user = $this->userWithAccess(['dpl-proposals' => ['read', 'create', 'update']], national: false);
        UserOrgAssignment::factory()->for($user)->forRegion($this->region)->create(['team_id' => $team->id]);

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $outlets
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $outlets, array $overrides = []): array
    {
        return [
            'proposal_type' => DplProposal::TYPE_CONSOLIDATION,
            'consolidation_mode' => DplConsolidation::MODE_MANUAL,
            'date_start' => '2026-10-15',
            'date_end' => '2026-12-31',
            'is_contract' => false,
            'outlets' => $outlets,
            'items' => [[
                'product_id' => $this->product->id,
                'distributor_id' => $this->distributor->id,
                'minimum_order' => 100,
                'principle_discount_on' => '10',
                'margin' => '5',
            ]],
            ...$overrides,
        ];
    }

    private function create(array $payload, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? $this->proposer)->postJson('/api/dpl/proposals', $payload);
    }

    private function groupDraft(?User $user = null): DplProposal
    {
        $id = $this->create($this->payload(
            [['outlet_id' => $this->outlet->id, 'is_contract' => true], ['outlet_id' => $this->remoteOutlet->id]],
            ['consolidation_mode' => DplConsolidation::MODE_GROUP, 'outlet_group_id' => $this->group->id],
        ), $user)->assertCreated()->json('data.id');

        return DplProposal::query()->findOrFail($id);
    }

    private function approveAll(DplProposal $proposal): void
    {
        foreach (['RM', 'NSM', 'GM', 'SMD', 'DMD'] as $role) {
            $this->actingAs($this->approvers[$role])
                ->postJson("/api/dpl/proposals/{$proposal->id}/approve", ['item_ids' => $proposal->items()->pluck('id')->all()])
                ->assertOk();
        }
    }

    public function test_manual_consolidation_draft_stores_outlets_with_bed_order_and_parent_number(): void
    {
        $response = $this->create($this->payload([
            ['outlet_id' => $this->outlet->id, 'is_contract' => true, 'bed_order_minimum' => '1'],
            ['outlet_id' => $this->outletNoBed->id, 'bed_order_minimum' => '50'],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.dpl_number', '2610-SAI-E-0001-C')
            ->assertJsonPath('data.outlet_id', null)
            ->assertJsonPath('data.proposal_type', DplProposal::TYPE_CONSOLIDATION)
            ->assertJsonPath('data.consolidation.mode', DplConsolidation::MODE_MANUAL)
            ->assertJsonPath('data.consolidation.region_id', $this->region->id);

        $consolidation = DplConsolidation::query()->with('outlets')->sole();
        $this->assertSame('2610-SAI-E-0001-C', $consolidation->code);
        $rows = $consolidation->outlets->keyBy('outlet_id');
        // Bed 100 × 0,25 × 90; bed 0 -> manual.
        $this->assertSame('2250.00', $rows[$this->outlet->id]->bed_order_minimum);
        $this->assertTrue($rows[$this->outlet->id]->is_contract);
        $this->assertSame('50.00', $rows[$this->outletNoBed->id]->bed_order_minimum);

        $this->actingAs($this->proposer)->getJson("/api/dpl/proposals/{$response->json('data.id')}")
            ->assertOk()
            ->assertJsonCount(2, 'data.consolidation.outlets');
    }

    public function test_manual_mode_rejects_outlets_outside_region_direct_items_and_empty_list(): void
    {
        $this->create($this->payload([['outlet_id' => $this->remoteOutlet->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outlets.0.outlet_id' => 'di luar wilayah Anda']);

        $payload = $this->payload([['outlet_id' => $this->outlet->id]]);
        $payload['items'][0]['is_direct_delivery'] = true;
        $payload['items'][0]['logistic_fee'] = '1';
        $this->create($payload)->assertUnprocessable()->assertJsonValidationErrors(['items.0.is_direct_delivery']);

        $this->create($this->payload([]))->assertUnprocessable()->assertJsonValidationErrors(['outlets' => 'Tambahkan minimal satu outlet.']);
    }

    public function test_group_mode_accepts_only_group_members_and_requires_pic_to_submit(): void
    {
        $this->create($this->payload(
            [['outlet_id' => $this->outletNoBed->id]],
            ['consolidation_mode' => DplConsolidation::MODE_GROUP, 'outlet_group_id' => $this->group->id],
        ))->assertUnprocessable()->assertJsonValidationErrors(['outlets.0.outlet_id' => 'bukan anggota group RS']);

        $proposal = $this->groupDraft();

        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outlets']);

        OutletSalesAssignment::factory()->create(['outlet_id' => $this->outlet->id]);
        OutletSalesAssignment::factory()->create(['outlet_id' => $this->remoteOutlet->id]);

        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.proposal_status', DplProposal::STATUS_IN_APPROVAL);

        // Approver RM/NSM dari wilayah pengaju, walau ada outlet di zona lain.
        $rmStep = $proposal->approvalInstances()->sole()->steps()->with('workflowStep.role', 'assigneeUsers')->get()
            ->first(fn (ApprovalStepInstance $step) => $step->workflowStep->role->name === 'RM');
        $this->assertSame([$this->approvers['RM']->id], $rmStep->assigneeUsers->pluck('id')->all());
    }

    public function test_full_approval_splits_group_consolidation_into_child_dpls_owned_by_pic(): void
    {
        $am = User::factory()->create();
        $spv = User::factory()->create();
        OutletSalesAssignment::factory()->create(['outlet_id' => $this->outlet->id, 'am_user_id' => $am->id, 'spv_user_id' => $spv->id]);
        OutletSalesAssignment::factory()->create(['outlet_id' => $this->remoteOutlet->id, 'am_user_id' => $am->id]);
        $old = DplProposal::factory()->active()->create(['outlet_id' => $this->outlet->id, 'team_code' => 'A']);

        $proposal = $this->groupDraft();
        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/submit")->assertOk();
        $this->approveAll($proposal);

        $proposal->refresh();
        $this->assertSame(DplProposal::STATUS_APPROVED, $proposal->proposal_status);
        $this->assertSame(DplProposal::PROGRAM_INACTIVE, $proposal->program_status);
        $this->assertSame(DplConsolidation::STATUS_GENERATED, $proposal->consolidation->status);

        $rows = $proposal->consolidation->outlets()->with('generatedDpl.items.terms')->get()->keyBy('outlet_id');
        $child = $rows[$this->outlet->id]->generatedDpl;
        $remoteChild = $rows[$this->remoteOutlet->id]->generatedDpl;

        // Nomor dari zona outlet; pemilik SPV, bila kosong AM; Kontrak & 25% Bed per outlet; item disalin.
        $this->assertSame('2610-SAI-E-0002', $child->dpl_number);
        $this->assertSame('2610-SAI-W-0001', $remoteChild->dpl_number);
        $this->assertSame($spv->id, $child->created_by_user_id);
        $this->assertSame($am->id, $remoteChild->created_by_user_id);
        $this->assertTrue($child->is_contract);
        $this->assertSame('2250.00', $child->bed_order_minimum);
        $this->assertSame('900.00', $remoteChild->bed_order_minimum);
        $this->assertSame(DplProposal::STATUS_APPROVED, $child->proposal_status);
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $child->program_status);
        $this->assertSame('10.0000', $child->items->sole()->terms->principle_discount_on);
        $this->assertSame(DplProposal::PROGRAM_SUPERSEDED, $old->fresh()->program_status);
        $this->assertDatabaseHas(DplRelation::class, ['source_dpl_id' => $proposal->id, 'target_dpl_id' => $child->id, 'relation_type' => DplRelation::TYPE_CONSOLIDATION_CHILD]);

        // Job harian tidak mengaktifkan induk.
        app(DplApprovalService::class)->refreshProgramStatuses();
        $this->assertSame(DplProposal::PROGRAM_INACTIVE, $proposal->fresh()->program_status);
    }

    public function test_manual_mode_and_creator_owned_team_children_belong_to_proposer(): void
    {
        $teamB = $this->proposer(Team::factory()->create(['code' => 'B']));
        $proposal = $this->groupDraft($teamB);

        // Team B: DPL anak milik pengaju, PIC tidak diwajibkan.
        $this->actingAs($teamB)->postJson("/api/dpl/proposals/{$proposal->id}/submit")->assertOk();
        $this->approveAll($proposal);

        $owners = DplProposal::query()->whereIn('id', $proposal->consolidation->outlets()->pluck('generated_dpl_id'))->pluck('created_by_user_id')->unique();
        $this->assertSame([$teamB->id], $owners->values()->all());
    }

    public function test_future_start_children_activate_through_daily_job(): void
    {
        $id = $this->create($this->payload([['outlet_id' => $this->outlet->id]], ['date_start' => '2026-11-01']))->json('data.id');
        $proposal = DplProposal::query()->findOrFail($id);
        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/submit")->assertOk();
        $this->approveAll($proposal);

        $child = $proposal->consolidation->outlets()->sole()->generatedDpl;
        $this->assertSame($this->proposer->id, $child->created_by_user_id);
        $this->assertSame(DplProposal::PROGRAM_INACTIVE, $child->program_status);

        app(DplApprovalService::class)->refreshProgramStatuses(CarbonImmutable::parse('2026-11-01', 'Asia/Jakarta'));
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $child->fresh()->program_status);
        $this->assertSame(DplProposal::PROGRAM_INACTIVE, $proposal->fresh()->program_status);
    }

    public function test_parent_is_visible_to_creator_and_users_covering_an_outlet_only(): void
    {
        $id = $this->groupDraft()->id;

        $remoteUser = $this->userWithAccess(['dpl-proposals' => ['read']], national: false);
        UserOrgAssignment::factory()->for($remoteUser)->forArea($this->remoteOutlet->area)->create();
        $outsider = $this->userWithAccess(['dpl-proposals' => ['read']], national: false);
        UserOrgAssignment::factory()->for($outsider)->forRegion(SalesRegion::factory()->for($this->zone, 'zone')->create())->create();

        $this->actingAs($this->proposer)->getJson("/api/dpl/proposals/{$id}")->assertOk();
        $this->actingAs($remoteUser)->getJson("/api/dpl/proposals/{$id}")->assertOk();
        $this->actingAs($outsider)->getJson("/api/dpl/proposals/{$id}")->assertNotFound();
    }

    public function test_group_members_endpoint_lists_active_members_with_pic_flag(): void
    {
        OutletSalesAssignment::factory()->create(['outlet_id' => $this->outlet->id]);

        $this->actingAs($this->proposer)->getJson("/api/dpl/proposals/outlet-groups/{$this->group->id}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $this->outlet->id, 'has_pic' => true, 'bed_count' => 100])
            ->assertJsonFragment(['id' => $this->remoteOutlet->id, 'has_pic' => false]);
    }

    public function test_pages_and_export_show_consolidation_outlets_and_child_uses_parent_approval(): void
    {
        $printer = $this->userWithAccess(['dpl-proposals' => ['read', 'print']]);
        $id = $this->create($this->payload([['outlet_id' => $this->outlet->id, 'is_contract' => true]]))->json('data.id');
        $proposal = DplProposal::query()->findOrFail($id);

        $this->actingAs($this->proposer)->get("/dpl/proposals/{$id}")->assertOk()->assertSee('id="consolidation-card"', escape: false);
        $this->actingAs($this->proposer)->get('/dpl/proposals/create')->assertOk()->assertSee('Konsolidasi / Group RS');

        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$id}/submit")->assertOk();
        $this->approveAll($proposal);
        $child = $proposal->consolidation->outlets()->sole()->generatedDpl;

        $sheet = fn (DplProposal $dpl) => collect(IOFactory::load(
            $this->actingAs($printer)->get("/dpl/proposals/{$dpl->id}/export")->assertOk()->baseResponse->getFile()->getPathname(),
        )->getActiveSheet()->toArray())->flatten()->filter()->values();

        $parentValues = $sheet($proposal);
        $this->assertTrue($parentValues->contains('OUTLET KONSOLIDASI'));
        $this->assertTrue($parentValues->contains($child->dpl_number));
        $this->assertTrue($parentValues->contains('KONSOLIDASI'));

        $childValues = $sheet($child);
        $this->assertTrue($childValues->contains($proposal->dpl_number));
        $this->assertTrue($childValues->contains('Approved'));
    }

    public function test_editing_outlets_after_submit_requires_approval_again(): void
    {
        $id = $this->create($this->payload([['outlet_id' => $this->outlet->id], ['outlet_id' => $this->outletNoBed->id, 'bed_order_minimum' => '5']]))->json('data.id');
        $proposal = DplProposal::query()->findOrFail($id);
        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$id}/submit")->assertOk();
        $itemId = $proposal->items()->value('id');
        $this->actingAs($this->approvers['RM'])->postJson("/api/dpl/proposals/{$id}/approve", ['item_ids' => [$itemId]])->assertOk();

        // NSM (boleh edit, wilayah zona) menghapus satu outlet: outlet berubah -> semua item approval ulang dari level NSM.
        UserOrgAssignment::factory()->for($this->approvers['NSM'])->forZone($this->zone)->create();
        $payload = $this->payload([['outlet_id' => $this->outlet->id]]);
        $payload['items'][0]['id'] = $itemId;
        $this->actingAs($this->approvers['NSM'])->putJson("/api/dpl/proposals/{$id}", $payload)->assertOk();

        $this->assertSame([$this->outlet->id], $proposal->consolidation->outlets()->pluck('outlet_id')->all());
        $this->actingAs($this->approvers['NSM'])->getJson("/api/dpl/proposals/{$id}/approval")
            ->assertOk()
            ->assertJsonPath('data.actionable_item_ids', [$itemId]);
    }
}
