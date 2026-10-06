<?php

namespace Tests\Feature;

use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
use App\Models\OutletCategory;
use App\Models\OutletGroup;
use App\Models\OutletGroupMember;
use App\Models\SalesArea;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Master outlet: klasifikasi, group RS (satu group aktif per outlet), dan outlet.
 */
class OutletMasterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'outlet' => ['outlets', '/outlets'],
            'group' => ['outlet-groups', '/outlet-groups'],
            'klasifikasi' => ['outlet-classifications', '/outlet-classifications'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_outlet_page_loads_leaflet_with_configured_tile_server(): void
    {
        config(['services.map.tile_url' => 'https://tiles.internal.test/{z}/{x}/{y}.png']);

        $this->actingAs($this->userWithAccess(['outlets' => ['read']]))
            ->get('/outlets')
            ->assertOk()
            ->assertSee('vendor/libs/leaflet/leaflet.js', escape: false)
            ->assertSee('id="outlet-map"', escape: false)
            ->assertSee('data-tile-url="https://tiles.internal.test/{z}/{x}/{y}.png"', escape: false);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $actor = $this->userWithAccess(['outlet-classifications' => ['create', 'delete']]);

        $this->actingAs($actor)
            ->postJson('/api/outlet-categories', ['code' => 'RUMAH_SAKIT', 'name' => 'Rumah Sakit', 'is_active' => true])
            ->assertCreated();

        $category = OutletCategory::query()->where('code', 'RUMAH_SAKIT')->sole();
        Outlet::factory()->create(['outlet_category_id' => $category->id]);

        $this->actingAs($actor)->deleteJson("/api/outlet-categories/{$category->id}")->assertUnprocessable();
        $this->assertModelExists($category);
    }

    public function test_group_code_is_optional_but_unique_when_filled(): void
    {
        $actor = $this->userWithAccess(['outlet-groups' => ['create']]);

        $this->actingAs($actor)->postJson('/api/outlet-groups', ['name' => 'Hermina', 'is_active' => true])->assertCreated();
        $this->actingAs($actor)->postJson('/api/outlet-groups', ['name' => 'Siloam', 'is_active' => true])->assertCreated();
        $this->actingAs($actor)->postJson('/api/outlet-groups', ['code' => 'MAYAPADA', 'name' => 'Mayapada', 'is_active' => true])->assertCreated();

        $this->actingAs($actor)
            ->postJson('/api/outlet-groups', ['code' => 'MAYAPADA', 'name' => 'Lain', 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_outlet_is_created_with_area_and_group(): void
    {
        $area = SalesArea::factory()->create();
        $group = OutletGroup::factory()->create();

        $this->actingAs($this->userWithAccess(['outlets' => ['create']]))
            ->postJson('/api/outlets', [
                'code' => 'OUT-001',
                'name' => 'RS Contoh',
                'area_id' => $area->id,
                'outlet_group_id' => $group->id,
                'bed_count' => 120,
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.area.region.id', $area->region_id)
            ->assertJsonPath('data.active_group_membership.outlet_group.id', $group->id);
    }

    public function test_changing_group_closes_old_membership_and_keeps_history(): void
    {
        $this->freezeTime();
        $outlet = Outlet::factory()->create();
        [$hermina, $siloam] = OutletGroup::factory()->count(2)->create();
        $actor = $this->userWithAccess(['outlets' => ['update']]);
        $payload = fn (?int $groupId) => [
            'code' => $outlet->code, 'name' => $outlet->name, 'outlet_group_id' => $groupId, 'is_active' => true,
        ];

        $this->actingAs($actor)->putJson("/api/outlets/{$outlet->id}", $payload($hermina->id))->assertOk();
        $this->actingAs($actor)->putJson("/api/outlets/{$outlet->id}", $payload($hermina->id))->assertOk();
        $this->assertSame(1, $outlet->groupMemberships()->count(), 'Group sama tidak membuat keanggotaan baru.');

        $this->actingAs($actor)->putJson("/api/outlets/{$outlet->id}", $payload($siloam->id))->assertOk();

        $memberships = $outlet->groupMemberships()->orderBy('id')->get();
        $this->assertCount(2, $memberships);
        $this->assertSame(now()->toDateString(), $memberships[0]->valid_until->toDateString());
        $this->assertNull($memberships[1]->valid_until);
        $this->assertSame($siloam->id, $outlet->activeGroupMembership()->first()->outlet_group_id);

        $this->actingAs($actor)->putJson("/api/outlets/{$outlet->id}", $payload(null))->assertOk();
        $this->assertNull($outlet->activeGroupMembership()->first());
    }

    public function test_database_allows_only_one_open_membership_per_outlet(): void
    {
        $member = OutletGroupMember::factory()->create();

        $this->expectException(QueryException::class);

        OutletGroupMember::factory()->create(['outlet_id' => $member->outlet_id]);
    }

    public function test_datatable_filters_by_active_group_and_region(): void
    {
        $group = OutletGroup::factory()->create();
        $inGroup = OutletGroupMember::factory()->for($group, 'outletGroup')->create()->outlet;
        OutletGroupMember::factory()->for($group, 'outletGroup')->create(['valid_from' => '2025-01-01', 'valid_until' => '2025-12-31']);
        Outlet::factory()->create();
        $actor = $this->userWithAccess(['outlets' => ['read']]);
        $columns = ['code', 'name', 'bed_count', 'is_active'];

        $this->actingAs($actor)
            ->getJson('/api/outlets/datatable?'.$this->dataTableQuery($columns, ['outlet_group_id' => $group->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', $inGroup->code)
            ->assertJsonPath('data.0.active_group_membership.outlet_group.id', $group->id);

        $this->actingAs($actor)
            ->getJson('/api/outlets/datatable?'.$this->dataTableQuery($columns, ['region_id' => $inGroup->area->region_id]))
            ->assertJsonPath('recordsFiltered', 1);
    }

    public function test_outlet_without_business_data_is_deleted_with_its_membership_history(): void
    {
        $outlet = OutletGroupMember::factory()->create()->outlet;

        $this->actingAs($this->userWithAccess(['outlets' => ['delete']]))
            ->deleteJson("/api/outlets/{$outlet->id}")
            ->assertOk();

        $this->assertModelMissing($outlet);
        $this->assertSame(0, OutletGroupMember::query()->count());
    }

    public function test_outlet_used_by_dpl_cannot_be_deleted_and_history_is_kept(): void
    {
        $outlet = OutletGroupMember::factory()->create()->outlet;
        DplProposal::factory()->for($outlet)->create();

        $this->actingAs($this->userWithAccess(['outlets' => ['delete']]))
            ->deleteJson("/api/outlets/{$outlet->id}")
            ->assertUnprocessable();

        $this->assertModelExists($outlet);
        $this->assertSame(1, $outlet->groupMemberships()->count());
    }
}
