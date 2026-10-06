<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Dpl\OutletPriceRegion;
use App\Models\Dpl\PriceRegion;
use App\Models\Dpl\ProductPrice;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductPriceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Modul Komersial DPL: region harga, region harga outlet/distributor, harga product.
 */
class CommercialPricingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'region harga' => ['price-regions', '/dpl/price-regions'],
            'region harga outlet' => ['outlet-price-regions', '/dpl/outlet-price-regions'],
            'region harga distributor' => ['distributor-price-regions', '/dpl/distributor-price-regions'],
            'harga product' => ['product-prices', '/dpl/product-prices'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_price_region_in_use_by_product_price_cannot_be_deleted(): void
    {
        $actor = $this->userWithAccess(['price-regions' => ['create', 'delete', 'read']]);

        $this->actingAs($actor)
            ->postJson('/api/dpl/price-regions', ['code' => 'PR-1', 'name' => 'Region 1', 'is_active' => true])
            ->assertCreated();

        $region = PriceRegion::query()->where('code', 'PR-1')->sole();
        ProductPrice::factory()->for($region)->create();
        OutletPriceRegion::factory()->for($region)->create();

        $this->actingAs($actor)
            ->getJson('/api/dpl/price-regions/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active']))
            ->assertJsonPath('data.0.outlets_count', 1)
            ->assertJsonPath('data.0.product_prices_count', 1);

        $this->actingAs($actor)->deleteJson("/api/dpl/price-regions/{$region->id}")->assertUnprocessable();
        $this->assertModelExists($region);
    }

    public function test_outlet_price_region_can_be_assigned_changed_and_removed(): void
    {
        $outlet = Outlet::factory()->create();
        [$first, $second] = PriceRegion::factory()->count(2)->create();
        $actor = $this->userWithAccess(['outlet-price-regions' => ['read', 'update']]);

        $this->actingAs($actor)->putJson("/api/dpl/outlet-price-regions/{$outlet->id}", ['price_region_id' => $first->id])->assertOk();
        $this->actingAs($actor)->putJson("/api/dpl/outlet-price-regions/{$outlet->id}", ['price_region_id' => $second->id])->assertOk();

        $this->assertSame($second->id, OutletPriceRegion::query()->where('outlet_id', $outlet->id)->sole()->price_region_id);

        $this->actingAs($actor)->putJson("/api/dpl/outlet-price-regions/{$outlet->id}", ['price_region_id' => null])->assertOk();
        $this->assertSame(0, OutletPriceRegion::query()->count());
    }

    public function test_mapping_update_requires_update_access(): void
    {
        $outlet = Outlet::factory()->create();

        $this->actingAs($this->userWithAccess(['outlet-price-regions' => ['read']]))
            ->putJson("/api/dpl/outlet-price-regions/{$outlet->id}", ['price_region_id' => PriceRegion::factory()->create()->id])
            ->assertForbidden();
    }

    public function test_mapping_datatable_filters_unassigned_and_by_region(): void
    {
        $region = PriceRegion::factory()->create();
        $mapped = OutletPriceRegion::factory()->for($region)->create()->outlet;
        $unmapped = Outlet::factory()->create();
        $actor = $this->userWithAccess(['outlet-price-regions' => ['read']]);
        $columns = ['outlets.code', 'outlets.name'];

        $this->actingAs($actor)
            ->getJson('/api/dpl/outlet-price-regions/datatable?'.$this->dataTableQuery($columns, ['price_region_id' => 'none']))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', $unmapped->code);

        $this->actingAs($actor)
            ->getJson('/api/dpl/outlet-price-regions/datatable?'.$this->dataTableQuery($columns, ['price_region_id' => $region->id, 'order' => ['outlets.name', 'asc']]))
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', $mapped->code)
            ->assertJsonPath('data.0.price_region_code', $region->code);
    }

    public function test_distributor_price_region_import_assigns_and_removes(): void
    {
        $region = PriceRegion::factory()->create(['code' => 'PR-5']);
        $distributor = Distributor::factory()->create(['code' => 'MJMTERNATE']);
        $actor = $this->userWithAccess(['distributor-price-regions' => ['update']]);

        $this->excelPost($actor, '/api/dpl/distributor-price-regions/import', ['kode_distributor', 'kode_region_harga'], [
            ['MJMTERNATE', 'PR-5'],
        ])->assertOk()->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('dpl.distributor_price_regions', ['distributor_id' => $distributor->id, 'price_region_id' => $region->id]);

        $this->excelPost($actor, '/api/dpl/distributor-price-regions/import', ['kode_distributor', 'kode_region_harga'], [
            ['MJMTERNATE', ''],
            ['D-TIDAK-ADA', 'PR-5'],
        ])->assertUnprocessable()->assertJsonPath('errors.file.0', 'Baris 3: kode_distributor D-TIDAK-ADA tidak ditemukan.');

        $this->excelPost($actor, '/api/dpl/distributor-price-regions/import', ['kode_distributor', 'kode_region_harga'], [
            ['MJMTERNATE', ''],
        ])->assertOk()->assertJsonPath('data.updated', 1);

        $this->assertDatabaseMissing('dpl.distributor_price_regions', ['distributor_id' => $distributor->id]);
    }

    public function test_product_price_validation_keeps_numeric_precision(): void
    {
        $actor = $this->userWithAccess(['product-prices' => ['create']]);
        $payload = [
            'product_id' => Product::factory()->create()->id,
            'price_region_id' => PriceRegion::factory()->create()->id,
            'price' => '12500.1234',
            'valid_from' => '2026-01-01',
            'valid_until' => '2026-12-31',
            'is_active' => true,
        ];

        $this->actingAs($actor)->postJson('/api/dpl/product-prices', $payload)
            ->assertCreated()
            ->assertJsonPath('data.price', '12500.1234');

        $this->actingAs($actor)->postJson('/api/dpl/product-prices', [...$payload, 'price' => '1.12345', 'valid_until' => '2025-12-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['price', 'valid_until']);
    }

    public function test_product_price_datatable_filters_prices_effective_today(): void
    {
        $this->freezeTime();
        $current = ProductPrice::factory()->create(['valid_from' => now()->subMonth()->toDateString(), 'valid_until' => null]);
        ProductPrice::factory()->create(['valid_from' => now()->subYear()->toDateString(), 'valid_until' => now()->subMonth()->toDateString()]);
        ProductPrice::factory()->create(['valid_from' => now()->addMonth()->toDateString()]);

        $this->actingAs($this->userWithAccess(['product-prices' => ['read']]))
            ->getJson('/api/dpl/product-prices/datatable?'.$this->dataTableQuery(['price', 'valid_from'], ['effective_today' => 1]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.id', $current->id);
    }

    public function test_product_price_import_upserts_per_start_date_and_keeps_history(): void
    {
        $product = Product::factory()->create(['code' => 'P-500']);
        $region = PriceRegion::factory()->create(['code' => 'PR-1']);
        $actor = $this->userWithAccess(['product-prices' => ['create', 'update']]);

        $this->excelPost($actor, '/api/dpl/product-prices/import', ProductPriceImportService::COLUMNS, [
            ['P-500', 'PR-1', 12500.5, '', '2026-01-01', '2026-06-30', ''],
        ])->assertOk()->assertJsonPath('data.created', 1);

        // Tanggal sama -> diperbarui; tanggal beda (sel tanggal Excel) -> baris baru.
        $this->excelPost($actor, '/api/dpl/product-prices/import', ProductPriceImportService::COLUMNS, [
            ['P-500', 'PR-1', '13000', '15000', '2026-01-01', '2026-06-30', ''],
            ['P-500', 'PR-1', '14000', '', ExcelDate::PHPToExcel(new \DateTime('2026-07-01')), '', ''],
        ])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.created', 1);

        $prices = ProductPrice::query()->where('product_id', $product->id)->where('price_region_id', $region->id)->orderBy('valid_from')->get();
        $this->assertCount(2, $prices);
        $this->assertSame('13000.0000', $prices[0]->price);
        $this->assertSame('15000.0000', $prices[0]->regular_price);
        $this->assertSame('2026-07-01', $prices[1]->valid_from->toDateString());
    }

    public function test_product_price_import_rejects_invalid_rows(): void
    {
        $error = $this->excelPost($this->userWithAccess(['product-prices' => ['create', 'update']]), '/api/dpl/product-prices/import',
            ProductPriceImportService::COLUMNS, [['P-X', 'PR-X', 'abc', '', '01/02/2026', '', '']])
            ->assertUnprocessable()
            ->json('errors.file.0');

        foreach (['kode_product P-X tidak ditemukan', 'kode_region_harga PR-X tidak ditemukan', 'harga harus angka', 'berlaku_mulai harus format YYYY-MM-DD'] as $expected) {
            $this->assertStringContainsString($expected, $error);
        }
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function excelPost(User $user, string $url, array $headings, array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'commercial').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($user)->post($url, ['file' => new UploadedFile($path, 'import.xlsx', null, null, true)], ['Accept' => 'application/json']);
    }
}
