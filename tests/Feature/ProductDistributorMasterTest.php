<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Dpl\DplItem;
use App\Models\Product;
use App\Models\SalesRegion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProductDistributorMasterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'product' => ['products', '/products'],
            'distributor' => ['distributors', '/distributors'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_product_is_created_with_volume_and_unique_code(): void
    {
        $actor = $this->userWithAccess(['products' => ['create']]);
        $payload = ['code' => 'P-500', 'name' => 'Cairan 500', 'volume' => '500', 'is_branded' => true, 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/products', $payload)->assertCreated()->assertJsonPath('data.volume', '500.0000');

        $this->actingAs($actor)->postJson('/api/products', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_product_group_must_be_a_configured_group(): void
    {
        $actor = $this->userWithAccess(['products' => ['create']]);
        $payload = ['code' => 'IV-1', 'name' => 'IV Catheter Pen 18G', 'is_branded' => false, 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/products', [...$payload, 'product_group' => 'SPUIT'])
            ->assertUnprocessable()->assertJsonValidationErrors('product_group');

        $this->actingAs($actor)->postJson('/api/products', [...$payload, 'product_group' => 'IV CAT PEN'])
            ->assertCreated()->assertJsonPath('data.product_group', 'IV CAT PEN');
    }

    public function test_product_datatable_filters_by_volume_and_branded(): void
    {
        Product::factory()->create(['code' => 'P-1', 'volume' => '500', 'is_branded' => true]);
        Product::factory()->create(['code' => 'P-2', 'volume' => '500', 'is_branded' => false]);
        Product::factory()->create(['code' => 'P-3', 'volume' => '250', 'is_branded' => true]);
        $columns = ['code', 'name', 'volume', 'is_branded', 'is_active'];

        $this->actingAs($this->userWithAccess(['products' => ['read']]))
            ->getJson('/api/products/datatable?'.$this->dataTableQuery($columns, ['volume' => '500.0000', 'is_branded' => '1']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', 'P-1');
    }

    public function test_product_used_by_dpl_cannot_be_deleted(): void
    {
        $product = DplItem::factory()->create()->product;

        $this->actingAs($this->userWithAccess(['products' => ['delete']]))
            ->deleteJson("/api/products/{$product->id}")
            ->assertUnprocessable();

        $this->assertModelExists($product);
    }

    public function test_distributor_is_saved_with_parent_and_regions_and_region_change_is_logged(): void
    {
        $parent = Distributor::factory()->create();
        [$jabar, $dki] = SalesRegion::factory()->count(2)->create();
        $actor = $this->userWithAccess(['distributors' => ['create', 'update']]);

        $id = $this->actingAs($actor)
            ->postJson('/api/distributors', [
                'code' => 'D-BDG', 'name' => 'Cabang Bandung', 'is_branch' => true, 'parent_distributor_id' => $parent->id,
                'region_ids' => [$jabar->id], 'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent.id', $parent->id)
            ->assertJsonPath('data.regions.0.id', $jabar->id)
            ->json('data.id');

        $this->actingAs($actor)
            ->putJson("/api/distributors/{$id}", [
                'code' => 'D-BDG', 'name' => 'Cabang Bandung', 'is_branch' => true, 'parent_distributor_id' => $parent->id,
                'region_ids' => [$jabar->id, $dki->id], 'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonCount(2, 'data.regions');

        $this->assertSame(2, Activity::query()->where('event', 'regions_updated')->count());
    }

    public function test_distributor_saves_contact_and_meeting_location(): void
    {
        $this->actingAs($this->userWithAccess(['distributors' => ['create']]))
            ->postJson('/api/distributors', [
                'code' => 'D-TTE', 'name' => 'Distributor Ternate', 'is_branch' => false, 'is_active' => true,
                'address' => 'Jl. Raya Mangga Dua', 'phone' => '0811-4329-539', 'email' => 'cs@contoh.test', 'npwp' => '01.234.567.8-901.000',
                'latitude' => '0.7713696', 'longitude' => '127.3762587', 'meeting_radius_meters' => 20,
            ])
            ->assertCreated()
            ->assertJsonPath('data.meeting_radius_meters', 20)
            ->assertJsonPath('data.latitude', '0.7713696');

        $this->actingAs($this->userWithAccess(['distributors' => ['create']]))
            ->postJson('/api/distributors', [
                'code' => 'D-X', 'name' => 'X', 'is_branch' => false, 'is_active' => true,
                'email' => 'bukan-email', 'latitude' => '1.5', 'meeting_radius_meters' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'longitude', 'meeting_radius_meters']);
    }

    public function test_parent_options_search_includes_newly_created_distributor_and_excludes_self(): void
    {
        $actor = $this->userWithAccess(['distributors' => ['create', 'read']]);

        $this->actingAs($actor)
            ->postJson('/api/distributors', ['code' => 'SDLSURABAYA', 'name' => 'SDL Surabaya', 'is_branch' => false, 'is_active' => true])
            ->assertCreated();
        $headId = Distributor::query()->where('code', 'SDLSURABAYA')->value('id');

        $this->actingAs($actor)
            ->getJson('/api/distributors/options?q=sdlsura')
            ->assertOk()
            ->assertJsonPath('data.0.id', $headId)
            ->assertJsonPath('data.0.text', 'SDL Surabaya (SDLSURABAYA)');

        $this->actingAs($actor)
            ->getJson("/api/distributors/options?q=sdl&exclude_id={$headId}")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($actor)
            ->postJson('/api/distributors', [
                'code' => 'SDLSEMARANG', 'name' => 'SDL Semarang', 'is_branch' => true, 'parent_distributor_id' => $headId, 'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent.code', 'SDLSURABAYA');

        $this->actingAs($this->userWithAccess([]))->getJson('/api/distributors/options')->assertForbidden();
    }

    public function test_distributor_cannot_use_itself_or_its_branch_as_parent(): void
    {
        $head = Distributor::factory()->create();
        $branch = Distributor::factory()->create(['parent_distributor_id' => $head->id, 'is_branch' => true]);
        $actor = $this->userWithAccess(['distributors' => ['update']]);
        $payload = fn (int $parentId) => ['code' => $head->code, 'name' => $head->name, 'is_branch' => false, 'parent_distributor_id' => $parentId, 'is_active' => true];

        $this->actingAs($actor)->putJson("/api/distributors/{$head->id}", $payload($head->id))
            ->assertUnprocessable()->assertJsonValidationErrors('parent_distributor_id');

        $this->actingAs($actor)->putJson("/api/distributors/{$head->id}", $payload($branch->id))
            ->assertUnprocessable()->assertJsonValidationErrors('parent_distributor_id');
    }

    public function test_distributor_datatable_filters_by_region(): void
    {
        $region = SalesRegion::factory()->create();
        $inRegion = Distributor::factory()->create();
        $inRegion->regions()->attach($region);
        Distributor::factory()->create();

        $this->actingAs($this->userWithAccess(['distributors' => ['read']]))
            ->getJson('/api/distributors/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active'], ['region_id' => $region->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', $inRegion->code)
            ->assertJsonPath('data.0.regions.0.code', $region->code);
    }

    public function test_distributor_with_branches_cannot_be_deleted_but_plain_one_can_with_its_regions(): void
    {
        $actor = $this->userWithAccess(['distributors' => ['delete']]);
        $head = Distributor::factory()->create();
        Distributor::factory()->create(['parent_distributor_id' => $head->id]);
        $plain = Distributor::factory()->create();
        $plain->regions()->attach(SalesRegion::factory()->create());

        $this->actingAs($actor)->deleteJson("/api/distributors/{$head->id}")->assertUnprocessable();
        $this->assertModelExists($head);

        $this->actingAs($actor)->deleteJson("/api/distributors/{$plain->id}")->assertOk();
        $this->assertModelMissing($plain);
    }
}
