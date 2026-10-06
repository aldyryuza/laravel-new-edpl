<?php

namespace Tests\Feature;

use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\CommercialParameter;
use App\Models\Dpl\DplItem;
use App\Models\Dpl\ProductPriceOverride;
use App\Models\OutletGroup;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductPriceOverrideImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Komersial DPL: harga khusus, skema bonus, parameter komersial.
 */
class CommercialMasterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'harga khusus' => ['special-prices', '/dpl/special-prices'],
            'skema bonus' => ['bonus-schemes', '/dpl/bonus-schemes'],
            'parameter komersial' => ['commercial-parameters', '/dpl/commercial-parameters'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_special_price_is_stored_with_uppercase_type_and_filtered_by_group(): void
    {
        $group = OutletGroup::factory()->create();
        $actor = $this->userWithAccess(['special-prices' => ['create', 'read']]);

        $this->actingAs($actor)
            ->postJson('/api/dpl/special-prices', [
                'price_type' => ' jkn ', 'outlet_group_id' => $group->id, 'product_id' => Product::factory()->create()->id,
                'price' => '9500.25', 'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.price_type', 'JKN')
            ->assertJsonPath('data.price', '9500.2500');

        ProductPriceOverride::factory()->create(['price_type' => 'PADMA']);

        $this->actingAs($actor)
            ->getJson('/api/dpl/special-prices/datatable?'.$this->dataTableQuery(['price_type', 'price'], ['outlet_group_id' => $group->id]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.outlet_group.id', $group->id);
    }

    public function test_special_price_import_upserts_and_validates(): void
    {
        Product::factory()->create(['code' => 'P-500']);
        OutletGroup::factory()->create(['code' => 'HERMINA']);
        $actor = $this->userWithAccess(['special-prices' => ['create', 'update']]);

        $this->importSpecial($actor, [
            ['jkn', 'HERMINA', 'P-500', 9000, '2026-01-01', '', '', 'harga JKN'],
            ['PADMA', '', 'P-500', '9500.5', '', '', '', ''],
        ])->assertOk()->assertJsonPath('data.created', 2);

        $this->importSpecial($actor, [['JKN', 'HERMINA', 'P-500', 9100, '2026-01-01', '', '', '']])
            ->assertOk()
            ->assertJsonPath('data.updated', 1);

        $this->assertSame('9100.0000', ProductPriceOverride::query()->where('price_type', 'JKN')->sole()->price);

        $error = $this->importSpecial($actor, [['', 'GRP-X', 'P-X', '-1', '', '', '', '']])->assertUnprocessable()->json('errors.file.0');

        foreach (['jenis_harga wajib diisi', 'kode_grup GRP-X tidak ditemukan', 'kode_product P-X tidak ditemukan', 'harga harus angka'] as $expected) {
            $this->assertStringContainsString($expected, $error);
        }
    }

    public function test_bonus_scheme_code_is_unique_and_used_scheme_cannot_be_deleted(): void
    {
        $actor = $this->userWithAccess(['bonus-schemes' => ['create', 'delete']]);
        $payload = ['code' => 'B5P1', 'name' => 'Beli 5 Bonus 1', 'buy_quantity' => '5', 'bonus_quantity' => '1', 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/dpl/bonus-schemes', $payload)->assertCreated()->assertJsonPath('data.buy_quantity', '5.0000');
        $this->actingAs($actor)->postJson('/api/dpl/bonus-schemes', $payload)->assertUnprocessable()->assertJsonValidationErrors('code');

        $scheme = BonusScheme::query()->where('code', 'B5P1')->sole();
        DplItem::factory()->create(['bonus_scheme_id' => $scheme->id]);

        $this->actingAs($actor)->deleteJson("/api/dpl/bonus-schemes/{$scheme->id}")->assertUnprocessable();
        $this->assertModelExists($scheme);
    }

    public function test_commercial_parameter_values_keep_their_type(): void
    {
        $actor = $this->userWithAccess(['commercial-parameters' => ['create', 'read']]);

        $this->actingAs($actor)
            ->postJson('/api/dpl/commercial-parameters', ['code' => 'MAX_DPL_DISCOUNT', 'value_type' => 'number', 'value' => '35.50', 'is_active' => true])
            ->assertCreated();
        $this->actingAs($actor)
            ->postJson('/api/dpl/commercial-parameters', ['code' => 'ALLOW_OVERRIDE', 'value_type' => 'boolean', 'value' => '0', 'is_active' => true])
            ->assertCreated();

        $discount = CommercialParameter::query()->where('code', 'MAX_DPL_DISCOUNT')->sole();
        $this->assertSame(CommercialParameter::TYPE_NUMBER, $discount->valueType());
        $this->assertSame('35.5', $discount->typedValue(), 'Angka disimpan sebagai string desimal, bukan float.');
        $this->assertFalse(CommercialParameter::query()->where('code', 'ALLOW_OVERRIDE')->sole()->typedValue());

        $this->actingAs($actor)
            ->getJson('/api/dpl/commercial-parameters/datatable?'.$this->dataTableQuery(['code', 'is_active'], ['search' => 'max_dpl']))
            ->assertJsonPath('data.0.value_type', 'number')
            ->assertJsonPath('data.0.typed_value', '35.5');
    }

    public function test_commercial_parameter_rejects_invalid_code_value_and_duplicate(): void
    {
        $actor = $this->userWithAccess(['commercial-parameters' => ['create']]);
        CommercialParameter::factory()->create(['code' => 'MIN_MONTHLY_ORDER']);

        $this->actingAs($actor)
            ->postJson('/api/dpl/commercial-parameters', ['code' => 'max discount', 'value_type' => 'number', 'value' => 'tiga puluh', 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'value']);

        $this->actingAs($actor)
            ->postJson('/api/dpl/commercial-parameters', ['code' => 'MIN_MONTHLY_ORDER', 'value_type' => 'number', 'value' => '200', 'is_active' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  urutan kolom = ProductPriceOverrideImportService::COLUMNS
     */
    private function importSpecial(User $user, array $rows): TestResponse
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([ProductPriceOverrideImportService::COLUMNS, ...$rows], null, 'A1', true);
        $path = tempnam(sys_get_temp_dir(), 'special').'.xlsx';
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($path);

        return $this->actingAs($user)->post(
            '/api/dpl/special-prices/import',
            ['file' => new UploadedFile($path, 'special.xlsx', null, null, true)],
            ['Accept' => 'application/json'],
        );
    }
}
