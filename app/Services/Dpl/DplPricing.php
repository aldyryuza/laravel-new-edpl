<?php

namespace App\Services\Dpl;

use App\Models\Dpl\CommercialParameter;
use App\Models\Dpl\ProductPrice;

/**
 * Harga acuan item DPL: HNA = harga product yang berlaku pada Date Start DPL, kolomnya sesuai jenis harga
 * item (config dpl.price_types, mis. PRICE_BARU_2026 = price, PRICE_LAMA_2025 = regular_price), dan PPN dari
 * parameter komersial PPN_PERCENT. Nilai turunan untuk tampilan (dibulatkan ke rupiah, setengah ke atas):
 * HNA × (1 + PPN%) × (1 − (Disc Principle ON + Disc Distributor ON)%).
 */
class DplPricing
{
    public const PPN_PARAMETER = 'PPN_PERCENT';

    /**
     * @return array<string, string> kode jenis harga => kolom product_prices
     */
    public static function priceTypes(): array
    {
        return config('dpl.price_types');
    }

    public static function defaultPriceType(): string
    {
        return config('dpl.default_price_type');
    }

    /**
     * Apakah jenis harga boleh dipakai untuk product dengan volume tersebut (config dpl.price_type_volumes).
     */
    public static function allowsVolume(string $priceType, int|float|string|null $volume): bool
    {
        $volumes = config("dpl.price_type_volumes.{$priceType}");

        if ($volumes === null) {
            return true;
        }

        return $volume !== null && in_array((float) $volume, array_map('floatval', $volumes), true);
    }

    /**
     * Harga per product yang berlaku pada tanggal tersebut, per jenis harga. Bila ada beberapa baris berlaku,
     * dipakai yang valid_from terbaru.
     *
     * @param  array<int, int>|null  $productIds  null = semua product
     * @return array<int, array<string, string|null>> product_id => [kode jenis harga => harga (string desimal) atau null]
     */
    public function pricesOn(string $date, ?array $productIds = null): array
    {
        $types = self::priceTypes();

        return ProductPrice::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $date))
            ->when($productIds !== null, fn ($query) => $query->whereIn('product_id', $productIds))
            ->orderBy('product_id')
            ->orderByRaw('valid_from DESC NULLS LAST')
            ->orderByDesc('id')
            ->get(['product_id', ...array_unique(array_values($types))])
            ->unique('product_id')
            ->mapWithKeys(fn (ProductPrice $price) => [
                $price->product_id => array_map(fn (string $column) => $price->{$column}, $types),
            ])
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
