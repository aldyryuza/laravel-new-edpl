<?php

namespace App\Services\Dpl;

use App\Models\Dpl\CommercialParameter;
use App\Models\Dpl\ProductPrice;

/**
 * Harga acuan item DPL: HNA = product_prices.price yang berlaku pada Date Start DPL, dan PPN dari
 * parameter komersial PPN_PERCENT. Nilai turunan (HNA - Disc On, HNA+PPN - Disc On) dihitung untuk tampilan:
 * HNA × (1 + PPN%) × (1 − (Disc Prinsipal On + Disc Distributor On)%).
 */
class DplPricing
{
    public const PPN_PARAMETER = 'PPN_PERCENT';

    /**
     * HNA per product yang berlaku pada tanggal tersebut. Bila ada beberapa harga berlaku, dipakai yang valid_from terbaru.
     *
     * @param  array<int, int>|null  $productIds  null = semua product
     * @return array<int, string> product_id => harga (string desimal)
     */
    public function hnaOn(string $date, ?array $productIds = null): array
    {
        return ProductPrice::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $date))
            ->when($productIds !== null, fn ($query) => $query->whereIn('product_id', $productIds))
            ->orderBy('product_id')
            ->orderByRaw('valid_from DESC NULLS LAST')
            ->orderByDesc('id')
            ->get(['product_id', 'price'])
            ->unique('product_id')
            ->mapWithKeys(fn (ProductPrice $price) => [$price->product_id => $price->price])
            ->all();
    }

    /**
     * Persentase PPN aktif (string desimal), null bila parameter belum diatur.
     */
    public function ppnPercent(): ?string
    {
        $value = CommercialParameter::query()->where('code', self::PPN_PARAMETER)->where('is_active', true)->first()?->typedValue();

        return is_string($value) ? $value : null;
    }
}
