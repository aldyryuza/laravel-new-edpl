<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Dpl\ApprovalOverrideRequest;
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
 * UI approval DPL: tombol Ajukan & panel approval di detail, filter "Giliran Approval Saya" + flag giliran di daftar,
 * dan master Aturan Approval.
 */
class DplApprovalUiTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = ['dpl_number', 'date_offer', 'date_start', 'date_end', 'proposal_status', 'created_at'];

    private Outlet $outlet;

    private User $proposer;

    /** @var array<string, User> */
    private array $approvers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $this->seed([ApprovalReferenceSeeder::class, CommercialParameterSeeder::class]);

        $zone = SalesZone::factory()->create(['code' => 'E']);
        $region = SalesRegion::factory()->for($zone, 'zone')->create();
        $area = SalesArea::factory()->for($region, 'region')->create();
        $this->outlet = Outlet::factory()->create(['area_id' => $area->id, 'city_id' => City::factory()->create()->id]);
        $this->proposer = $this->userWithAccess(['dpl-proposals' => ['read', 'create', 'update']]);

        foreach (['RM' => ['region_id' => $region->id], 'NSM' => ['zone_id' => $zone->id], 'GM' => [], 'SMD' => [], 'DMD' => []] as $role => $scope) {
            $this->approvers[$role] = $this->userWithAccess(['dpl-proposals' => ['read', 'approve']], national: false);
            ApproverAssignment::factory()->create([
                'role_id' => Role::query()->where('name', $role)->value('id'),
                'user_id' => $this->approvers[$role]->id,
                ...$scope,
            ]);
        }
    }

    private function draft(): DplProposal
    {
        $proposal = DplProposal::factory()->create([
            'outlet_id' => $this->outlet->id,
            'team_code' => 'A',
            'created_by_user_id' => $this->proposer->id,
            'date_start' => '2026-10-15',
            'date_end' => '2026-12-31',
        ]);
        DplItemTerm::factory()->for(DplItem::factory()->for($proposal, 'proposal'), 'item')->create(['margin' => '5']);

        return $proposal;
    }

    private function submitted(): DplProposal
    {
        $proposal = $this->draft();
        $this->actingAs($this->proposer)->postJson("/api/dpl/proposals/{$proposal->id}/submit")->assertOk();

        return $proposal;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function datatable(User $user, array $filters = []): TestResponse
    {
        return $this->actingAs($user)->getJson('/api/dpl/proposals/datatable?'.$this->dataTableQuery(self::COLUMNS, $filters))->assertOk();
    }

    public function test_detail_page_has_submit_button_and_approval_panel(): void
    {
        $proposal = $this->draft();

        $this->actingAs($this->proposer)->get("/dpl/proposals/{$proposal->id}")
            ->assertOk()
            ->assertSee('id="btn-submit-proposal"', escape: false)
            ->assertSee('id="approval-card"', escape: false)
            ->assertSee('id="approval-progress-card"', escape: false)
            ->assertSee('id="approval-timeline"', escape: false)
            ->assertSee('id="approval-modal"', escape: false);
    }

    public function test_awaiting_me_filter_returns_only_proposals_with_pending_step_of_user(): void
    {
        $pending = $this->submitted();
        $rejected = $this->submitted();
        $this->draft();
        $this->actingAs($this->approvers['RM'])->postJson("/api/dpl/proposals/{$rejected->id}/reject", [
            'item_ids' => $rejected->items()->pluck('id')->all(),
            'reason' => 'Tidak sesuai',
        ])->assertOk();

        $this->datatable($this->approvers['RM'], ['awaiting_me' => 1])
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.awaiting_me', true);

        // Proposer bukan approver: tidak ada yang menunggu.
        $this->datatable($this->proposer, ['awaiting_me' => 1])->assertJsonPath('recordsFiltered', 0);
        $this->datatable($this->proposer)->assertJsonPath('recordsFiltered', 3)->assertJsonPath('data.0.awaiting_me', false);
    }

    public function test_open_reviewer_sees_rejected_dpls_with_pending_open_request_as_awaiting(): void
    {
        $proposal = $this->submitted();
        $this->actingAs($this->approvers['RM'])->postJson("/api/dpl/proposals/{$proposal->id}/reject", [
            'item_ids' => $proposal->items()->pluck('id')->all(),
            'reason' => 'Tidak sesuai',
        ])->assertOk();
        $proposal->overrideRequests()->create([
            'dpl_item_id' => $proposal->items()->value('id'),
            'requested_by_user_id' => $this->proposer->id,
            'request_reason' => 'Program RS',
            'status' => ApprovalOverrideRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);

        $this->datatable($this->approvers['GM'], ['awaiting_me' => 1])
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $proposal->id)
            ->assertJsonPath('data.0.open_requested', true)
            ->assertJsonPath('data.0.awaiting_me', true);

        // Bukan reviewer Open: tidak ikut menunggu.
        $this->datatable($this->approvers['RM'], ['awaiting_me' => 1])->assertJsonPath('recordsFiltered', 0);
    }

    public function test_awaiting_me_flag_follows_approval_sequence(): void
    {
        $proposal = $this->submitted();

        // NSM belum giliran sebelum RM approve: tidak tampil di filter, tapi tetap ada tanpa filter.
        $this->datatable($this->approvers['NSM'], ['awaiting_me' => 1])->assertJsonPath('recordsFiltered', 0);
        $this->datatable($this->approvers['NSM'])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.awaiting_me', false);

        $this->actingAs($this->approvers['RM'])->postJson("/api/dpl/proposals/{$proposal->id}/approve", [
            'item_ids' => $proposal->items()->pluck('id')->all(),
        ])->assertOk();

        $this->datatable($this->approvers['NSM'], ['awaiting_me' => 1])->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.awaiting_me', true);
        $this->datatable($this->approvers['RM'], ['awaiting_me' => 1])->assertJsonPath('recordsFiltered', 0);
    }

    public function test_approval_rules_page_requires_read_access(): void
    {
        $this->actingAs($this->userWithAccess([]))->get('/dpl/approval-rules')->assertForbidden();
        $this->actingAs($this->userWithAccess([]))->getJson('/api/dpl/approval-rules/datatable')->assertForbidden();

        $this->actingAs($this->userWithAccess(['approval-rules' => ['read']]))->get('/dpl/approval-rules')
            ->assertOk()
            ->assertSee('approval-rules.js');
    }

    public function test_approval_rule_crud_syncs_steps_and_provinces(): void
    {
        $actor = $this->userWithAccess(['approval-rules' => ['create', 'read', 'update', 'delete']]);
        $steps = ApprovalWorkflowStep::query()->orderBy('sequence')->pluck('id');
        [$jakarta, $bali] = Province::factory()->count(2)->create();
        $payload = [
            'name' => 'Diskon kecil',
            'discount_lower' => '10',
            'discount_upper' => '30',
            'step_ids' => [$steps[0], $steps[1]],
            'province_ids' => [$jakarta->id],
            'sort_order' => 1,
            'is_active' => true,
        ];

        $id = $this->actingAs($actor)->postJson('/api/dpl/approval-rules', $payload)
            ->assertCreated()
            ->assertJsonPath('data.discount_upper', '30.0000')
            ->assertJsonCount(2, 'data.steps')
            ->assertJsonPath('data.provinces.0.id', $jakarta->id)
            ->json('data.id');

        $this->actingAs($actor)->postJson('/api/dpl/approval-rules', [...$payload, 'discount_upper' => '10'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_upper');
        $this->actingAs($actor)->postJson('/api/dpl/approval-rules', [...$payload, 'step_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('step_ids');

        $this->actingAs($actor)->putJson("/api/dpl/approval-rules/{$id}", [
            ...$payload,
            'discount_lower' => '',
            'step_ids' => [$steps[2]],
            'province_ids' => [$bali->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.discount_lower', null)
            ->assertJsonPath('data.steps.0.id', $steps[2]);

        $rule = ApprovalRule::query()->findOrFail($id);
        $this->assertSame([$steps[2]], $rule->steps()->pluck('dpl.approval_workflow_steps.id')->all());
        $this->assertSame([$bali->id], $rule->provinces()->pluck('provinces.id')->all());

        $this->actingAs($actor)->getJson('/api/dpl/approval-rules/datatable?'.$this->dataTableQuery(['sort_order', 'name', 'is_active']))
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1);

        $this->actingAs($actor)->deleteJson("/api/dpl/approval-rules/{$id}")->assertOk();
        $this->assertModelMissing($rule);
    }
}
