<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Outlet;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\Team;
use App\Models\User;
use App\Models\UserOrgAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Wilayah user (Nasional/zona/region/area) membatasi outlet & distributor yang terlihat.
 */
class UserRegionScopeTest extends TestCase
{
    use RefreshDatabase;

    private const OUTLET_COLUMNS = ['code', 'name', 'bed_count', 'is_active'];

    private SalesZone $zoneEast;

    private SalesRegion $regionJatim;

    private SalesArea $areaSurabaya;

    private SalesArea $areaMalang;

    private SalesArea $areaBali;

    private SalesArea $areaJakarta;

    protected function setUp(): void
    {
        parent::setUp();

        // Zona Timur: Jatim (Surabaya, Malang) + Bali (Denpasar). Zona Barat: DKI (Jakarta).
        $this->zoneEast = SalesZone::factory()->create();
        $this->regionJatim = SalesRegion::factory()->for($this->zoneEast, 'zone')->create();
        $this->areaSurabaya = SalesArea::factory()->for($this->regionJatim, 'region')->create();
        $this->areaMalang = SalesArea::factory()->for($this->regionJatim, 'region')->create();
        $this->areaBali = SalesArea::factory()->for(SalesRegion::factory()->for($this->zoneEast, 'zone'), 'region')->create();
        $this->areaJakarta = SalesArea::factory()->create();

        foreach ([$this->areaSurabaya, $this->areaMalang, $this->areaBali, $this->areaJakarta] as $area) {
            Outlet::factory()->create(['area_id' => $area->id]);
        }
    }

    private function outletReader(?callable $assign = null): User
    {
        $user = $this->userWithAccess(['outlets' => ['read', 'create', 'update', 'delete'], 'distributors' => ['read', 'create', 'update', 'delete']], national: false);

        if ($assign) {
            $assign(UserOrgAssignment::factory()->for($user));
        }

        return $user;
    }

    private function visibleOutletCount(User $user): int
    {
        return $this->actingAs($user)
            ->getJson('/api/outlets/datatable?'.$this->dataTableQuery(self::OUTLET_COLUMNS))
            ->assertOk()
            ->json('recordsFiltered');
    }

    public function test_outlets_are_limited_to_assigned_scope(): void
    {
        $this->assertSame(4, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->national()->create())));
        $this->assertSame(3, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->forZone($this->zoneEast)->create())));
        $this->assertSame(2, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->forRegion($this->regionJatim)->create())));
        $this->assertSame(1, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->forArea($this->areaSurabaya)->create())));

        // Beberapa penugasan digabung.
        $this->assertSame(2, $this->visibleOutletCount($this->outletReader(function ($factory): void {
            $factory->forArea($this->areaSurabaya)->create();
            $factory->forArea($this->areaJakarta)->create();
        })));
    }

    public function test_user_without_effective_assignment_sees_no_outlets(): void
    {
        $this->assertSame(0, $this->visibleOutletCount($this->outletReader()));
        $this->assertSame(0, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->national()->create(['is_active' => false]))));
        $this->assertSame(0, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->national()->create([
            'valid_from' => now()->subMonth()->toDateString(), 'valid_until' => now()->subDay()->toDateString(),
        ]))));
        $this->assertSame(0, $this->visibleOutletCount($this->outletReader(fn ($factory) => $factory->national()->create([
            'valid_from' => now()->addDay()->toDateString(),
        ]))));
    }

    public function test_outlet_outside_scope_is_not_found(): void
    {
        $user = $this->outletReader(fn ($factory) => $factory->forArea($this->areaSurabaya)->create());
        $foreign = Outlet::query()->where('area_id', $this->areaJakarta->id)->sole();
        $own = Outlet::query()->where('area_id', $this->areaSurabaya->id)->sole();

        $this->actingAs($user)->getJson("/api/outlets/{$own->id}")->assertOk();
        $this->actingAs($user)->getJson("/api/outlets/{$foreign->id}")->assertNotFound();
        $this->actingAs($user)->putJson("/api/outlets/{$foreign->id}", ['code' => $foreign->code, 'name' => 'X', 'area_id' => $this->areaSurabaya->id, 'is_active' => true])
            ->assertNotFound();
        $this->actingAs($user)->deleteJson("/api/outlets/{$foreign->id}")->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_non_national_user_must_create_outlet_inside_scope(): void
    {
        $user = $this->outletReader(fn ($factory) => $factory->forRegion($this->regionJatim)->create());
        $payload = ['code' => 'OUT-NEW', 'name' => 'RS Baru', 'is_active' => true];

        $this->actingAs($user)->postJson('/api/outlets', $payload)->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->actingAs($user)->postJson('/api/outlets', [...$payload, 'area_id' => $this->areaJakarta->id])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');
        $this->actingAs($user)->postJson('/api/outlets', [...$payload, 'area_id' => $this->areaMalang->id])->assertCreated();
    }

    public function test_distributors_are_limited_by_region_including_region_of_assigned_area(): void
    {
        $jatim = Distributor::factory()->create();
        $jatim->regions()->attach($this->regionJatim);
        $jakarta = Distributor::factory()->create();
        $jakarta->regions()->attach($this->areaJakarta->region_id);

        $user = $this->outletReader(fn ($factory) => $factory->forArea($this->areaSurabaya)->create());

        $this->actingAs($user)
            ->getJson('/api/distributors/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', $jatim->code);

        $this->actingAs($user)->getJson("/api/distributors/{$jakarta->id}")->assertNotFound();

        $this->actingAs($this->outletReader())
            ->getJson('/api/distributors/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active']))
            ->assertJsonPath('recordsFiltered', 0);
    }

    public function test_non_national_distributor_update_keeps_regions_outside_scope(): void
    {
        $jakartaRegionId = $this->areaJakarta->region_id;
        $distributor = Distributor::factory()->create();
        $distributor->regions()->attach([$this->regionJatim->id, $jakartaRegionId]);
        $user = $this->outletReader(fn ($factory) => $factory->forRegion($this->regionJatim)->create());
        $payload = ['code' => $distributor->code, 'name' => 'Nama Baru', 'is_branch' => false, 'is_active' => true];

        $this->actingAs($user)->putJson("/api/distributors/{$distributor->id}", [...$payload, 'region_ids' => [$jakartaRegionId]])
            ->assertUnprocessable()->assertJsonValidationErrors('region_ids.0');

        $this->actingAs($user)->putJson("/api/distributors/{$distributor->id}", [...$payload, 'region_ids' => [$this->regionJatim->id]])
            ->assertOk();

        $this->assertEqualsCanonicalizing([$this->regionJatim->id, $jakartaRegionId], $distributor->regions()->pluck('sales_regions.id')->all());
    }

    public function test_import_is_only_for_national_users(): void
    {
        $user = $this->outletReader(fn ($factory) => $factory->forRegion($this->regionJatim)->create());
        $file = UploadedFile::fake()->create('import.xlsx', 1, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)->postJson('/api/outlets/import', ['file' => $file])->assertForbidden();
        $this->actingAs($user)->postJson('/api/distributors/import', ['file' => $file])->assertForbidden();
        $this->actingAs($user)->get('/outlets')->assertOk()->assertDontSee('excel-import-modal', escape: false);
    }

    public function test_assignment_is_created_per_scope_and_overlapping_duplicate_is_rejected(): void
    {
        $admin = $this->userWithAccess(['user-assignments' => ['read', 'create', 'update']]);
        $target = User::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($admin)->postJson('/api/user-assignments', [
            'user_id' => $target->id, 'scope_type' => 'region', 'region_id' => $this->regionJatim->id,
            'area_id' => $this->areaJakarta->id, 'team_id' => $team->id, 'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.scope_type', 'region')->assertJsonPath('data.area_id', null);

        $this->actingAs($admin)->postJson('/api/user-assignments', [
            'user_id' => $target->id, 'scope_type' => 'region', 'region_id' => $this->regionJatim->id, 'team_id' => $team->id, 'is_active' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->actingAs($admin)->postJson('/api/user-assignments', ['user_id' => $target->id, 'scope_type' => 'area', 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('area_id');

        $this->actingAs($admin)->postJson('/api/user-assignments', ['user_id' => $target->id, 'scope_type' => 'national', 'is_active' => true])
            ->assertCreated()->assertJsonPath('data.scope_type', 'national');

        $this->assertTrue($target->regionScope()->isNational());
        $this->actingAs($admin)->get('/user-assignments')->assertOk()->assertSee('user-assignments.js');
    }

    public function test_database_rejects_assignment_with_more_than_one_scope(): void
    {
        $this->expectException(QueryException::class);

        UserOrgAssignment::factory()->create(['region_id' => $this->regionJatim->id, 'area_id' => $this->areaSurabaya->id]);
    }

    public function test_team_crud_and_team_in_use_cannot_be_deleted(): void
    {
        $admin = $this->userWithAccess(['teams' => ['read', 'create', 'delete']]);

        $this->actingAs($admin)->postJson('/api/teams', ['code' => 'ETH', 'name' => 'Ethical', 'is_active' => true])->assertCreated();
        $this->actingAs($admin)->postJson('/api/teams', ['code' => 'ETH', 'name' => 'Lain', 'is_active' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $team = Team::query()->where('code', 'ETH')->sole();
        UserOrgAssignment::factory()->national()->create(['team_id' => $team->id]);

        $this->actingAs($admin)->deleteJson("/api/teams/{$team->id}")->assertUnprocessable();
        $this->assertModelExists($team);
        $this->actingAs($admin)->get('/teams')->assertOk()->assertSee('teams.js');
    }
}
