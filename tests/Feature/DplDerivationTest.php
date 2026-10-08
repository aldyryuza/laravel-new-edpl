<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Distributor;
use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalItemState;
use App\Models\Dpl\ApproverAssignment;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplItemTerm;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplRelation;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Role;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use App\Services\Dpl\DplApprovalService;
use Carbon\CarbonImmutable;
use Database\Seeders\ApprovalReferenceSeeder;
use Database\Seeders\CommercialParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Make a Copy (AMENDMENT) & Duplicate (RENEWAL): hanya pemilik, item dihitung ulang, approval item yang sama
 * diwariskan pada amendment, renewal approval dari awal, DPL baru menggantikan sumber saat aktif.
 */
class DplDerivationTest extends TestCase
{
    use RefreshDatabase;

    private DplApprovalService $approvals;

    private User $proposer;

    /** @var array<string, User> */
    private array $approvers = [];

    private Distributor $distributor;

    private DplProposal $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $this->seed([ApprovalReferenceSeeder::class, CommercialParameterSeeder::class]);
        $this->approvals = app(DplApprovalService::class);

        $zone = SalesZone::factory()->create(['code' => 'E']);
        $region = SalesRegion::factory()->for($zone, 'zone')->create();
        $outlet = Outlet::factory()->create(['area_id' => SalesArea::factory()->for($region, 'region')->create()->id, 'city_id' => City::factory()->create()->id]);
        $this->proposer = $this->userWithAccess(['dpl-proposals' => ['read', 'create', 'update']]);

        foreach (['RM' => ['region_id' => $region->id], 'NSM' => ['zone_id' => $zone->id], 'GM' => [], 'SMD' => [], 'DMD' => []] as $role => $scope) {
            $this->approvers[$role] = User::factory()->create();
            ApproverAssignment::factory()->create(['role_id' => Role::query()->where('name', $role)->value('id'), 'user_id' => $this->approvers[$role]->id, ...$scope]);
        }

        // Distributor tanpa kota & logistic: nilai hitung ulang = margin distributor 5, logistic kosong.
        $this->distributor = Distributor::factory()->create(['group_code' => 'AMJ', 'city_id' => null, 'margin' => '5']);

        $this->source = DplProposal::factory()->create([
            'outlet_id' => $outlet->id, 'team_code' => 'A', 'created_by_user_id' => $this->proposer->id,
            'date_start' => '2026-10-01', 'date_end' => '2026-12-31',
        ]);

        foreach ([500, 250] as $volume) {
            DplItemTerm::factory()->for(DplItem::factory()->for($this->source, 'proposal')->state([
                'distributor_id' => $this->distributor->id,
                'product_id' => Product::factory()->create(['volume' => $volume])->id,
            ]), 'item')->create(['margin' => '5']);
        }

        $this->approveAll($this->approvals->submit($this->source, $this->proposer));
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $this->source->fresh()->program_status);
    }

    private function approveAll(DplProposal $proposal): void
    {
        foreach (['RM', 'NSM', 'GM', 'SMD', 'DMD'] as $role) {
            $ids = $this->approvals->actionableStates($proposal->fresh(), $this->approvers[$role])->keys()->all();

            if ($ids !== []) {
                $this->approvals->approve($proposal->fresh(), $this->approvers[$role], $ids);
            }
        }
    }

    private function amend(): DplProposal
    {
        $id = $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$this->source->id}/amend")->assertCreated()->json('data.id');

        return DplProposal::query()->findOrFail($id);
    }

    /**
     * @return array<int, string> status semua state item di instance aktif
     */
    private function itemStatuses(DplProposal $proposal, int $itemId): array
    {
        $instance = $proposal->approvalInstances()->where('status', '!=', ApprovalInstance::STATUS_CANCELLED)->latest('id')->sole();

        return ApprovalItemState::query()->whereIn('step_instance_id', $instance->steps()->pluck('id'))->where('dpl_item_id', $itemId)->pluck('status')->unique()->values()->all();
    }

    public function test_only_owner_can_copy_and_amendment_needs_active_program(): void
    {
        $other = $this->userWithAccess(['dpl-proposals' => ['read', 'create']]);
        $this->actingAs($other)->postJson("/api/dpl/proposals/{$this->source->id}/amend")->assertUnprocessable();
        $this->actingAs($other)->postJson("/api/dpl/proposals/{$this->source->id}/renew", ['date_start' => '2027-01-01', 'date_end' => '2027-03-31'])->assertUnprocessable();

        $this->actingAs($this->proposer)->getJson("/api/dpl/proposals/{$this->source->id}")
            ->assertJsonPath('data.can_amend', true)
            ->assertJsonPath('data.can_renew', true);

        $this->actingAs($this->proposer)->get("/dpl/proposals/{$this->source->id}")
            ->assertOk()
            ->assertSee('id="btn-amend"', escape: false)
            ->assertSee('id="renew-modal"', escape: false);

        $this->source->update(['program_status' => DplProposal::PROGRAM_EXPIRED]);
        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$this->source->id}/amend")->assertUnprocessable();
    }

    public function test_unchanged_amendment_is_approved_immediately_and_supersedes_source(): void
    {
        $copy = $this->amend();

        $this->assertSame(DplProposal::STATUS_DRAFT, $copy->proposal_status);
        $this->assertSame(['2026-10-15', '2026-12-31'], [$copy->date_start->toDateString(), $copy->date_end->toDateString()]);
        $this->assertSame(2, $copy->items()->count());
        $this->assertDatabaseHas(DplRelation::class, ['source_dpl_id' => $this->source->id, 'target_dpl_id' => $copy->id, 'relation_type' => 'AMENDMENT']);

        $copy = $this->approvals->submit($copy, $this->proposer);

        $this->assertSame(DplProposal::STATUS_APPROVED, $copy->proposal_status);
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $copy->program_status);
        $this->assertSame(DplProposal::PROGRAM_SUPERSEDED, $this->source->fresh()->program_status);
    }

    public function test_edited_and_new_items_need_approval_while_unchanged_keep_it(): void
    {
        $copy = $this->amend();
        [$kept, $edited] = $copy->items()->orderBy('id')->get()->all();
        DplItemTerm::query()->where('dpl_item_id', $edited->id)->update(['principle_discount_on' => '12']);
        $added = DplItemTerm::factory()->for(DplItem::factory()->for($copy, 'proposal')->state(['distributor_id' => $this->distributor->id]), 'item')
            ->create(['margin' => '5'])->dpl_item_id;

        $copy = $this->approvals->submit($copy, $this->proposer);

        $this->assertSame(['APPROVED'], $this->itemStatuses($copy, $kept->id));
        $this->assertSame(['PENDING'], $this->itemStatuses($copy, $edited->id));
        $this->assertSame(['PENDING'], $this->itemStatuses($copy, $added));
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $this->source->fresh()->program_status, 'Sumber tetap aktif selama amendment diproses.');

        $this->approveAll($copy);
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $copy->fresh()->program_status);
        $this->assertSame(DplProposal::PROGRAM_SUPERSEDED, $this->source->fresh()->program_status);
    }

    public function test_recalculated_margin_change_requires_reapproval(): void
    {
        $this->distributor->update(['margin' => '6']);

        $copy = $this->amend();
        $this->assertSame(['6.0000'], $copy->items()->with('terms')->get()->pluck('terms.margin')->unique()->values()->all());

        $copy = $this->approvals->submit($copy, $this->proposer);
        $this->assertSame(DplProposal::STATUS_IN_APPROVAL, $copy->proposal_status);
    }

    public function test_amendment_period_is_locked_on_edit(): void
    {
        $copy = $this->amend()->load('items.terms');
        $items = $copy->items->map(fn (DplItem $item) => [
            'id' => $item->id, 'product_id' => $item->product_id, 'distributor_id' => $item->distributor_id,
            'minimum_order' => 200, 'principle_discount_on' => '10', 'margin' => $item->terms->margin,
        ])->all();

        $this->actingAs($this->proposer)->putJson("/api/dpl/proposals/{$copy->id}", [
            'proposal_type' => 'NORMAL', 'date_start' => '2026-11-01', 'date_end' => '2027-06-30', 'is_contract' => false, 'items' => $items,
        ])->assertOk()->assertJsonPath('data.date_start', '2026-10-15')->assertJsonPath('data.date_end', '2026-12-31');
    }

    public function test_renewal_from_expired_dpl_restarts_full_approval(): void
    {
        $this->source->update(['program_status' => DplProposal::PROGRAM_EXPIRED]);
        $url = "/api/dpl/proposals/{$this->source->id}/renew";

        $this->actingAs($this->proposer)->postJson($url, ['date_start' => '2026-10-14', 'date_end' => '2026-12-31'])->assertUnprocessable();
        $this->actingAs($this->proposer)->postJson($url, ['date_start' => '2027-01-01', 'date_end' => '2028-02-01'])->assertUnprocessable();

        $id = $this->actingAs($this->proposer)->postJson($url, ['date_start' => '2027-01-01', 'date_end' => '2027-12-31'])->assertCreated()->json('data.id');
        $renewal = DplProposal::query()->findOrFail($id);

        $this->assertDatabaseHas(DplRelation::class, ['source_dpl_id' => $this->source->id, 'target_dpl_id' => $renewal->id, 'relation_type' => 'RENEWAL']);
        $renewal = $this->approvals->submit($renewal, $this->proposer);
        $this->assertSame(DplProposal::STATUS_IN_APPROVAL, $renewal->proposal_status);
        $this->assertSame(['PENDING'], $this->itemStatuses($renewal, $renewal->items()->value('id')));
    }
}
