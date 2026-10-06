<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Master organisasi zona -> region -> area.
 */
class OrganizationMasterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'zona' => ['sales-zones', '/sales-zones'],
            'region' => ['sales-regions', '/sales-regions'],
            'area' => ['sales-areas', '/sales-areas'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_zone_is_created_with_unique_code_and_requires_create_access(): void
    {
        $payload = ['code' => 'Z-JAWA', 'name' => 'Jawa', 'is_active' => true];

        $this->actingAs($this->userWithAccess(['sales-zones' => ['read']]))
            ->postJson('/api/sales-zones', $payload)
            ->assertForbidden();

        $actor = $this->userWithAccess(['sales-zones' => ['create']]);

        $this->actingAs($actor)->postJson('/api/sales-zones', $payload)->assertCreated()->assertJsonPath('data.code', 'Z-JAWA');

        $this->actingAs($actor)
            ->postJson('/api/sales-zones', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_zone_can_be_deactivated(): void
    {
        $zone = SalesZone::factory()->create();

        $this->actingAs($this->userWithAccess(['sales-zones' => ['update']]))
            ->putJson("/api/sales-zones/{$zone->id}", ['code' => $zone->code, 'name' => $zone->name, 'is_active' => false])
            ->assertOk();

        $this->assertFalse($zone->fresh()->is_active);
    }

    public function test_zone_datatable_lists_region_count_and_searches(): void
    {
        $zone = SalesZone::factory()->create(['code' => 'Z-SUM', 'name' => 'Sumatera']);
        SalesRegion::factory()->count(2)->for($zone, 'zone')->create();
        SalesZone::factory()->create(['name' => 'Kalimantan']);

        $this->actingAs($this->userWithAccess(['sales-zones' => ['read']]))
            ->getJson('/api/sales-zones/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active'], ['search' => 'sumatera']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', 'Z-SUM')
            ->assertJsonPath('data.0.regions_count', 2);
    }

    public function test_zone_in_use_cannot_be_deleted_but_unused_zone_can(): void
    {
        $actor = $this->userWithAccess(['sales-zones' => ['delete']]);
        $used = SalesRegion::factory()->create()->zone;
        $unused = SalesZone::factory()->create();

        $this->actingAs($actor)->deleteJson("/api/sales-zones/{$used->id}")->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertModelExists($used);

        $this->actingAs($actor)->deleteJson("/api/sales-zones/{$unused->id}")->assertOk();
        $this->assertModelMissing($unused);
    }

    public function test_region_requires_existing_zone(): void
    {
        $actor = $this->userWithAccess(['sales-regions' => ['create']]);
        $zone = SalesZone::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/sales-regions', ['code' => 'R-1', 'name' => 'Region 1', 'zone_id' => 999999, 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('zone_id');

        $this->actingAs($actor)
            ->postJson('/api/sales-regions', ['code' => 'R-1', 'name' => 'Region 1', 'zone_id' => $zone->id, 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('data.zone.id', $zone->id);
    }

    public function test_region_datatable_filters_by_zone_and_searches_zone_name(): void
    {
        $jawa = SalesZone::factory()->create(['name' => 'Jawa']);
        $sumatera = SalesZone::factory()->create(['name' => 'Sumatera']);
        SalesRegion::factory()->for($jawa, 'zone')->create(['name' => 'Jabar']);
        SalesRegion::factory()->for($sumatera, 'zone')->create(['name' => 'Sumut']);
        $actor = $this->userWithAccess(['sales-regions' => ['read']]);
        $columns = ['code', 'name', 'is_active'];

        $this->actingAs($actor)
            ->getJson('/api/sales-regions/datatable?'.$this->dataTableQuery($columns, ['zone_id' => $jawa->id]))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Jabar')
            ->assertJsonPath('data.0.zone.name', 'Jawa');

        $this->actingAs($actor)
            ->getJson('/api/sales-regions/datatable?'.$this->dataTableQuery($columns, ['search' => 'sumatera']))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Sumut');
    }

    public function test_area_is_created_and_filtered_by_region(): void
    {
        $actor = $this->userWithAccess(['sales-areas' => ['create', 'read']]);
        $region = SalesRegion::factory()->create();
        SalesArea::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/sales-areas', ['code' => 'A-1', 'name' => 'Bandung', 'region_id' => $region->id, 'is_active' => true])
            ->assertCreated();

        $this->actingAs($actor)
            ->getJson('/api/sales-areas/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active'], ['region_id' => $region->id]))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.name', 'Bandung')
            ->assertJsonPath('data.0.region.zone.id', $region->zone_id);
    }

    public function test_area_used_by_outlet_cannot_be_deleted(): void
    {
        $outlet = Outlet::factory()->create();

        $this->actingAs($this->userWithAccess(['sales-areas' => ['delete']]))
            ->deleteJson("/api/sales-areas/{$outlet->area_id}")
            ->assertUnprocessable();

        $this->assertModelExists($outlet->area);
    }
}
