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

        $this->importDistributors([
            ['kode' => 'D-BDG', 'nama' => 'Cabang Bandung', 'grup_distributor' => 'GRP-A', 'kota' => 'Bandung', 'cabang' => 'ya', 'kode_induk' => 'D-PUSAT', 'kode_region' => 'R-JABAR'],
            ['kode' => 'D-PUSAT', 'nama' => 'Distributor Pusat', 'grup_distributor' => 'GRP-A', 'kota' => 'Jakarta', 'kode_region' => 'R-DKI, R-JABAR'],
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

        $this->importDistributors([['kode' => 'D-1', 'nama' => 'Nama Baru']])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertCount(1, $distributor->regions()->get());
    }

    public function test_distributor_import_rejects_cycles_and_unknown_codes(): void
    {
        $head = Distributor::factory()->create(['code' => 'D-HEAD']);
        Distributor::factory()->create(['code' => 'D-BRANCH', 'parent_distributor_id' => $head->id]);

        $response = $this->importDistributors([
            ['kode' => 'D-HEAD', 'nama' => 'Head', 'kode_induk' => 'D-BRANCH'],
            ['kode' => 'D-X', 'nama' => 'X', 'kode_induk' => 'D-TIDAK-ADA', 'kode_region' => 'R-XXX'],
        ])->assertUnprocessable();

        $errors = $response->json('errors.file');
        $this->assertStringContainsString('kode_induk D-TIDAK-ADA tidak ditemukan', $errors[0]);
        $this->assertStringContainsString('kode_region R-XXX tidak ditemukan', $errors[0]);
        $this->assertStringContainsString('Baris 2: kode_induk membuat hierarki melingkar', $errors[1]);
        $this->assertNull($head->fresh()->parent_distributor_id);
    }

    public function test_legacy_distributor_row_imports_contact_and_meeting_location(): void
    {
        SalesRegion::factory()->create(['code' => 'V']);

        // Contoh baris legacy MJMTERNATE (telepon berawalan spasi, koordinat & radius angka).
        $this->importDistributors([[
            'kode' => 'MJMTERNATE', 'nama' => 'PT. MARKSON JAYA MEDIKA', 'grup_distributor' => 'MJM', 'kota' => 'TERNATE',
            'alamat' => 'Jl. Raya Mangga Dua, Mangga Dua, Kec. Ternate Sel., Kota Ternate, Maluku Utara',
            'telepon' => ' 0811-4329-539', 'latitude' => 0.7713696, 'longitude' => 127.3762587, 'radius_meter' => 20,
            'kode_region' => 'V', 'aktif' => 1,
        ]])->assertOk()->assertJsonPath('data.created', 1);

        $distributor = Distributor::query()->where('code', 'MJMTERNATE')->with('regions')->sole();
        $this->assertSame('0811-4329-539', $distributor->phone);
        $this->assertSame('0.7713696', $distributor->latitude);
        $this->assertSame('127.3762587', $distributor->longitude);
        $this->assertSame(20, $distributor->meeting_radius_meters);
        $this->assertSame(['V'], $distributor->regions->pluck('code')->all());
    }

    public function test_distributor_import_validates_contact_and_location(): void
    {
        $error = $this->importDistributors([[
            'kode' => 'D-1', 'nama' => 'X', 'email' => 'bukan-email', 'latitude' => '1.5', 'radius_meter' => '-3',
        ]])->assertUnprocessable()->json('errors.file.0');

        $this->assertStringContainsString('email tidak valid', $error);
        $this->assertStringContainsString('latitude dan longitude harus diisi bersamaan', $error);
        $this->assertStringContainsString('radius_meter harus angka bulat', $error);
    }

    /**
     * Baris diisi berdasarkan nama kolom; kolom yang tidak disebut dikosongkan.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function importDistributors(array $rows): TestResponse
    {
        $ordered = array_map(
            fn (array $row) => array_map(fn (string $column) => $row[$column] ?? '', DistributorImportService::COLUMNS),
            $rows,
        );

        return $this->import('distributors', DistributorImportService::COLUMNS, $ordered);
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
