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
            ['OUT-1', 'RS Hermina Bandung', 'Jl. Contoh', 'A-BDG', 'RUMAH_SAKIT', '', 150, 'HERMINA', 9001, -6.9147, 107.6098, 'ya'],
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

        $this->import([[$outlet->code, 'Nama Baru', '', '', '', '', '', '', '', '', '', '1']])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertSame('Nama Baru', $outlet->fresh()->name);
        $this->assertSame($membership->outlet_group_id, $outlet->activeGroupMembership()->first()->outlet_group_id);
    }

    public function test_group_code_moves_outlet_to_new_group(): void
    {
        $outlet = OutletGroupMember::factory()->create()->outlet;
        OutletGroup::factory()->create(['code' => 'SILOAM']);

        $this->import([[$outlet->code, $outlet->name, '', '', '', '', '', 'SILOAM', '', '', '', '']])->assertOk();

        $this->assertSame('SILOAM', $outlet->activeGroupMembership()->first()->outletGroup->code);
        $this->assertSame(2, $outlet->groupMemberships()->count());
    }

    public function test_invalid_references_and_numbers_reject_whole_file(): void
    {
        $response = $this->import([
            ['OUT-OK', 'Valid', '', '', '', '', '', '', '', '', '', ''],
            ['OUT-X', 'Salah', '', 'A-XXX', 'KAT-X', 'OWN-X', '-5', 'GRP-X', 'abc', '120', '', ''],
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
     * @param  array<int, array<int, mixed>>  $rows  urutan kolom = OutletImportService::COLUMNS
     */
    private function import(array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([OutletImportService::COLUMNS, ...$rows], null, 'A1', true);
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
