<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Distributor;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Dpl\DistributorMarginRule;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\CityLogisticFeeImportService;
use App\Services\DistributorMarginImportService;
use App\Services\DistributorMarginOverrideImportService;
use App\Services\DistributorMarginRuleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Import Excel margin distributor, logistic fee kota, margin khusus product, dan margin khusus outlet.
 */
class DplMarginImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_distributor_margin_import_updates_only_filled_cells(): void
    {
        $first = Distributor::factory()->create(['code' => 'D-1', 'margin' => '5', 'margin_team_b' => '3', 'is_special_case_margin' => false]);
        $second = Distributor::factory()->create(['code' => 'D-2', 'margin' => '4', 'margin_team_b' => null, 'is_special_case_margin' => true]);

        $this->importDistributorMargins([
            ['D-1', 7.5, '', 'ya'],
            ['D-2', '', '', ''],
        ])->assertOk()->assertJsonPath('data.updated', 1)->assertJsonPath('data.unchanged', 1);

        $first->refresh();
        $this->assertSame('7.5000', $first->margin);
        $this->assertSame('3.0000', $first->margin_team_b);
        $this->assertTrue($first->is_special_case_margin);

        $second->refresh();
        $this->assertSame('4.0000', $second->margin);
        $this->assertNull($second->margin_team_b);
        $this->assertTrue($second->is_special_case_margin);
    }

    public function test_distributor_margin_import_rejects_unknown_and_duplicate_codes(): void
    {
        $distributor = Distributor::factory()->create(['code' => 'D-1', 'margin' => '5']);

        $this->importDistributorMargins([['D-1', 9, '', ''], ['D-404', 1, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kode_distributor D-404 tidak ditemukan.');

        $this->assertSame('5.0000', $distributor->refresh()->margin);

        $this->importDistributorMargins([['D-1', 9, '', ''], ['D-1', 8, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kode_distributor D-1 muncul lebih dari sekali di file.');

        $this->importDistributorMargins([['D-1', 'lima', '', 'mungkin']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 2: margin harus angka >= 0 dengan maks. 4 desimal; margin_khusus_outlet harus 1/ya atau 0/tidak.');

        $this->assertSame('5.0000', $distributor->refresh()->margin);
    }

    public function test_distributor_margin_import_requires_update_access_and_national_scope(): void
    {
        Distributor::factory()->create(['code' => 'D-1', 'margin' => '5']);
        $rows = [['D-1', 9, '', '']];

        $this->importDistributorMargins($rows, $this->userWithAccess(['distributors' => ['read', 'create']]))->assertForbidden();
        $this->importDistributorMargins($rows, $this->userWithAccess(['distributors' => ['read', 'update']], national: false))->assertForbidden();
        $this->assertSame('5.0000', Distributor::query()->sole()->margin);

        $this->actingAs($this->userWithAccess(['distributors' => ['read', 'update']], national: false))
            ->get('/distributors')
            ->assertOk()
            ->assertDontSee('margin-import-modal');

        $national = $this->userWithAccess(['distributors' => ['read', 'update']]);

        $this->actingAs($national)
            ->get('/distributors')
            ->assertOk()
            ->assertSee('id="margin-import-modal"', escape: false)
            ->assertSee(route('distributors.import-margin-template'));

        $this->actingAs($national)->get('/distributors/import-margin-template')->assertOk();
        $this->importDistributorMargins($rows, $national)->assertOk()->assertJsonPath('data.updated', 1);
    }

    public function test_city_logistic_fee_import_matches_numeric_codes_and_keeps_empty_cells(): void
    {
        $bekasi = City::factory()->create(['code' => '3216', 'logistic_fee' => '2']);
        $bogor = City::factory()->create(['code' => '3201', 'logistic_fee' => '1.5']);

        // Excel mengirim kode kota sebagai angka.
        $this->importLogisticFees([[3216, 4.25], ['3201', '']])
            ->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.unchanged', 1);

        $this->assertSame('4.2500', $bekasi->refresh()->logistic_fee);
        $this->assertSame('1.5000', $bogor->refresh()->logistic_fee);
    }

    public function test_city_logistic_fee_import_rejects_unknown_and_duplicate_codes(): void
    {
        $city = City::factory()->create(['code' => '3216', 'logistic_fee' => '2']);

        $this->importLogisticFees([[3216, 5], [9999, 1]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kode_kota 9999 tidak ditemukan.');

        $this->importLogisticFees([[3216, 5], ['3216', 6]])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kode_kota 3216 muncul lebih dari sekali di file.');

        $this->assertSame('2.0000', $city->refresh()->logistic_fee);

        $this->importLogisticFees([[3216, 5]], $this->userWithAccess(['cities' => ['read']]))->assertForbidden();
        $this->actingAs($this->userWithAccess(['cities' => ['update']]))->get('/cities/import-logistic-fee-template')->assertOk();
    }

    public function test_distributor_margin_rule_import_upserts_by_group_product_and_city(): void
    {
        $product = Product::factory()->create(['code' => 'P-1']);
        $city = City::factory()->create(['code' => '3216']);
        $existing = DistributorMarginRule::factory()->create([
            'distributor_group_code' => 'GRP-A', 'product_id' => $product->id, 'city_id' => null, 'margin' => '5',
        ]);

        $this->importMarginRules([
            ['grp-a', 'P-1', '', 7.5, 'Catatan', ''],
            ['GRP-A', 'P-1', 3216, 8, '', 'tidak'],
        ])->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.unchanged', 0);

        $existing->refresh();
        $this->assertSame('7.5000', $existing->margin);
        $this->assertNull($existing->city_id);
        $this->assertSame('Catatan', $existing->note);

        $perCity = DistributorMarginRule::query()->where('city_id', $city->id)->sole();
        $this->assertSame('GRP-A', $perCity->distributor_group_code);
        $this->assertSame('8.0000', $perCity->margin);
        $this->assertFalse($perCity->is_active);
    }

    public function test_distributor_margin_rule_import_rejects_unknown_codes_and_duplicates(): void
    {
        Product::factory()->create(['code' => 'P-1']);

        $this->importMarginRules([['GRP-A', 'P-1', '', 5, '', ''], ['GRP-A', 'P-404', '', 5, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kode_product P-404 tidak ditemukan.');

        $this->importMarginRules([['GRP-A', 'P-1', 9999, 5, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 2: kode_kota 9999 tidak ditemukan.');

        $this->importMarginRules([['grp-a', 'P-1', '', 5, '', ''], ['GRP-A', 'P-1', '', 6, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kombinasi kode_grup, kode_product, dan kode_kota muncul lebih dari sekali di file.');

        $this->assertSame(0, DistributorMarginRule::query()->count());

        $this->actingAs($this->userWithAccess(['distributor-margin-rules' => ['create']]))
            ->get('/dpl/distributor-margin-rules/import-template')
            ->assertOk();
    }

    public function test_distributor_margin_override_import_upserts_by_group_and_outlet(): void
    {
        $outlet = Outlet::factory()->create(['code' => 'OUT-1']);
        $other = Outlet::factory()->create(['code' => 'OUT-2']);
        $existing = DistributorMarginOverride::factory()->create(['distributor_group_code' => 'GRP-B', 'outlet_id' => $outlet->id, 'margin' => '6']);

        $this->importMarginOverrides([
            ['grp-b', 'OUT-1', 6, '', ''],
            ['grp-b', 'OUT-2', 4.25, 'Khusus', 'tidak'],
        ])->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.unchanged', 1);

        $this->assertSame('6.0000', $existing->refresh()->margin);

        $created = DistributorMarginOverride::query()->where('outlet_id', $other->id)->sole();
        $this->assertSame('GRP-B', $created->distributor_group_code);
        $this->assertSame('4.2500', $created->margin);
        $this->assertSame('Khusus', $created->note);
        $this->assertFalse($created->is_active);
    }

    public function test_distributor_margin_override_import_rejects_unknown_outlets_and_duplicates(): void
    {
        Outlet::factory()->create(['code' => 'OUT-1']);

        $this->importMarginOverrides([['GRP-B', 'OUT-1', 6, '', ''], ['GRP-B', 'OUT-404', 6, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kode_outlet OUT-404 tidak ditemukan.');

        $this->importMarginOverrides([['grp-b', 'OUT-1', 6, '', ''], ['GRP-B', 'OUT-1', 7, '', '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 3: kombinasi kode_grup dan kode_outlet muncul lebih dari sekali di file.');

        $this->assertSame(0, DistributorMarginOverride::query()->count());

        $this->importMarginOverrides([['GRP-B', 'OUT-1', 6, '', '']], $this->userWithAccess(['distributor-margin-overrides' => ['create']]))
            ->assertForbidden();
        $this->actingAs($this->userWithAccess(['distributor-margin-overrides' => ['create']]))
            ->get('/dpl/distributor-margin-overrides/import-template')
            ->assertOk();
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function importDistributorMargins(array $rows, ?User $user = null): TestResponse
    {
        return $this->upload('/api/distributors/import-margin', DistributorMarginImportService::COLUMNS, $rows,
            $user ?? $this->userWithAccess(['distributors' => ['update']]));
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function importLogisticFees(array $rows, ?User $user = null): TestResponse
    {
        return $this->upload('/api/cities/import-logistic-fee', CityLogisticFeeImportService::COLUMNS, $rows,
            $user ?? $this->userWithAccess(['cities' => ['update']]));
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function importMarginRules(array $rows): TestResponse
    {
        return $this->upload('/api/dpl/distributor-margin-rules/import', DistributorMarginRuleImportService::COLUMNS, $rows,
            $this->userWithAccess(['distributor-margin-rules' => ['create', 'update']]));
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function importMarginOverrides(array $rows, ?User $user = null): TestResponse
    {
        return $this->upload('/api/dpl/distributor-margin-overrides/import', DistributorMarginOverrideImportService::COLUMNS, $rows,
            $user ?? $this->userWithAccess(['distributor-margin-overrides' => ['create', 'update']]));
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function upload(string $url, array $headings, array $rows, User $user): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($user)->post(
            $url,
            ['file' => new UploadedFile($path, 'import.xlsx', null, null, true)],
            ['Accept' => 'application/json'],
        );
    }
}
