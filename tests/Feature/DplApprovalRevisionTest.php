<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Dpl\ApprovalInstance;
use App\Models\Dpl\ApprovalItemState;
use App\Models\Dpl\ApprovalOverrideRequest;
use App\Models\Dpl\ApproverAssignment;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplItemTerm;
use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Edit setelah diajukan (pengaju setelah reject, RM/NSM selama approval) dan Open item auto reject oleh GM.
 */
class DplApprovalRevisionTest extends TestCase
{
    use RefreshDatabase;

    private DplApprovalService $approvals;

    private User $proposer;

    /** @var array<string, User> */
    private array $approvers = [];

    private DplProposal $proposal;

    private int $first;

    private int $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $this->seed([ApprovalReferenceSeeder::class, CommercialParameterSeeder::class]);
        $this->approvals = app(DplApprovalService::class);

        $zone = SalesZone::factory()->create(['code' => 'E']);
        $region = SalesRegion::factory()->for($zone, 'zone')->create();
        $outlet = Outlet::factory()->create(['area_id' => SalesArea::factory()->for($region, 'region')->create()->id, 'city_id' => City::factory()->create()->id]);
        $this->proposer = User::factory()->create();

        foreach (['RM' => ['region_id' => $region->id], 'NSM' => ['zone_id' => $zone->id], 'GM' => [], 'SMD' => [], 'DMD' => []] as $role => $scope) {
            $this->approvers[$role] = User::factory()->create();
            ApproverAssignment::factory()->create(['role_id' => Role::query()->where('name', $role)->value('id'), 'user_id' => $this->approvers[$role]->id, ...$scope]);
        }

        $this->proposal = DplProposal::factory()->create([
            'outlet_id' => $outlet->id, 'team_code' => 'A', 'created_by_user_id' => $this->proposer->id,
            'date_start' => '2026-10-15', 'date_end' => '2026-12-31',
        ]);

        foreach (['5', '6'] as $margin) {
            DplItemTerm::factory()->for(DplItem::factory()->for($this->proposal, 'proposal'), 'item')->create(['margin' => $margin]);
        }

        [$this->first, $this->second] = $this->proposal->items()->orderBy('id')->pluck('id')->all();
        $this->approvals->submit($this->proposal, $this->proposer);
    }

    /**
     * @return array<string, string> nama role => status state item
     */
    private function statesOf(int $itemId): array
    {
        $instance = $this->proposal->approvalInstances()->where('status', '!=', ApprovalInstance::STATUS_CANCELLED)->latest('id')->sole();

        return ApprovalItemState::query()
            ->whereIn('step_instance_id', $instance->steps()->pluck('id'))
            ->where('dpl_item_id', $itemId)
            ->with('stepInstance.workflowStep.role')
            ->get()
            ->mapWithKeys(fn (ApprovalItemState $state) => [$state->stepInstance->workflowStep->role->name => $state->status])
            ->all();
    }

    private function editMargin(int $itemId, string $margin): void
    {
        DplItemTerm::query()->where('dpl_item_id', $itemId)->update(['margin' => $margin]);
    }

    public function test_proposer_edits_after_reject_and_unchanged_items_keep_approval(): void
    {
        $this->approvals->approve($this->proposal, $this->approvers['RM'], [$this->first]);
        $this->approvals->reject($this->proposal, $this->approvers['RM'], [$this->second], 'Margin terlalu besar');
        $proposal = $this->proposal->fresh();

        $this->assertSame(0, $this->approvals->editorSequence($proposal, $this->proposer));
        $this->assertNull($this->approvals->editorSequence($proposal, $this->approvers['GM']));

        $before = $this->approvals->snapshot($proposal);
        $this->editMargin($this->second, '4');
        $proposal = $this->approvals->resubmit($proposal, $this->proposer, $before, 0);

        $this->assertSame(DplProposal::STATUS_PARTIALLY_APPROVED, $proposal->proposal_status);
        $this->assertSame('APPROVED', $this->statesOf($this->first)['RM']);
        $this->assertSame(['PENDING'], array_values(array_unique($this->statesOf($this->second))));
        $this->assertSame(1, $proposal->revisions()->count());
        $this->assertSame(1, $proposal->approvalInstances()->where('status', ApprovalInstance::STATUS_CANCELLED)->count());

        // Approval berjalan lagi di instance baru.
        $this->approvals->approve($proposal, $this->approvers['RM'], [$this->second]);
        $this->assertSame('APPROVED', $this->statesOf($this->second)['RM']);
    }

    public function test_nsm_edit_keeps_rm_approval_and_restarts_from_nsm(): void
    {
        $this->approvals->approve($this->proposal, $this->approvers['RM'], [$this->first, $this->second]);
        $proposal = $this->proposal->fresh();

        $this->assertNull($this->approvals->editorSequence($proposal, $this->proposer), 'Pengaju tidak boleh edit selama approval.');
        $sequence = $this->approvals->editorSequence($proposal, $this->approvers['NSM']);
        $this->assertSame(2, $sequence);

        $before = $this->approvals->snapshot($proposal);
        $this->editMargin($this->first, '7');
        $this->approvals->resubmit($proposal, $this->approvers['NSM'], $before, $sequence);

        $this->assertSame('APPROVED', $this->statesOf($this->first)['RM']);
        $this->assertSame('PENDING', $this->statesOf($this->first)['NSM']);
        $this->assertSame('APPROVED', $this->statesOf($this->second)['RM']);
    }

    public function test_header_change_restarts_every_item(): void
    {
        $this->approvals->approve($this->proposal, $this->approvers['RM'], [$this->first]);
        $this->approvals->reject($this->proposal, $this->approvers['RM'], [$this->second], 'Periode salah');
        $proposal = $this->proposal->fresh();

        $before = $this->approvals->snapshot($proposal);
        $proposal->update(['date_end' => '2026-11-30']);
        $this->approvals->resubmit($proposal, $this->proposer, $before, 0);

        $this->assertSame('PENDING', $this->statesOf($this->first)['RM']);
        $this->assertSame(DplProposal::STATUS_IN_APPROVAL, $proposal->fresh()->proposal_status);
    }

    public function test_auto_rejected_item_can_be_opened_by_gm_on_request(): void
    {
        // Edit oleh pengaju tidak bisa: masih dalam approval. Buat DPL baru yang auto reject (10 + 25 = 35 > 30).
        $proposal = DplProposal::factory()->create([
            'outlet_id' => $this->proposal->outlet_id, 'team_code' => 'A', 'created_by_user_id' => $this->proposer->id,
            'date_start' => '2026-10-15', 'date_end' => '2026-12-31',
        ]);
        $item = DplItemTerm::factory()->for(DplItem::factory()->for($proposal, 'proposal'), 'item')->create(['margin' => '25'])->dpl_item_id;
        $proposal = $this->approvals->submit($proposal, $this->proposer);

        $this->assertSame(DplProposal::STATUS_REJECTED, $proposal->proposal_status);
        $this->assertSame([$item], $this->approvals->autoRejectedItemIds($proposal));

        $request = $this->approvals->requestOverride($proposal, $this->proposer, $item, 'Program khusus RS pemerintah');
        $this->assertSame(ApprovalOverrideRequest::STATUS_PENDING, $request->status);

        try {
            $this->approvals->reviewOverride($proposal, $this->approvers['RM'], [$item], true);
            $this->fail('Hanya GM yang boleh membuka.');
        } catch (ValidationException) {
            // diharapkan
        }

        $this->approvals->reviewOverride($proposal, $this->approvers['GM'], [$item], true, 'Disetujui');

        $this->assertSame(ApprovalOverrideRequest::STATUS_APPROVED, $request->fresh()->status);
        $this->assertSame(DplProposal::STATUS_IN_APPROVAL, $proposal->fresh()->proposal_status);
        $this->assertSame([], $this->approvals->autoRejectedItemIds($proposal->fresh()));
        $this->approvals->approve($proposal->fresh(), $this->approvers['RM'], [$item]);
    }

    public function test_manual_reject_cannot_be_opened(): void
    {
        $this->approvals->reject($this->proposal, $this->approvers['RM'], [$this->first], 'Tidak sesuai');

        $this->expectException(ValidationException::class);
        $this->approvals->reviewOverride($this->proposal->fresh(), $this->approvers['GM'], [$this->first], true);
    }
}
