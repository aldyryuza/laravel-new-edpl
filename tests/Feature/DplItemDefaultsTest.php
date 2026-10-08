<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Distributor;
use App\Models\Dpl\DistributorLogisticRate;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Dpl\DistributorMarginRule;
use App\Models\Dpl\ProductMarginOverride;
use App\Models\Outlet;
use App\Models\OutletCategory;
use App\Models\Product;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\CommercialParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Nilai awal Margin & Logistic Fee item DPL (DplItemDefaults) + batas kalkulator per product.
 * Angka bisnis dari CommercialParameterSeeder (KFTD 9/11, Instansi Jawa 3 / luar Jawa 5, nasional 6, ÷3, kota default 3216).
 */
class DplItemDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private City $javaCity;

    private City $outerCity;

    private Distributor $local;

    private Distributor $kftd;

    private Outlet $instansi;

    private Outlet $hospital;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CommercialParameterSeeder::class);
        $this->user = $this->userWithAccess(['dpl-proposals' => ['read']]);

        $this->javaCity = City::factory()->for(Province::factory()->create(['is_java' => true]))->create(['logistic_fee' => '3.5']);
        $this->outerCity = City::factory()->for(Province::factory()->create(['is_java' => false]))->create(['logistic_fee' => '8.6']);

        // AMJ = distributor lokal (bukan nasional), supaya logistic fee kota bisa diuji.
        $this->local = Distributor::factory()->create(['group_code' => 'AMJ', 'city_id' => $this->javaCity->id, 'margin' => '8', 'margin_team_b' => '6']);
        $this->kftd = Distributor::factory()->create(['group_code' => 'KFTD', 'city_id' => $this->javaCity->id, 'margin' => '10']);

        $this->instansi = Outlet::factory()->create(['outlet_category_id' => OutletCategory::factory()->create(['code' => 'INSTANSI'])->id]);
        $this->hospital = Outlet::factory()->create(['outlet_category_id' => OutletCategory::factory()->create(['code' => 'RUMAH_SAKIT'])->id]);
        $this->product = Product::factory()->create(['volume' => 500]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function defaults(array $query = []): TestResponse
    {
        return $this->actingAs($this->user)->getJson('/api/dpl/proposals/item-defaults?'.http_build_query([
            'product_id' => $this->product->id,
            'distributor_id' => $this->local->id,
            'outlet_id' => $this->hospital->id,
            'team_code' => 'A',
            'is_direct_delivery' => 0,
            'is_factory_pickup' => 0,
            ...$query,
        ]))->assertOk();
    }

    private function margin(array $query = []): ?string
    {
        $margin = $this->defaults($query)->json('data.margin');

        return $margin === null ? null : number_format((float) $margin, 2, '.', '');
    }

    private function logisticFee(array $query = []): ?string
    {
        $fee = $this->defaults(['is_direct_delivery' => 1, ...$query])->json('data.logistic_fee');

        return $fee === null ? null : number_format((float) $fee, 2, '.', '');
    }

    public function test_base_margin_team_b_and_product_rule_precedence(): void
    {
        $this->assertSame('8.00', $this->margin());
        $this->assertSame('6.00', $this->margin(['team_code' => 'B']));

        // Margin khusus product (semua kota) menang atas margin distributor, termasuk Tim B.
        DistributorMarginRule::factory()->create(['distributor_group_code' => 'AMJ', 'product_id' => $this->product->id, 'margin' => '7']);
        $this->assertSame('7.00', $this->margin());
        $this->assertSame('7.00', $this->margin(['team_code' => 'B']));

        // Aturan untuk kota distributor menang atas aturan semua kota.
        DistributorMarginRule::factory()->create(['distributor_group_code' => 'AMJ', 'product_id' => $this->product->id, 'city_id' => $this->javaCity->id, 'margin' => '9']);
        $this->assertSame('9.00', $this->margin());

        $this->defaults(['group_mode' => 1])->assertJsonPath('data.margin', '0')->assertJsonPath('data.margin_source', 'Mode Group');
    }

    public function test_outlet_margin_needs_flag_and_skips_excluded_team_and_small_volume(): void
    {
        DistributorMarginOverride::factory()->create(['distributor_group_code' => 'AMJ', 'outlet_id' => $this->hospital->id, 'margin' => '5']);
        $this->assertSame('8.00', $this->margin(), 'Distributor belum ditandai margin khusus outlet.');

        $this->local->update(['is_special_case_margin' => true]);
        $this->assertSame('5.00', $this->margin());
        $this->assertSame('6.00', $this->margin(['team_code' => 'B']), 'Tim B dikecualikan.');

        $small = Product::factory()->create(['volume' => 25]);
        $this->assertSame('8.00', $this->margin(['product_id' => $small->id]), 'Product 25ml tidak memakai margin outlet.');
    }

    public function test_direct_margin_rules_for_kftd_and_instansi(): void
    {
        $this->assertSame('9.00', $this->margin(['distributor_id' => $this->kftd->id, 'outlet_id' => $this->instansi->id, 'is_direct_delivery' => 1]));
        $this->assertSame('11.00', $this->margin(['distributor_id' => $this->kftd->id, 'is_direct_delivery' => 1]));
        $this->assertSame('3.00', $this->margin(['outlet_id' => $this->instansi->id, 'is_direct_delivery' => 1]));
        $this->assertSame('8.00', $this->margin(['is_direct_delivery' => 1]), 'Non-KFTD ke outlet non-Instansi: margin apa adanya.');

        $this->local->update(['city_id' => $this->outerCity->id]);
        $this->assertSame('5.00', $this->margin(['outlet_id' => $this->instansi->id, 'is_direct_delivery' => 1]));
    }

    public function test_product_override_priority_applies_only_to_non_direct(): void
    {
        $override = fn (array $attributes) => ProductMarginOverride::factory()->create(['product_id' => $this->product->id, ...$attributes]);

        $override(['margin' => '10']);
        $this->assertSame('10.00', $this->margin());

        $override(['distributor_group_code' => 'AMJ', 'margin' => '11']);
        $this->assertSame('11.00', $this->margin());

        $override(['team_code' => 'A', 'margin' => '12']);
        $this->assertSame('12.00', $this->margin(), 'Team menang atas grup.');
        $this->assertSame('11.00', $this->margin(['team_code' => 'B']));

        $override(['team_code' => 'A', 'distributor_group_code' => 'AMJ', 'margin' => '13']);
        $this->assertSame('13.00', $this->margin());

        $this->assertSame('8.00', $this->margin(['is_direct_delivery' => 1]), 'Direct tidak memakai override.');
    }

    public function test_logistic_fee_rules(): void
    {
        $this->defaults()->assertJsonPath('data.logistic_fee', '3.5000');
        $this->defaults(['is_factory_pickup' => 1])->assertJsonPath('data.logistic_fee', '0');
        $this->assertSame('3.50', $this->logisticFee());

        DistributorLogisticRate::factory()->create(['distributor_group_code' => 'AMJ', 'city_id' => $this->javaCity->id, 'logistic_fee' => '4.5']);
        $this->assertSame('4.50', $this->logisticFee());

        $this->assertSame('6.00', $this->logisticFee(['distributor_id' => $this->kftd->id]), 'KFTD distributor nasional.');
        $this->assertSame('2.00', $this->logisticFee(['distributor_id' => $this->kftd->id, 'product_id' => Product::factory()->create(['volume' => 25])->id]));
        $this->assertSame('1.50', $this->logisticFee(['product_id' => Product::factory()->create(['volume' => null, 'is_team_b_product' => true])->id]));

        // Distributor tanpa kota -> kota default (kode 3216).
        City::factory()->create(['code' => '3216', 'logistic_fee' => '4']);
        $noCity = Distributor::factory()->create(['group_code' => 'LOKAL', 'city_id' => null]);
        $this->assertSame('4.00', $this->logisticFee(['distributor_id' => $noCity->id]));
    }

    public function test_calculator_limit_per_product(): void
    {
        $special = Product::factory()->create(['calculator_limit' => '20']);
        $teamBProduct = Product::factory()->create(['is_team_b_product' => true]);
        $item = ['margin' => '25'];

        $this->actingAs($this->user)->postJson('/api/dpl/proposals/calculate', ['team_code' => 'A', 'items' => [
            [...$item, 'product_id' => $this->product->id],
            [...$item, 'product_id' => $special->id],
            [...$item, 'product_id' => $teamBProduct->id],
        ]])
            ->assertOk()
            ->assertJsonPath('data.0.limit', '30')
            ->assertJsonPath('data.1.limit', '20.0000')
            ->assertJsonPath('data.1.exceeds_limit', true)
            ->assertJsonPath('data.2.limit', '45')
            ->assertJsonPath('data.2.exceeds_limit', false);
    }

    public function test_distributor_outside_user_scope_is_not_found(): void
    {
        $outsider = $this->userWithAccess(['dpl-proposals' => ['read']], national: false);

        $this->actingAs($outsider)->getJson('/api/dpl/proposals/item-defaults?'.http_build_query([
            'product_id' => $this->product->id, 'distributor_id' => $this->local->id, 'team_code' => 'A',
        ]))->assertNotFound();
    }
}
