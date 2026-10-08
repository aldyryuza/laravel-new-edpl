<?php

namespace Tests\Feature;

use App\Models\City;
use App\Models\Distributor;
use App\Models\Dpl\DistributorLogisticRate;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Dpl\DistributorMarginRule;
use App\Models\Dpl\ProductMarginOverride;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Komersial DPL: margin khusus product, margin khusus outlet, override margin product, tarif logistic distributor.
 */
class DplMarginRulePagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function pages(): array
    {
        return [
            'margin khusus product' => ['distributor-margin-rules', '/dpl/distributor-margin-rules'],
            'margin khusus outlet' => ['distributor-margin-overrides', '/dpl/distributor-margin-overrides'],
            'override margin product' => ['product-margin-overrides', '/dpl/product-margin-overrides'],
            'tarif logistic distributor' => ['distributor-logistic-rates', '/dpl/distributor-logistic-rates'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_requires_read_access(string $menu, string $url): void
    {
        $this->actingAs($this->userWithAccess([]))->get($url)->assertForbidden();

        $this->actingAs($this->userWithAccess([$menu => ['read']]))->get($url)->assertOk();
    }

    public function test_distributor_margin_rule_crud_and_uniqueness(): void
    {
        $actor = $this->userWithAccess(['distributor-margin-rules' => ['create', 'read', 'update', 'delete']]);
        $product = Product::factory()->create();
        $city = City::factory()->create();
        $payload = ['distributor_group_code' => ' grp-a ', 'product_id' => $product->id, 'city_id' => '', 'margin' => '7.5', 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/dpl/distributor-margin-rules', $payload)
            ->assertCreated()
            ->assertJsonPath('data.distributor_group_code', 'GRP-A')
            ->assertJsonPath('data.city_id', null)
            ->assertJsonPath('data.margin', '7.5000');

        // "Semua kota" (null) hanya boleh satu per grup + product.
        $this->actingAs($actor)->postJson('/api/dpl/distributor-margin-rules', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('distributor_group_code');

        $this->actingAs($actor)->postJson('/api/dpl/distributor-margin-rules', [...$payload, 'city_id' => $city->id])->assertCreated();
        $this->actingAs($actor)->postJson('/api/dpl/distributor-margin-rules', [...$payload, 'city_id' => $city->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('distributor_group_code');

        $this->actingAs($actor)
            ->postJson('/api/dpl/distributor-margin-rules', [...$payload, 'product_id' => Product::factory()->create(['is_active' => false])->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $rule = DistributorMarginRule::query()->whereNull('city_id')->sole();

        $this->actingAs($actor)->putJson("/api/dpl/distributor-margin-rules/{$rule->id}", [...$payload, 'margin' => '8', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.margin', '8.0000')
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($actor)
            ->getJson('/api/dpl/distributor-margin-rules/datatable?'.$this->dataTableQuery(['distributor_group_code', 'margin'], ['search' => $product->code]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 2);

        $this->actingAs($actor)->deleteJson("/api/dpl/distributor-margin-rules/{$rule->id}")->assertOk();
        $this->assertModelMissing($rule);
    }

    public function test_distributor_margin_override_crud_and_uniqueness(): void
    {
        $actor = $this->userWithAccess(['distributor-margin-overrides' => ['create', 'read', 'update', 'delete']]);
        $distributor = Distributor::factory()->create(['is_special_case_margin' => false]);
        $outlet = Outlet::factory()->create();
        $payload = ['distributor_id' => $distributor->id, 'outlet_id' => $outlet->id, 'margin' => '6', 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/dpl/distributor-margin-overrides', $payload)
            ->assertCreated()
            ->assertJsonPath('data.margin', '6.0000');

        $this->actingAs($actor)->postJson('/api/dpl/distributor-margin-overrides', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('outlet_id');

        $override = DistributorMarginOverride::query()->sole();

        $this->actingAs($actor)
            ->getJson('/api/dpl/distributor-margin-overrides/datatable?'.$this->dataTableQuery(['margin'], ['search' => $outlet->code]))
            ->assertOk()
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonPath('data.0.distributor.is_special_case_margin', false);

        $this->actingAs($actor)->putJson("/api/dpl/distributor-margin-overrides/{$override->id}", [...$payload, 'margin' => '4.25'])
            ->assertOk()
            ->assertJsonPath('data.margin', '4.2500');

        $this->actingAs($actor)->deleteJson("/api/dpl/distributor-margin-overrides/{$override->id}")->assertOk();
        $this->assertModelMissing($override);
    }

    public function test_outlet_options_return_matching_active_outlets(): void
    {
        $actor = $this->userWithAccess(['distributor-margin-overrides' => ['read']]);
        $match = Outlet::factory()->create(['code' => 'OUT-MATCH1', 'name' => 'RS Harapan']);
        Outlet::factory()->create(['code' => 'OUT-MATCH2', 'name' => 'RS Lama', 'is_active' => false]);
        Outlet::factory()->create(['code' => 'OUT-OTHER', 'name' => 'Klinik Sehat']);

        $this->actingAs($actor)->getJson('/api/dpl/distributor-margin-overrides/outlet-options?q=match')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('data.0.text', 'RS Harapan (OUT-MATCH1)');

        $this->actingAs($this->userWithAccess([]))->getJson('/api/dpl/distributor-margin-overrides/outlet-options')->assertForbidden();
    }

    public function test_product_margin_override_crud_and_uniqueness(): void
    {
        $actor = $this->userWithAccess(['product-margin-overrides' => ['create', 'update', 'delete']]);
        $product = Product::factory()->create();
        $team = Team::factory()->create();
        $payload = ['product_id' => $product->id, 'distributor_group_code' => '', 'team_code' => '', 'margin' => '10', 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/dpl/product-margin-overrides', $payload)
            ->assertCreated()
            ->assertJsonPath('data.distributor_group_code', null)
            ->assertJsonPath('data.team_code', null);

        // Semua grup + semua team (null + null) hanya boleh satu per product.
        $this->actingAs($actor)->postJson('/api/dpl/product-margin-overrides', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $scoped = [...$payload, 'distributor_group_code' => 'grp-b', 'team_code' => $team->code];

        $this->actingAs($actor)->postJson('/api/dpl/product-margin-overrides', $scoped)
            ->assertCreated()
            ->assertJsonPath('data.distributor_group_code', 'GRP-B');
        $this->actingAs($actor)->postJson('/api/dpl/product-margin-overrides', $scoped)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');

        $override = ProductMarginOverride::query()->whereNull('team_code')->sole();

        $this->actingAs($actor)->putJson("/api/dpl/product-margin-overrides/{$override->id}", [...$payload, 'margin' => '12.5'])
            ->assertOk()
            ->assertJsonPath('data.margin', '12.5000');

        $this->actingAs($actor)->deleteJson("/api/dpl/product-margin-overrides/{$override->id}")->assertOk();
        $this->assertModelMissing($override);
    }

    public function test_distributor_logistic_rate_crud_and_uniqueness(): void
    {
        $actor = $this->userWithAccess(['distributor-logistic-rates' => ['create', 'update', 'delete']]);
        $city = City::factory()->create();
        $payload = ['distributor_group_code' => 'grp-c', 'city_id' => $city->id, 'logistic_fee' => '4', 'is_active' => true];

        $this->actingAs($actor)->postJson('/api/dpl/distributor-logistic-rates', $payload)
            ->assertCreated()
            ->assertJsonPath('data.distributor_group_code', 'GRP-C')
            ->assertJsonPath('data.logistic_fee', '4.0000');

        $this->actingAs($actor)->postJson('/api/dpl/distributor-logistic-rates', [...$payload, 'distributor_group_code' => 'GRP-C'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('distributor_group_code');

        $rate = DistributorLogisticRate::query()->sole();

        $this->actingAs($actor)->putJson("/api/dpl/distributor-logistic-rates/{$rate->id}", [...$payload, 'logistic_fee' => '3.5'])
            ->assertOk()
            ->assertJsonPath('data.logistic_fee', '3.5000');

        $this->actingAs($actor)->deleteJson("/api/dpl/distributor-logistic-rates/{$rate->id}")->assertOk();
        $this->assertModelMissing($rate);
    }
}
