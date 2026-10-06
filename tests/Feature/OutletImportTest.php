<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\OutletCategory;
use App\Models\OutletGroup;
use App\Models\OutletGroupMember;
use App\Models\SalesArea;
use App\Models\User;
use App\Services\OutletImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class OutletImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_outlets_with_references_and_group(): void
    {
        SalesArea::factory()->create(['code' => 'A-BDG']);
        OutletCategory::factory()->create(['code' => 'RUMAH_SAKIT']);
        OutletGroup::factory()->create(['code' => 'HERMINA']);

        $this->import([
            ['kode' => 'OUT-1', 'nama' => 'RS Hermina Bandung', 'alamat' => 'Jl. Contoh', 'kode_area' => 'A-BDG', 'kode_kategori' => 'RUMAH_SAKIT',
                'jumlah_bed' => 150, 'kode_grup' => 'HERMINA', 'crm_id' => 9001, 'latitude' => -6.9147, 'longitude' => 107.6098, 'aktif' => 'ya'],
        ])->assertOk()->assertJsonPath('data.created', 1);

        $outlet = Outlet::query()->where('code', 'OUT-1')->with(['area', 'category', 'activeGroupMembership.outletGroup'])->sole();
        $this->assertSame('A-BDG', $outlet->area->code);
        $this->assertSame('RUMAH_SAKIT', $outlet->category->code);
        $this->assertSame('HERMINA', $outlet->activeGroupMembership->outletGroup->code);
        $this->assertSame(150, $outlet->bed_count);
        $this->assertSame(9001, $outlet->crm_id);
        $this->assertSame('-6.9147000', $outlet->latitude);
    }

    public function test_empty_group_code_keeps_existing_group_while_other_fields_update(): void
    {
        $membership = OutletGroupMember::factory()->create();
        $outlet = $membership->outlet;

        $this->import([['kode' => $outlet->code, 'nama' => 'Nama Baru', 'aktif' => '1']])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertSame('Nama Baru', $outlet->fresh()->name);
        $this->assertSame($membership->outlet_group_id, $outlet->activeGroupMembership()->first()->outlet_group_id);
    }

    public function test_group_code_moves_outlet_to_new_group(): void
    {
        $outlet = OutletGroupMember::factory()->create()->outlet;
        OutletGroup::factory()->create(['code' => 'SILOAM']);

        $this->import([['kode' => $outlet->code, 'nama' => $outlet->name, 'kode_grup' => 'SILOAM']])->assertOk();

        $this->assertSame('SILOAM', $outlet->activeGroupMembership()->first()->outletGroup->code);
        $this->assertSame(2, $outlet->groupMemberships()->count());
    }

    public function test_invalid_references_and_numbers_reject_whole_file(): void
    {
        $response = $this->import([
            ['kode' => 'OUT-OK', 'nama' => 'Valid'],
            ['kode' => 'OUT-X', 'nama' => 'Salah', 'kode_area' => 'A-XXX', 'kode_kategori' => 'KAT-X', 'kode_kepemilikan' => 'OWN-X',
                'jumlah_bed' => '-5', 'kode_grup' => 'GRP-X', 'crm_id' => 'abc', 'latitude' => '120'],
        ])->assertUnprocessable();

        $error = $response->json('errors.file.0');
        $this->assertStringStartsWith('Baris 3:', $error);

        foreach (['kode_area A-XXX', 'kode_kategori KAT-X', 'kode_kepemilikan OWN-X', 'jumlah_bed', 'kode_grup GRP-X', 'crm_id', 'latitude'] as $expected) {
            $this->assertStringContainsString($expected, $error);
        }

        $this->assertSame(0, Outlet::query()->count());
    }

    public function test_template_lists_reference_codes(): void
    {
        OutletCategory::factory()->create(['code' => 'INSTANSI', 'name' => 'Instansi']);

        $response = $this->actingAs($this->userWithAccess(['outlets' => ['create']]))->get('/outlets/import-template')->assertOk();
        $workbook = IOFactory::load($response->baseResponse->getFile()->getPathname());

        $this->assertSame(OutletImportService::COLUMNS, $workbook->getSheetByName('Data')->toArray()[0]);
        $this->assertContains(['INSTANSI', 'Instansi'], $workbook->getSheetByName('Petunjuk')->toArray());
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows  diisi per nama kolom; kolom yang tidak disebut dikosongkan
     */
    private function import(array $rows): TestResponse
    {
        $ordered = array_map(fn (array $row) => array_map(fn (string $column) => $row[$column] ?? '', OutletImportService::COLUMNS), $rows);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([OutletImportService::COLUMNS, ...$ordered], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'outlet').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($this->importer())->post(
            '/api/outlets/import',
            ['file' => new UploadedFile($path, 'outlet.xlsx', null, null, true)],
            ['Accept' => 'application/json'],
        );
    }

    private function importer(): User
    {
        return $this->userWithAccess(['outlets' => ['create', 'update']]);
    }
}
