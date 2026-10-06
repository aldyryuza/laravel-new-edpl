<?php

namespace Tests\Feature;

use App\Models\SalesArea;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * Import Excel master organisasi: upsert per kode, all-or-nothing.
 */
class OrganizationImportTest extends TestCase
{
    use RefreshDatabase;

    private const AREA_COLUMNS = ['kode', 'nama', 'kode_region', 'aktif'];

    public function test_area_import_creates_new_and_updates_existing_codes(): void
    {
        $region = SalesRegion::factory()->create(['code' => 'R-JABAR']);
        $existing = SalesArea::factory()->create(['code' => 'A-BDG', 'name' => 'Bandung Lama']);

        $this->actingAs($this->importer('sales-areas'))
            ->post('/api/sales-areas/import', ['file' => $this->excel(self::AREA_COLUMNS, [
                ['A-BDG', 'Bandung', 'R-JABAR', 'nonaktif'],
                ['A-BGR', 'Bogor', 'R-JABAR', ''],
            ])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);

        $existing->refresh();
        $this->assertSame('Bandung', $existing->name);
        $this->assertSame($region->id, $existing->region_id);
        $this->assertFalse($existing->is_active);
        $this->assertTrue(SalesArea::query()->where('code', 'A-BGR')->sole()->is_active);

        $log = Activity::query()->where('event', 'imported')->sole();
        $this->assertSame(AuditLog::DATA, $log->log_name);
        $this->assertSame('area', $log->properties['type']);
    }

    public function test_any_invalid_row_rejects_whole_file_with_row_numbers(): void
    {
        SalesRegion::factory()->create(['code' => 'R-JABAR']);

        $response = $this->actingAs($this->importer('sales-areas'))
            ->post('/api/sales-areas/import', ['file' => $this->excel(self::AREA_COLUMNS, [
                ['A-1', 'Valid', 'R-JABAR', '1'],
                ['A-2', '', 'R-XXX', 'mungkin'],
                ['A-1', 'Duplikat', 'R-JABAR', '1'],
            ])], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $errors = $response->json('errors.file');
        $this->assertCount(2, $errors);
        $this->assertStringStartsWith('Baris 3:', $errors[0]);
        $this->assertStringContainsString('nama wajib diisi', $errors[0]);
        $this->assertStringContainsString('kode_region R-XXX tidak ditemukan', $errors[0]);
        $this->assertStringContainsString('aktif harus', $errors[0]);
        $this->assertStringStartsWith('Baris 4:', $errors[1]);
        $this->assertStringContainsString('lebih dari sekali', $errors[1]);

        $this->assertSame(0, SalesArea::query()->count(), 'Baris valid pun tidak boleh tersimpan.');
    }

    public function test_numeric_codes_typed_as_numbers_in_excel_are_matched_as_text(): void
    {
        $region = SalesRegion::factory()->create(['code' => '101']);

        $this->actingAs($this->importer('sales-areas'))
            ->post('/api/sales-areas/import', ['file' => $this->excel(self::AREA_COLUMNS, [
                [2001, 'Area Angka', 101, 1],
            ])], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame($region->id, SalesArea::query()->where('code', '2001')->sole()->region_id);
    }

    public function test_file_without_required_columns_is_rejected(): void
    {
        $this->actingAs($this->importer('sales-areas'))
            ->post('/api/sales-areas/import', ['file' => $this->excel(['kode', 'nama'], [['A-1', 'Area']])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Kolom wajib tidak ditemukan: kode_region. Gunakan template yang disediakan.');
    }

    public function test_import_requires_both_create_and_update_access(): void
    {
        $file = $this->excel(['kode', 'nama', 'aktif'], [['Z-1', 'Zona', '1']]);

        $this->actingAs($this->userWithAccess(['sales-zones' => ['create']]))
            ->post('/api/sales-zones/import', ['file' => $file], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_zone_and_region_import_resolve_parent_by_code(): void
    {
        $actor = $this->userWithAccess(['sales-zones' => ['create', 'update'], 'sales-regions' => ['create', 'update']]);

        $this->actingAs($actor)
            ->post('/api/sales-zones/import', ['file' => $this->excel(['kode', 'nama', 'aktif'], [['Z-JAWA', 'Jawa', 'ya']])], ['Accept' => 'application/json'])
            ->assertOk();

        $this->actingAs($actor)
            ->post('/api/sales-regions/import', ['file' => $this->excel(['kode', 'nama', 'kode_zona', 'aktif'], [
                ['R-JABAR', 'Jawa Barat', 'Z-JAWA', ''],
            ])], ['Accept' => 'application/json'])
            ->assertOk();

        $this->assertSame(
            SalesZone::query()->where('code', 'Z-JAWA')->sole()->id,
            SalesRegion::query()->where('code', 'R-JABAR')->sole()->zone_id,
        );
    }

    public function test_import_button_and_modal_shown_only_with_create_and_update_access(): void
    {
        foreach (['sales-zones', 'sales-regions', 'sales-areas'] as $menu) {
            $this->actingAs($this->userWithAccess([$menu => ['read', 'create']]))
                ->get("/{$menu}")
                ->assertOk()
                ->assertDontSee('excel-import-modal');

            $this->actingAs($this->userWithAccess([$menu => ['read', 'create', 'update']]))
                ->get("/{$menu}")
                ->assertOk()
                ->assertSee('id="excel-import-modal"', escape: false)
                ->assertSee("api/{$menu}/import")
                ->assertSee(route("{$menu}.import-template"));
        }
    }

    public function test_template_lists_columns_and_parent_codes(): void
    {
        SalesRegion::factory()->create(['code' => 'R-JABAR', 'name' => 'Jawa Barat']);

        $this->actingAs($this->userWithAccess(['sales-areas' => ['read']]))->get('/sales-areas/import-template')->assertForbidden();

        $response = $this->actingAs($this->userWithAccess(['sales-areas' => ['create']]))->get('/sales-areas/import-template')->assertOk();

        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());

        $this->assertSame(self::AREA_COLUMNS, $workbook->getSheetByName('Data')->toArray()[0]);
        $this->assertContains(['R-JABAR', 'Jawa Barat'], $workbook->getSheetByName('Petunjuk')->toArray());
    }

    private function importer(string $menu): User
    {
        return $this->userWithAccess([$menu => ['create', 'update']]);
    }

    /**
     * File .xlsx sungguhan; nilai angka tetap bertipe angka seperti diketik di Excel.
     *
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function excel(array $headings, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return new UploadedFile($path, 'import.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
