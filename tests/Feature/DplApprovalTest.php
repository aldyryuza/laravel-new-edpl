<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Dpl\ApprovalItemState;
use App\Models\Dpl\ApprovalRule;
use App\Models\Dpl\ApprovalWorkflowStep;
use App\Models\Dpl\ApproverAssignment;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplItemTerm;
use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
use App\Models\Province;
use App\Models\Role;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ApprovalReferenceSeeder;
use Database\Seeders\CommercialParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Submit & approval DPL per item: jalur (aturan / lengkap), approver per wilayah, urutan RM -> NSM -> (GM/SMD/DMD),
 * reject, auto reject, aktivasi + supersede, job harian.
 */
class DplApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private SalesRegion $region;

    private User $proposer;

    /** @var array<string, User> */
    private array $approvers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $this->seed([ApprovalReferenceSeeder::class, CommercialParameterSeeder::class]);

        $zone = SalesZone::factory()->create(['code' => 'E']);
        $this->region = SalesRegion::factory()->for($zone, 'zone')->create();
        $area = SalesArea::factory()->for($this->region, 'region')->create();
        $this->outlet = Outlet::factory()->create(['area_id' => $area->id, 'city_id' => City::factory()->create()->id]);
        $this->proposer = $this->userWithAccess(['dpl-proposals' => ['read', 'create', 'update']]);

        foreach (['RM' => ['region_id' => $this->region->id], 'NSM' => ['zone_id' => $zone->id], 'GM' => [], 'SMD' => [], 'DMD' => []] as $role => $scope) {
            $this->approvers[$role] = $this->approver($role, $scope);
        }
    }

    /**
     * @param  array<string, int>  $scope
     */
    private function approver(string $role, array $scope): User
    {
        $user = $this->userWithAccess(['dpl-proposals' => ['read', 'approve']], national: false);
        ApproverAssignment::factory()->create(['role_id' => Role::query()->where('name', $role)->value('id'), 'user_id' => $user->id, ...$scope]);

        return $user;
    }

    /**
     * Draft dengan item; margin menentukan Total Calculator (Principle ON 10 + margin).
     *
     * @param  array<int, string>  $margins
     * @param  array<string, mixed>  $attributes
     */
    private function draft(array $margins = ['5'], array $attributes = []): DplProposal
    {
        $proposal = DplProposal::factory()->create([
            'outlet_id' => $this->outlet->id,
            'team_code' => 'A',
            'created_by_user_id' => $this->proposer->id,
            'date_start' => '2026-10-15',
            'date_end' => '2026-12-31',
            ...$attributes,
        ]);

        foreach ($margins as $margin) {
            DplItemTerm::factory()->for(DplItem::factory()->for($proposal, 'proposal'), 'item')->create(['margin' => $margin]);
        }

        return $proposal;
    }

    private function submit(DplProposal $proposal): TestResponse
    {
        return $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/submit");
    }

    /**
     * @param  array<int, int>|null  $itemIds
     */
    private function approve(string $role, DplProposal $proposal, ?array $itemIds = null): TestResponse
    {
        return $this->actingAs($this->approvers[$role])->postJson("/api/dpl/proposals/{$proposal->id}/approve", [
            'item_ids' => $itemIds ?? $proposal->items()->pluck('id')->all(),
        ]);
    }

    public function test_submit_routes_items_through_full_workflow_with_resolved_approvers(): void
    {
        $proposal = $this->draft(['5', '6']);

        $this->submit($proposal)->assertOk()->assertJsonPath('data.proposal_status', DplProposal::STATUS_IN_APPROVAL);

        $instance = $proposal->approvalInstances()->with('steps.assigneeUsers')->sole();
        $this->assertCount(5, $instance->steps);
        $this->assertSame(10, ApprovalItemState::query()->where('status', ApprovalItemState::STATUS_PENDING)->count());
        $rmStep = $instance->steps->first(fn ($step) => $step->workflowStep->role->name === 'RM');
        $this->assertSame([$this->approvers['RM']->id], $rmStep->assigneeUsers->pluck('id')->all());

        // Draft yang sudah diajukan tidak bisa diajukan / diubah lagi.
        $this->submit($proposal)->assertUnprocessable();
    }

    public function test_sequence_rm_then_nsm_then_gm_smd_dmd_in_any_order_and_activation(): void
    {
        $old = DplProposal::factory()->active()->create(['outlet_id' => $this->outlet->id, 'team_code' => 'A']);
        $proposal = $this->draft();
        $this->submit($proposal)->assertOk();

        $this->approve('NSM', $proposal)->assertUnprocessable();
        $this->approve('GM', $proposal)->assertUnprocessable();

        $this->approve('RM', $proposal)->assertOk()->assertJsonPath('data.proposal_status', DplProposal::STATUS_PARTIALLY_APPROVED);
        $this->approve('GM', $proposal)->assertUnprocessable();
        $this->approve('NSM', $proposal)->assertOk();

        // Setelah NSM, GM/SMD/DMD tidak saling menunggu.
        $this->approve('DMD', $proposal)->assertOk();
        $this->approve('SMD', $proposal)->assertOk();
        $this->approve('GM', $proposal)->assertOk()
            ->assertJsonPath('data.proposal_status', DplProposal::STATUS_APPROVED)
            ->assertJsonPath('data.program_status', DplProposal::PROGRAM_ACTIVE);

        $this->assertSame(DplProposal::PROGRAM_SUPERSEDED, $old->fresh()->program_status);
        $this->approve('GM', $proposal)->assertUnprocessable();
    }

    public function test_only_assigned_approver_can_act_and_any_of_multiple_approvers(): void
    {
        $secondRm = $this->approver('RM', ['region_id' => $this->region->id]);
        $otherRegionRm = $this->approver('RM', ['region_id' => SalesRegion::factory()->create()->id]);
        $proposal = $this->draft();
        $this->submit($proposal)->assertOk();
        $itemIds = $proposal->items()->pluck('id')->all();

        $this->actingAs($otherRegionRm)->postJson("/api/dpl/proposals/{$proposal->id}/approve", ['item_ids' => $itemIds])->assertNotFound();
        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/approve", ['item_ids' => $itemIds])->assertForbidden();

        $this->actingAs($secondRm)->postJson("/api/dpl/proposals/{$proposal->id}/approve", ['item_ids' => $itemIds])->assertOk();
        $this->approve('RM', $proposal)->assertUnprocessable();

        $this->actingAs($this->approvers['NSM'])->getJson("/api/dpl/proposals/{$proposal->id}/approval")
            ->assertOk()
            ->assertJsonPath('data.actionable_item_ids', $itemIds);
    }

    public function test_reject_requires_reason_and_stops_approval(): void
    {
        $proposal = $this->draft(['5', '6']);
        $this->submit($proposal)->assertOk();
        [$first, $second] = $proposal->items()->orderBy('id')->pluck('id')->all();

        $this->actingAs($this->approvers['RM'])->postJson("/api/dpl/proposals/{$proposal->id}/reject", ['item_ids' => [$first]])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->actingAs($this->approvers['RM'])->postJson("/api/dpl/proposals/{$proposal->id}/reject", ['item_ids' => [$first], 'reason' => 'Diskon terlalu besar'])
            ->assertOk()->assertJsonPath('data.proposal_status', DplProposal::STATUS_REJECTED);

        $this->approve('RM', $proposal, [$second])->assertUnprocessable();
        $this->assertSame(DplProposal::PROGRAM_INACTIVE, $proposal->fresh()->program_status);
    }

    public function test_items_over_calculator_limit_are_auto_rejected_except_clearance(): void
    {
        // Principle ON 10 + margin 25 = 35 > batas Tim A 30.
        $proposal = $this->draft(['25']);
        $this->submit($proposal)->assertOk()->assertJsonPath('data.proposal_status', DplProposal::STATUS_REJECTED);
        $this->assertSame(
            ['Total discount is more than 30%'],
            ApprovalItemState::query()->distinct()->pluck('reject_reason')->all(),
        );

        $clearance = $this->draft(['25'], ['proposal_type' => DplProposal::TYPE_CLEARANCE]);
        $this->submit($clearance)->assertOk()->assertJsonPath('data.proposal_status', DplProposal::STATUS_IN_APPROVAL);
    }

    public function test_approval_rule_shortens_route_by_total_and_outlet_province(): void
    {
        $steps = ApprovalWorkflowStep::query()->whereHas('role', fn ($query) => $query->whereIn('name', ['RM', 'NSM']))->pluck('id');
        $rule = ApprovalRule::factory()->create(['discount_upper' => '20']);
        $rule->steps()->attach($steps);
        $otherProvinceRule = ApprovalRule::factory()->create(['discount_upper' => '50', 'sort_order' => -1]);
        $otherProvinceRule->steps()->attach($steps->first());
        $otherProvinceRule->provinces()->attach(Province::factory()->create());

        // Item 15 (<= 20) -> RM + NSM; item 25 -> jalur lengkap.
        $proposal = $this->draft(['5', '15']);
        $this->submit($proposal)->assertOk();
        [$small, $large] = $proposal->items()->orderBy('id')->pluck('id')->all();

        $this->assertSame(2, ApprovalItemState::query()->where('dpl_item_id', $small)->count());
        $this->assertSame(5, ApprovalItemState::query()->where('dpl_item_id', $large)->count());

        $this->approve('RM', $proposal)->assertOk();
        $this->approve('NSM', $proposal)->assertOk();
        $this->assertSame(
            ApprovalItemState::STATUS_APPROVED,
            ApprovalItemState::query()->where('dpl_item_id', $small)->distinct()->sole('status')->status,
        );
        $this->assertSame(DplProposal::STATUS_PARTIALLY_APPROVED, $proposal->fresh()->proposal_status);
    }

    public function test_submit_fails_when_an_approver_is_missing(): void
    {
        ApproverAssignment::query()->where('user_id', $this->approvers['SMD']->id)->delete();

        $this->submit($this->draft())->assertUnprocessable()->assertJsonValidationErrors('approvers');
        $this->assertDatabaseCount('dpl.approval_instances', 0);
    }

    public function test_future_start_activates_on_date_start_and_expires_after_date_end(): void
    {
        $proposal = $this->draft(['5'], ['date_start' => '2026-11-01', 'date_end' => '2026-11-30']);
        $this->submit($proposal)->assertOk();

        foreach (['RM', 'NSM', 'GM', 'SMD', 'DMD'] as $role) {
            $this->approve($role, $proposal)->assertOk();
        }

        $this->assertSame(DplProposal::PROGRAM_INACTIVE, $proposal->fresh()->program_status);

        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:10', 'Asia/Jakarta'));
        $this->artisan('dpl:refresh-program-status')->assertSuccessful();
        $this->assertSame(DplProposal::PROGRAM_ACTIVE, $proposal->fresh()->program_status);

        $this->travelTo(CarbonImmutable::parse('2026-12-01 00:10', 'Asia/Jakarta'));
        $this->artisan('dpl:refresh-program-status')->assertSuccessful();
        $this->assertSame(DplProposal::PROGRAM_EXPIRED, $proposal->fresh()->program_status);
    }
}
