<?php

namespace App\Services\Dpl;

use App\Models\City;
use App\Models\Distributor;
use App\Models\Dpl\DistributorLogisticRate;
use App\Models\Dpl\DistributorMarginOverride;
use App\Models\Dpl\DistributorMarginRule;
use App\Models\Dpl\ProductMarginOverride;
use App\Models\Outlet;
use App\Models\Product;

/**
 * Nilai awal Margin dan Logistic Fee item DPL (pengganti legacy ProductController::getSpecialMargin + addDplItem.js).
 * Hasilnya mengisi form; user tetap boleh mengubah. Semua angka bisnis dari parameter komersial.
 *
 * Margin (berurutan, langkah belakangan menimpa):
 * 1. Mode Group -> 0.
 * 2. Margin khusus product (grup distributor + product + kota distributor/semua kota); bila tidak ada:
 *    margin distributor (Tim B: margin_team_b bila diisi).
 * 3. Margin khusus outlet (grup distributor + outlet): distributor is_special_case_margin, team tidak dikecualikan,
 *    product bukan volume kecil.
 * 4. Direct: KFTD -> outlet INSTANSI / lainnya; non-KFTD + outlet INSTANSI -> provinsi Jawa / luar Jawa.
 *    Bukan Direct: override margin per product (team + grup, team, grup, semua). Tanpa pengurangan "nilai direct".
 *
 * Logistic Fee (hanya Direct; Ambil Dari Pabrik = 0; via distributor = kosong):
 * tarif khusus grup distributor + kota -> logistic fee kota distributor -> kota default; distributor nasional
 * -> tarif nasional; product volume kecil atau produk tim B -> dibagi pembagi.
 */
class DplItemDefaults
{
    public function __construct(private CommercialParameterReader $parameters) {}

    /**
     * @return array{margin: string|null, margin_source: string, logistic_fee: string|null, logistic_source: string}
     */
    public function resolve(
        Product $product,
        Distributor $distributor,
        ?Outlet $outlet,
        string $teamCode,
        bool $direct,
        bool $factoryPickup,
        bool $groupMode = false,
    ): array {
        $distributor->loadMissing('city.province');
        $outlet?->loadMissing('category');

        [$margin, $marginSource] = $groupMode
            ? ['0', 'Mode Group']
            : $this->margin($product, $distributor, $outlet, mb_strtoupper($teamCode), $direct);

        [$logisticFee, $logisticSource] = $this->logisticFee($product, $distributor, $direct, $factoryPickup);

        return [
            'margin' => $margin,
            'margin_source' => $marginSource,
            'logistic_fee' => $logisticFee,
            'logistic_source' => $logisticSource,
        ];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function margin(Product $product, Distributor $distributor, ?Outlet $outlet, string $team, bool $direct): array
    {
        $rule = $distributor->group_code === null ? null : DistributorMarginRule::query()
            ->where('is_active', true)
            ->where('distributor_group_code', $distributor->group_code)
            ->where('product_id', $product->id)
            ->where(fn ($query) => $query->whereNull('city_id')->when($distributor->city_id, fn ($query, int $cityId) => $query->orWhere('city_id', $cityId)))
            ->orderByRaw('city_id IS NULL')
            ->first();

        if ($rule !== null) {
            [$margin, $source] = [$rule->margin, 'Margin khusus product'];
        } elseif ($team === 'B' && $distributor->margin_team_b !== null) {
            [$margin, $source] = [$distributor->margin_team_b, 'Margin Tim B distributor'];
        } else {
            [$margin, $source] = [$distributor->margin, 'Margin distributor'];
        }

        if ($outlet !== null && $distributor->is_special_case_margin && $distributor->group_code !== null && ! $this->isSmallVolume($product)
            && ! in_array($team, $this->parameters->upperList('OUTLET_MARGIN_EXCLUDED_TEAMS'), true)) {
            $override = DistributorMarginOverride::query()
                ->where('is_active', true)
                ->where('distributor_group_code', $distributor->group_code)
                ->where('outlet_id', $outlet->id)
                ->first();

            if ($override !== null) {
                [$margin, $source] = [$override->margin, 'Margin khusus outlet'];
            }
        }

        return $direct ? $this->directMargin($distributor, $outlet, $margin, $source) : $this->productOverride($product, $distributor, $team, $margin, $source);
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function directMargin(Distributor $distributor, ?Outlet $outlet, ?string $margin, string $source): array
    {
        $isInstansi = $outlet?->category?->code !== null
            && mb_strtoupper($outlet->category->code) === mb_strtoupper((string) $this->parameters->text('INSTANSI_CATEGORY_CODE'));
        $isKftd = $distributor->group_code !== null
            && mb_strtoupper($distributor->group_code) === mb_strtoupper((string) $this->parameters->text('KFTD_DISTRIBUTOR_GROUP'));

        if ($isKftd) {
            $code = $isInstansi ? 'DIRECT_MARGIN_KFTD_INSTANSI' : 'DIRECT_MARGIN_KFTD_OTHER';

            return [$this->parameters->number($code) ?? $margin, 'Direct KFTD'.($isInstansi ? ' - outlet Instansi' : '')];
        }

        if ($isInstansi) {
            $isJava = (bool) $distributor->city?->province?->is_java;
            $code = $isJava ? 'DIRECT_MARGIN_INSTANSI_JAVA' : 'DIRECT_MARGIN_INSTANSI_OUTSIDE_JAVA';

            return [$this->parameters->number($code) ?? $margin, 'Direct outlet Instansi '.($isJava ? 'Jawa' : 'luar Jawa')];
        }

        return [$margin, $source];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function productOverride(Product $product, Distributor $distributor, string $team, ?string $margin, string $source): array
    {
        $group = $distributor->group_code === null ? null : mb_strtoupper($distributor->group_code);

        $override = ProductMarginOverride::query()
            ->where('is_active', true)
            ->where('product_id', $product->id)
            ->get()
            ->filter(fn (ProductMarginOverride $row) => ($row->team_code === null || mb_strtoupper($row->team_code) === $team)
                && ($row->distributor_group_code === null || mb_strtoupper($row->distributor_group_code) === $group))
            // Paling spesifik dulu: team + grup, team, grup, semua.
            ->sortByDesc(fn (ProductMarginOverride $row) => ($row->team_code !== null ? 2 : 0) + ($row->distributor_group_code !== null ? 1 : 0))
            ->first();

        return $override === null ? [$margin, $source] : [$override->margin, 'Override margin product'];
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private function logisticFee(Product $product, Distributor $distributor, bool $direct, bool $factoryPickup): array
    {
        if ($factoryPickup) {
            return ['0', 'Ambil Dari Pabrik'];
        }

        if (! $direct) {
            return [null, 'Hanya untuk Direct'];
        }

        $city = $distributor->city ?? $this->defaultCity();
        $rate = $distributor->group_code === null || $city === null ? null : DistributorLogisticRate::query()
            ->where('is_active', true)
            ->where('distributor_group_code', $distributor->group_code)
            ->where('city_id', $city->id)
            ->first();

        if ($rate !== null) {
            [$fee, $source] = [$rate->logistic_fee, 'Tarif logistic distributor'];
        } else {
            $fee = $city?->logistic_fee ?? $this->defaultCity()?->logistic_fee;
            $source = $city?->logistic_fee !== null ? "Logistic kota {$city->name}" : 'Logistic kota default';
        }

        if ($distributor->group_code !== null && in_array(mb_strtoupper($distributor->group_code), $this->parameters->upperList('NATIONAL_DISTRIBUTOR_GROUPS'), true)) {
            [$fee, $source] = [$this->parameters->number('NATIONAL_DISTRIBUTOR_LOGISTIC_FEE') ?? $fee, 'Distributor nasional'];
        }

        $divisor = (float) ($this->parameters->number('SPECIAL_LOGISTIC_DIVISOR') ?? 0);

        if ($fee !== null && $divisor > 0 && ($this->isSmallVolume($product) || $product->is_team_b_product)) {
            [$fee, $source] = [number_format(round((float) $fee / $divisor, 2), 2, '.', ''), "{$source} ÷ ".rtrim(rtrim((string) $divisor, '0'), '.')];
        }

        return [$fee, $source];
    }

    private function isSmallVolume(Product $product): bool
    {
        $volume = $this->parameters->number('SMALL_VOLUME_ML');

        return $volume !== null && $product->volume !== null && (float) $product->volume === (float) $volume;
    }

    private function defaultCity(): ?City
    {
        $code = $this->parameters->text('DEFAULT_LOGISTIC_CITY_CODE');

        return $code === null ? null : City::query()->where('code', $code)->first();
    }
}
