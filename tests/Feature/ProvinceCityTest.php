<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Distributor;
use App\Models\Outlet;
use App\Models\Province;
use App\Models\User;
use App\Services\OutletImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Master wilayah administratif (provinsi, kota) dan pemakaiannya di outlet & distributor.
 */
class ProvinceCityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'provinsi' => ['provinces', '/provinces'],
            'kota' => ['cities', '/cities'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_city_requires_province_and_datatable_filters_by_province(): void
    {
        $actor = $this->userWithAccess(['cities' => ['create', 'read']]);
        $malut = Province::factory()->create(['code' => '82', 'name' => 'MALUKU UTARA']);
        City::factory()->create();

        $this->actingAs($actor)
            ->postJson('/api/cities', ['code' => '8271', 'name' => 'KOTA TERNATE', 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('province_id');

        $this->actingAs($actor)
            ->postJson('/api/cities', ['code' => '8271', 'name' => 'KOTA TERNATE', 'province_id' => $malut->id, 'is_active' => true])
            ->assertCreated();

        $this->actingAs($actor)
            ->getJson('/api/cities/datatable?'.$this->dataTableQuery(['code', 'name', 'is_active'], ['province_id' => $malut->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.code', '8271')
            ->assertJsonPath('data.0.province.name', 'MALUKU UTARA');
    }

    public function test_province_and_city_import_use_bps_codes(): void
    {
        $actor = $this->userWithAccess(['provinces' => ['create', 'update'], 'cities' => ['create', 'update']]);

        $this->importAs($actor, 'provinces', ['kode', 'nama', 'aktif'], [[82, 'MALUKU UTARA', '']])->assertOk();
        $this->importAs($actor, 'cities', ['kode', 'nama', 'kode_provinsi', 'aktif'], [[8271, 'KOTA TERNATE', 82, '']])->assertOk();

        $this->assertSame('82', City::query()->where('code', '8271')->sole()->province->code);

        $this->importAs($actor, 'cities', ['kode', 'nama', 'kode_provinsi', 'aktif'], [['82710000000', 'Terlalu Panjang', 82, '']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', 'Baris 2: kode maksimal 10 karakter.');
    }

    public function test_city_in_use_cannot_be_deleted(): void
    {
        $city = City::factory()->create();
        Outlet::factory()->create(['city_id' => $city->id]);

        $this->actingAs($this->userWithAccess(['cities' => ['delete']]))
            ->deleteJson("/api/cities/{$city->id}")
            ->assertUnprocessable();

        $this->assertModelExists($city);
    }

    public function test_outlet_and_distributor_store_city(): void
    {
        $city = City::factory()->create();

        $this->actingAs($this->userWithAccess(['outlets' => ['create']]))
            ->postJson('/api/outlets', ['code' => 'OUT-1', 'name' => 'RS', 'city_id' => $city->id, 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('data.city.code', $city->code);

        $this->actingAs($this->userWithAccess(['distributors' => ['create']]))
            ->postJson('/api/distributors', ['code' => 'D-1', 'name' => 'Dist', 'city_id' => $city->id, 'is_branch' => false, 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('data.city.code', $city->code);

        $this->assertSame($city->id, Distributor::query()->where('code', 'D-1')->sole()->city_id);
    }

    public function test_outlet_import_resolves_city_code(): void
    {
        $city = City::factory()->create(['code' => '3273']);
        $row = array_map(fn (string $column) => ['kode' => 'OUT-1', 'nama' => 'RS Bandung', 'kode_kota' => 3273][$column] ?? '', OutletImportService::COLUMNS);

        $this->importAs($this->userWithAccess(['outlets' => ['create', 'update']]), 'outlets', OutletImportService::COLUMNS, [$row])
            ->assertOk();

        $this->assertSame($city->id, Outlet::query()->where('code', 'OUT-1')->sole()->city_id);
    }

    /**
     * @param  array<int, string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function importAs(User $user, string $resource, array $headings, array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([$headings, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), $resource).'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($user)->post(
            "/api/{$resource}/import",
            ['file' => new UploadedFile($path, "{$resource}.xlsx", null, null, true)],
            ['Accept' => 'application/json'],
        );
    }
}
