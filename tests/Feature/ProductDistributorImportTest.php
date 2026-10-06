<?php

namespace Tests\Feature;

use App\Models\Distributor;
use App\Models\Product;
use App\Models\SalesRegion;
use App\Services\DistributorImportService;
use App\Services\ProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class ProductDistributorImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_import_upserts_by_code(): void
    {
        $existing = Product::factory()->create(['code' => 'P-1', 'name' => 'Lama']);

        $this->import('products', ProductImportService::COLUMNS, [
            ['P-1', 'Baru', 500, 'ya', 'NS-1', ''],
            ['P-2', 'Product Dua', '', '', '', 'tidak'],
        ])->assertOk()->assertJsonPath('data.created', 1)->assertJsonPath('data.updated', 1);

        $existing->refresh();
        $this->assertSame('Baru', $existing->name);
        $this->assertSame('500.0000', $existing->volume);
        $this->assertTrue($existing->is_branded);

        $second = Product::query()->where('code', 'P-2')->sole();
        $this->assertNull($second->volume);
        $this->assertFalse($second->is_branded);
        $this->assertFalse($second->is_active);
    }

    public function test_product_import_rejects_invalid_volume(): void
    {
        $this->import('products', ProductImportService::COLUMNS, [['P-1', 'Nama', 'lima ratus', '', '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 2: volume harus angka 0 s/d 999999.9999.');
    }

    public function test_distributor_import_links_parent_in_same_file_and_regions(): void
    {
        SalesRegion::factory()->create(['code' => 'R-JABAR']);
        SalesRegion::factory()->create(['code' => 'R-DKI']);

        $this->import('distributors', DistributorImportService::COLUMNS, [
            ['D-BDG', 'Cabang Bandung', 'GRP-A', 'Bandung', 'ya', 'D-PUSAT', 'R-JABAR', '', ''],
            ['D-PUSAT', 'Distributor Pusat', 'GRP-A', 'Jakarta', '', '', 'R-DKI, R-JABAR', '', ''],
        ])->assertOk()->assertJsonPath('data.created', 2);

        $branch = Distributor::query()->where('code', 'D-BDG')->with('parent', 'regions')->sole();
        $this->assertSame('D-PUSAT', $branch->parent->code);
        $this->assertTrue($branch->is_branch);
        $this->assertSame(['R-JABAR'], $branch->regions->pluck('code')->all());
        $this->assertCount(2, Distributor::query()->where('code', 'D-PUSAT')->sole()->regions);
    }

    public function test_empty_region_column_keeps_existing_regions(): void
    {
        $distributor = Distributor::factory()->create(['code' => 'D-1']);
        $distributor->regions()->attach(SalesRegion::factory()->create());

        $this->import('distributors', DistributorImportService::COLUMNS, [['D-1', 'Nama Baru', '', '', '', '', '', '', '']])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertCount(1, $distributor->regions()->get());
    }

    public function test_distributor_import_rejects_cycles_and_unknown_codes(): void
    {
        $head = Distributor::factory()->create(['code' => 'D-HEAD']);
        Distributor::factory()->create(['code' => 'D-BRANCH', 'parent_distributor_id' => $head->id]);

        $response = $this->import('distributors', DistributorImportService::COLUMNS, [
            ['D-HEAD', 'Head', '', '', '', 'D-BRANCH', '', '', ''],
            ['D-X', 'X', '', '', '', 'D-TIDAK-ADA', 'R-XXX', '', ''],
        ])->assertUnprocessable();

        $errors = $response->json('errors.file');
        $this->assertStringContainsString('kode_induk D-TIDAK-ADA tidak ditemukan', $errors[0]);
        $this->assertStringContainsString('kode_region R-XXX tidak ditemukan', $errors[0]);
        $this->assertStringContainsString('Baris 2: kode_induk membuat hierarki melingkar', $errors[1]);
        $this->assertNull($head->fresh()->parent_distributor_id);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function import(string $resource, array $headings, array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), $resource).'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($this->userWithAccess([$resource => ['create', 'update']]))->post(
            "/api/{$resource}/import",
            ['file' => new UploadedFile($path, "{$resource}.xlsx", null, null, true)],
            ['Accept' => 'application/json'],
        );
    }
}
