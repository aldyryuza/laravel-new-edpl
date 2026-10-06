<?php

namespace Tests\Feature;

use App\Models\Dpl\ProductPrice;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductPriceImportService;
use DateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Harga product: satu harga per product (tidak dibedakan per region) dengan periode berlaku.
 */
class CommercialPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_read_access(): void
    {
        $this->actingAs($this->userWithAccess([]))->get('/dpl/product-prices')->assertForbidden();

        $this->actingAs($this->userWithAccess(['product-prices' => ['read']]))->get('/dpl/product-prices')->assertOk();
    }

    public function test_price_region_tables_are_removed(): void
    {
        $this->assertFalse(Schema::hasTable('dpl.price_regions'));
        $this->assertFalse(Schema::hasTable('dpl.outlet_price_regions'));
        $this->assertFalse(Schema::hasTable('dpl.distributor_price_regions'));
        $this->assertFalse(Schema::hasColumn('dpl.product_prices', 'price_region_id'));
        $this->assertFalse(Schema::hasColumn('dpl.product_price_overrides', 'price_region_id'));
    }

    public function test_product_price_validation_keeps_numeric_precision(): void
    {
        $actor = $this->userWithAccess(['product-prices' => ['create']]);
        $payload = [
            'product_id' => Product::factory()->create()->id,
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
        $actor = $this->userWithAccess(['product-prices' => ['create', 'update']]);

        $this->excelPost($actor, [
            ['P-500', 12500.5, '', '2026-01-01', '2026-06-30', ''],
        ])->assertOk()->assertJsonPath('data.created', 1);

        // Tanggal sama -> diperbarui; tanggal beda (sel tanggal Excel) -> baris baru.
        $this->excelPost($actor, [
            ['P-500', '13000', '15000', '2026-01-01', '2026-06-30', ''],
            ['P-500', '14000', '', ExcelDate::PHPToExcel(new DateTime('2026-07-01')), '', ''],
        ])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.created', 1);

        $prices = ProductPrice::query()->where('product_id', $product->id)->orderBy('valid_from')->get();
        $this->assertCount(2, $prices);
        $this->assertSame('13000.0000', $prices[0]->price);
        $this->assertSame('15000.0000', $prices[0]->regular_price);
        $this->assertSame('2026-07-01', $prices[1]->valid_from->toDateString());
    }

    public function test_product_price_import_rejects_invalid_rows(): void
    {
        $error = $this->excelPost($this->userWithAccess(['product-prices' => ['create', 'update']]), [
            ['P-X', 'abc', '', '01/02/2026', '', ''],
        ])->assertUnprocessable()->json('errors.file.0');

        foreach (['kode_product P-X tidak ditemukan', 'harga harus angka', 'berlaku_mulai harus format YYYY-MM-DD'] as $expected) {
            $this->assertStringContainsString($expected, $error);
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  urutan kolom = ProductPriceImportService::COLUMNS
     */
    private function excelPost(User $user, array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([ProductPriceImportService::COLUMNS, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'price').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($user)->post(
            '/api/dpl/product-prices/import',
            ['file' => new UploadedFile($path, 'import.xlsx', null, null, true)],
            ['Accept' => 'application/json'],
        );
    }
}
