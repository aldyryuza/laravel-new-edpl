<?php

namespace Tests\Feature;

use App\Models\Dpl\ApproverAssignment;
use App\Models\Role;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Database\Seeders\ApprovalReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApproverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Role $rm;

    private SalesRegion $region;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ApprovalReferenceSeeder::class);
        $this->rm = Role::query()->where('name', 'RM')->sole();
        $this->region = SalesRegion::factory()->create();
    }

    public function test_page_requires_read_access_and_lists_only_workflow_roles(): void
    {
        Role::factory()->create(['name' => 'Bukan Approver']);

        $this->actingAs($this->userWithAccess([]))->get('/dpl/approver-assignments')->assertForbidden();

        $this->actingAs($this->userWithAccess(['approver-assignments' => ['read']]))
            ->get('/dpl/approver-assignments')
            ->assertOk()
            ->assertSee('>RM</option>', escape: false)
            ->assertDontSee('Bukan Approver');
    }

    public function test_region_scope_assignment_ignores_zone_input(): void
    {
        $user = User::factory()->create();

        $this->actingAs($this->userWithAccess(['approver-assignments' => ['create']]))
            ->postJson('/api/dpl/approver-assignments', $this->payload($user, [
                'zone_id' => SalesZone::factory()->create()->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.scope_type', ApproverAssignment::SCOPE_REGION)
            ->assertJsonPath('data.region_id', $this->region->id)
            ->assertJsonPath('data.zone_id', null);
    }

    public function test_global_scope_stores_no_region_or_zone(): void
    {
        $gm = Role::query()->where('name', 'GM')->sole();

        $this->actingAs($this->userWithAccess(['approver-assignments' => ['create']]))
            ->postJson('/api/dpl/approver-assignments', $this->payload(User::factory()->create(), [
                'role_id' => $gm->id,
                'scope_type' => ApproverAssignment::SCOPE_GLOBAL,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.region_id', null)
            ->assertJsonPath('data.zone_id', null);
    }

    public function test_scope_requires_matching_region_or_zone(): void
    {
        $actor = $this->userWithAccess(['approver-assignments' => ['create']]);
        $user = User::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/dpl/approver-assignments', $this->payload($user, ['region_id' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('region_id');

        $this->actingAs($actor)
            ->postJson('/api/dpl/approver-assignments', $this->payload($user, ['scope_type' => ApproverAssignment::SCOPE_ZONE]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('zone_id');
    }

    public function test_role_outside_workflow_and_inactive_user_are_rejected(): void
    {
        $this->actingAs($this->userWithAccess(['approver-assignments' => ['create']]))
            ->postJson('/api/dpl/approver-assignments', $this->payload(User::factory()->create(['is_active' => false]), [
                'role_id' => Role::factory()->create()->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role_id', 'user_id']);
    }

    public function test_two_different_approvers_may_share_role_and_region(): void
    {
        $actor = $this->userWithAccess(['approver-assignments' => ['create']]);

        $this->actingAs($actor)->postJson('/api/dpl/approver-assignments', $this->payload(User::factory()->create()))->assertCreated();
        $this->actingAs($actor)->postJson('/api/dpl/approver-assignments', $this->payload(User::factory()->create()))->assertCreated();

        $this->assertSame(2, ApproverAssignment::query()->where('region_id', $this->region->id)->count());
    }

    public function test_same_user_cannot_have_overlapping_active_assignment_for_same_scope(): void
    {
        $actor = $this->userWithAccess(['approver-assignments' => ['create']]);
        $user = User::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/dpl/approver-assignments', $this->payload($user, ['valid_from' => '2026-01-01', 'valid_until' => '2026-06-30']))
            ->assertCreated();

        $this->actingAs($actor)
            ->postJson('/api/dpl/approver-assignments', $this->payload($user, ['valid_from' => '2026-06-01']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->actingAs($actor)
            ->postJson('/api/dpl/approver-assignments', $this->payload($user, ['valid_from' => '2026-07-01']))
            ->assertCreated();
    }

    public function test_updating_assignment_does_not_conflict_with_itself(): void
    {
        $user = User::factory()->create();
        $assignment = ApproverAssignment::factory()->forRegion($this->region)->create(['role_id' => $this->rm->id, 'user_id' => $user->id]);

        $this->actingAs($this->userWithAccess(['approver-assignments' => ['update']]))
            ->putJson("/api/dpl/approver-assignments/{$assignment->id}", $this->payload($user, ['valid_until' => '2026-12-31']))
            ->assertOk()
            ->assertJsonPath('data.valid_until', '2026-12-31');
    }

    public function test_datatable_shows_scope_and_searches_user(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Approver']);
        ApproverAssignment::factory()->forRegion($this->region)->create(['role_id' => $this->rm->id, 'user_id' => $budi->id]);
        ApproverAssignment::factory()->create(['role_id' => $this->rm->id]);

        $this->actingAs($this->userWithAccess(['approver-assignments' => ['read']]))
            ->getJson('/api/dpl/approver-assignments/datatable?'.$this->dataTableQuery(
                ['valid_from', 'valid_until', 'is_active'],
                ['search' => 'budi', 'order' => ['valid_from', 'desc']],
            ))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.scope_type', ApproverAssignment::SCOPE_REGION)
            ->assertJsonPath('data.0.user.name', 'Budi Approver')
            ->assertJsonPath('data.0.region.id', $this->region->id);
    }

    public function test_assignment_can_be_deleted(): void
    {
        $assignment = ApproverAssignment::factory()->create(['role_id' => $this->rm->id]);

        $this->actingAs($this->userWithAccess(['approver-assignments' => ['delete']]))
            ->deleteJson("/api/dpl/approver-assignments/{$assignment->id}")
            ->assertOk();

        $this->assertModelMissing($assignment);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(User $user, array $overrides = []): array
    {
        return [
            'role_id' => $this->rm->id,
            'scope_type' => ApproverAssignment::SCOPE_REGION,
            'region_id' => $this->region->id,
            'zone_id' => null,
            'user_id' => $user->id,
            'valid_from' => null,
            'valid_until' => null,
            'is_active' => true,
            ...$overrides,
        ];
    }
}
