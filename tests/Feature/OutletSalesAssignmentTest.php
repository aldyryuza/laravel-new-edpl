<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\OutletSalesAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * PIC outlet (AM / SPV): CRUD dan import Excel.
 */
class OutletSalesAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->userWithAccess(['outlet-sales-assignments' => ['read', 'create', 'update', 'delete']]);
    }

    public function test_store_requires_am_or_spv_and_one_row_per_outlet(): void
    {
        $admin = $this->admin();
        $outlet = Outlet::factory()->create();
        $spv = User::factory()->create();

        $this->actingAs($admin)->postJson('/api/outlet-sales-assignments', ['outlet_id' => $outlet->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['am_user_id', 'spv_user_id']);

        $this->actingAs($admin)->postJson('/api/outlet-sales-assignments', ['outlet_id' => $outlet->id, 'spv_user_id' => $spv->id])
            ->assertCreated()
            ->assertJsonPath('data.spv.id', $spv->id);

        $this->actingAs($admin)->postJson('/api/outlet-sales-assignments', ['outlet_id' => $outlet->id, 'am_user_id' => $spv->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['outlet_id' => 'Outlet ini sudah punya PIC.']);
    }

    public function test_owner_is_spv_then_am(): void
    {
        $am = User::factory()->create();
        $spv = User::factory()->create();

        $this->assertSame($spv->id, OutletSalesAssignment::factory()->create(['am_user_id' => $am->id, 'spv_user_id' => $spv->id])->ownerUserId());
        $this->assertSame($am->id, OutletSalesAssignment::factory()->create(['am_user_id' => $am->id])->ownerUserId());
    }

    public function test_requires_access(): void
    {
        $outlet = Outlet::factory()->create();
        $reader = $this->userWithAccess(['outlet-sales-assignments' => ['read']]);

        $this->actingAs($reader)->get('/outlet-sales-assignments')->assertOk();
        $this->actingAs($reader)->postJson('/api/outlet-sales-assignments', ['outlet_id' => $outlet->id, 'am_user_id' => $reader->id])->assertForbidden();
    }

    public function test_import_upserts_by_outlet_using_nik(): void
    {
        $outlet = Outlet::factory()->create(['code' => 'OUT-1']);
        $newOutlet = Outlet::factory()->create(['code' => 'OUT-2']);
        $am = User::factory()->create(['nik' => '1001']);
        $spv = User::factory()->create(['nik' => '1002']);
        OutletSalesAssignment::factory()->create(['outlet_id' => $outlet->id, 'am_user_id' => $am->id]);

        $this->import([['OUT-1', '', 1002], ['OUT-2', 1001, '']])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);

        $this->assertDatabaseHas('outlet_sales_assignments', ['outlet_id' => $outlet->id, 'am_user_id' => null, 'spv_user_id' => $spv->id]);
        $this->assertDatabaseHas('outlet_sales_assignments', ['outlet_id' => $newOutlet->id, 'am_user_id' => $am->id, 'spv_user_id' => null]);
    }

    public function test_import_rejects_unknown_and_empty_rows_without_saving(): void
    {
        Outlet::factory()->create(['code' => 'OUT-1']);
        User::factory()->create(['nik' => '1001']);

        $this->import([['OUT-1', 1001, ''], ['OUT-1', '', ''], ['OUT-9', 9999, '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: isi nik_am atau nik_spv.')
            ->assertJsonPath('errors.file.1', 'Baris 4: kode_outlet OUT-9 tidak ditemukan; nik_am 9999 tidak ditemukan atau user nonaktif.');

        $this->assertDatabaseCount('outlet_sales_assignments', 0);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function import(array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([['kode_outlet', 'nik_am', 'nik_spv'], ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($this->admin())->post(
            '/api/outlet-sales-assignments/import',
            ['file' => new UploadedFile($path, 'import.xlsx', null, null, true)],
            ['Accept' => 'application/json'],
        );
    }
}
