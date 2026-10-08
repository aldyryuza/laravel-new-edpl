<?php

namespace App\Services\Dpl;

use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\CommercialParameter;
use App\Models\Product;

/**
 * Kalkulator DPL (legacy FunctionHelper). Satu-satunya implementasi rumus: form memanggil endpoint
 * api/dpl/proposals/calculate, dan hasil yang sama disimpan ke dpl_item_terms.calculator_percentage
 * (legacy punya rumus JS dan PHP yang berbeda untuk item Support; di sini hanya satu).
 *
 * Total Diskon (%) = Disc Principle ON + Disc Principle OFF + Bonus (total_discount skema bonus)
 *                  + Logistic Fee (Ambil Dari Pabrik = 0) + Margin + Support Logistic Satoria
 *                  − (Disc Distributor ON + Disc Distributor OFF) bila Support Satoria dicentang.
 * Tanpa Support, diskon distributor tidak memengaruhi total. Hasil dibulatkan 2 desimal (setengah ke atas).
 *
 * Batas per item (berurutan): batas khusus product (products.calculator_limit) -> produk tim B (is_team_b_product)
 * memakai batas Tim B -> batas team dari parameter MAX_DPL_DISCOUNT_TEAM_{kode team} (legacy Tim A 30, Tim B 45).
 * Margin & logistic fee yang dijumlah adalah nilai item (diisi otomatis oleh DplItemDefaults, bisa diubah user).
 *
 * Perhitungan memakai integer satuan 0,0001 agar tidak ada galat float.
 */
class DplCalculator
{
    public const LIMIT_PARAMETER_PREFIX = 'MAX_DPL_DISCOUNT_TEAM_';

    /** Skala internal: 4 desimal (presisi kolom numeric(10,4)). */
    private const SCALE = 10000;

    /**
     * @param  array<int, array<string, mixed>>  $items  terms + flag item (lihat totalDiscount()) + product_id (untuk batas)
     * @return array<int, array{total_discount: string, limit: string|null, exceeds_limit: bool}>
     */
    public function evaluate(array $items, string $teamCode): array
    {
        $teamLimit = $this->limitFor($teamCode);
        $teamBLimit = $this->limitFor('B');
        $bonus = $this->bonusPercents($items);
        $products = Product::query()
            ->whereKey(array_values(array_unique(array_filter(array_map(fn (array $item) => (int) ($item['product_id'] ?? 0), $items)))))
            ->get(['id', 'is_team_b_product', 'calculator_limit'])
            ->keyBy('id');

        return array_map(function (array $item) use ($teamLimit, $teamBLimit, $bonus, $products): array {
            $total = $this->totalDiscount($item, $bonus[(int) ($item['bonus_scheme_id'] ?? 0)] ?? null);
            $product = $products->get((int) ($item['product_id'] ?? 0));
            $limit = match (true) {
                $product?->calculator_limit !== null => $product->calculator_limit,
                (bool) $product?->is_team_b_product => $teamBLimit,
                default => $teamLimit,
            };

            return [
                'total_discount' => $total,
                'limit' => $limit,
                'exceeds_limit' => $limit !== null && $this->units($total) > $this->units($limit),
            ];
        }, $items);
    }

    /**
     * @param  array<string, mixed>  $item  principle_discount_on/off, distributor_discount_on/off, margin, logistic_fee,
     *                                      logistic_support, is_support_satoria, is_direct_delivery, is_factory_pickup
     * @param  string|null  $bonusPercent  total_discount skema bonus item
     */
    public function totalDiscount(array $item, ?string $bonusPercent): string
    {
        // Logistic fee berlaku untuk semua item; Ambil Dari Pabrik = 0 (legacy addDplItem.js).
        $logisticFee = $this->flag($item['is_factory_pickup'] ?? false) ? 0 : $this->units($item['logistic_fee'] ?? null);

        $total = $this->units($item['principle_discount_on'] ?? null)
            + $this->units($item['principle_discount_off'] ?? null)
            + $this->units($bonusPercent)
            + $logisticFee
            + $this->units($item['margin'] ?? null)
            + $this->units($item['logistic_support'] ?? null);

        if ($this->flag($item['is_support_satoria'] ?? false)) {
            $total -= $this->units($item['distributor_discount_on'] ?? null) + $this->units($item['distributor_discount_off'] ?? null);
        }

        return $this->roundTo2($total);
    }

    /**
     * Batas Total Diskon untuk team (string desimal), null bila parameter belum diatur.
     */
    public function limitFor(string $teamCode): ?string
    {
        $value = CommercialParameter::query()
            ->where('code', self::LIMIT_PARAMETER_PREFIX.mb_strtoupper($teamCode))
            ->where('is_active', true)
            ->first()
            ?->typedValue();

        return is_string($value) && is_numeric($value) ? $value : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string> bonus_scheme_id => total_discount
     */
    private function bonusPercents(array $items): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn (array $item) => (int) ($item['bonus_scheme_id'] ?? 0), $items))));

        if ($ids === []) {
            return [];
        }

        return BonusScheme::query()->whereKey($ids)->pluck('total_discount', 'id')->map(fn ($value) => (string) $value)->all();
    }

    /**
     * Angka desimal (string/int/float, maks. 4 desimal) -> integer satuan 0,0001. Kosong = 0.
     */
    private function units(mixed $value): int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0;
        }

        $text = is_float($value) ? number_format($value, 4, '.', '') : trim((string) $value);
        $negative = str_starts_with($text, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($text, '+-'), 2), 2, '');
        $units = (int) $whole * self::SCALE + (int) str_pad(substr($fraction, 0, 4), 4, '0');

        return $negative ? -$units : $units;
    }

    /**
     * Integer satuan 0,0001 -> string 2 desimal, dibulatkan setengah ke atas (menjauhi nol).
     */
    private function roundTo2(int $units): string
    {
        $cents = intdiv(abs($units) + 50, 100);
        $text = intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);

        return $units < 0 && $cents > 0 ? "-{$text}" : $text;
    }

    private function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
