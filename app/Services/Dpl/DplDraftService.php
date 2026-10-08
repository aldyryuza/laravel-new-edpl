<?php

namespace App\Services\Dpl;

use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplStatusHistory;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Buat, ubah, dan hapus draft DPL beserta item dan commercial terms-nya.
 *
 * Nomor DPL dibuat saat draft dibuat (zona dari outlet -> area -> region -> zona). Outlet dan team
 * tidak bisa diganti setelah draft dibuat karena sudah menentukan nomor dan aturan 1 DPL aktif per outlet + team.
 * base_price menyimpan snapshot HNA (harga product yang berlaku pada Date Start, sesuai jenis harga item / price_type)
 * setiap kali draft disimpan. Harga nego dan konversi bonus tidak lagi diinput (kolom lama dibiarkan).
 * calculator_percentage = Total Diskon kalkulator (DplCalculator), dihitung ulang setiap draft disimpan.
 * final_price dan effective_discount belum diisi (rumusnya belum ada).
 */
class DplDraftService
{
    /** Kolom dpl_item_terms yang diinput user. */
    public const TERM_FIELDS = [
        'principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off',
        'price_type', 'margin', 'logistic_fee', 'logistic_support',
    ];

    /** Diskon & Support Logistic Satoria: kosong disimpan 0. */
    private const DISCOUNT_FIELDS = ['principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off', 'logistic_support'];

    /** Kolom dpl_items yang diinput user. */
    public const ITEM_FIELDS = [
        'product_id', 'distributor_id', 'minimum_order', 'bonus_scheme_id', 'is_direct_delivery', 'is_factory_pickup', 'is_buyer_reward',
        'is_support_satoria', 'is_returnable', 'remark',
    ];

    public function __construct(
        private DplNumberGenerator $numberGenerator,
        private DplPricing $pricing,
        private DplCalculator $calculator,
        private CommercialParameterReader $parameters,
    ) {}

    /**
     * Tanggal penawaran otomatis hari ini (zona waktu config('dpl.timezone')) dan tidak bisa diubah.
     *
     * @param  array<string, mixed>  $header  proposal_type, date_start, date_end, is_contract, description
     * @param  array<int, array<string, mixed>>  $items
     */
    public function create(User $user, Outlet $outlet, string $teamCode, array $header, array $items): DplProposal
    {
        return DB::transaction(function () use ($user, $outlet, $teamCode, $header, $items): DplProposal {
            $zoneCode = $outlet->area->region->zone->code;
            $today = now(config('dpl.timezone'));

            $proposal = DplProposal::query()->create([
                ...$header,
                'dpl_number' => $this->numberGenerator->next($zoneCode, $today),
                'date_offer' => $today->toDateString(),
                'outlet_id' => $outlet->id,
                'team_code' => $teamCode,
                'created_by_user_id' => $user->id,
                'bed_count_snapshot' => $outlet->bed_count,
                'bed_order_minimum' => $this->bedOrderMinimum($outlet->bed_count, $header['bed_order_minimum'] ?? null),
                'proposal_status' => DplProposal::STATUS_DRAFT,
                'program_status' => DplProposal::PROGRAM_INACTIVE,
            ]);

            DplStatusHistory::query()->create([
                'dpl_proposal_id' => $proposal->id,
                'status_type' => DplStatusHistory::TYPE_PROPOSAL,
                'old_status' => null,
                'new_status' => DplProposal::STATUS_DRAFT,
                'changed_by_user_id' => $user->id,
            ]);

            $this->syncItems($proposal, $items);

            return $proposal;
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array<string, mixed>>  $items  item dengan `id` diperbarui, tanpa `id` dibuat, yang tidak dikirim dihapus
     */
    public function update(DplProposal $proposal, array $header, array $items): DplProposal
    {
        return DB::transaction(function () use ($proposal, $header, $items): DplProposal {
            $bedCount = $proposal->outlet->bed_count;
            $proposal->update([
                ...$header,
                'bed_count_snapshot' => $bedCount,
                'bed_order_minimum' => $this->bedOrderMinimum($bedCount, $header['bed_order_minimum'] ?? null),
            ]);
            $this->syncItems($proposal, $items);

            return $proposal;
        });
    }

    /**
     * Draft dihapus (soft delete). Nomor DPL-nya tidak dipakai ulang.
     */
    public function delete(DplProposal $proposal): void
    {
        $proposal->delete();
    }

    /**
     * "25% Bed" (legacy order_min) = bed outlet × BED_ORDER_RATIO × BED_ORDER_DAYS (legacy 0,25 × 90), 2 desimal.
     * Bila bed outlet 0/kosong, dipakai nilai manual dari form.
     */
    public function bedOrderMinimum(?int $bedCount, mixed $manual): ?string
    {
        $ratio = $this->parameters->number('BED_ORDER_RATIO');
        $days = $this->parameters->number('BED_ORDER_DAYS');

        if ($bedCount !== null && $bedCount > 0 && $ratio !== null && $days !== null) {
            return number_format(round($bedCount * (float) $ratio * (float) $days, 2), 2, '.', '');
        }

        return $manual === null || $manual === '' ? null : (string) $manual;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(DplProposal $proposal, array $items): void
    {
        $existing = $proposal->items()->get()->keyBy('id');
        $keptIds = [];
        $prices = $this->pricing->pricesOn($proposal->date_start->toDateString(), array_map(fn (array $item) => (int) $item['product_id'], $items));

        $calculations = $this->calculator->evaluate($items, $proposal->team_code);

        foreach ($items as $index => $input) {
            $item = isset($input['id']) ? $existing->get((int) $input['id']) : null;
            $item ??= new DplItem(['dpl_proposal_id' => $proposal->id]);

            $item->fill(array_intersect_key($input, array_flip(self::ITEM_FIELDS)))->save();
            // base_price = snapshot HNA (jenis harga item) pada Date Start; null bila harga belum ada.
            $terms = $this->terms($input);
            $terms['price_type'] ??= DplPricing::defaultPriceType();
            $item->terms()->updateOrCreate([], [
                ...$terms,
                'base_price' => $prices[$item->product_id][$terms['price_type']] ?? null,
                // Total Diskon kalkulator (lihat DplCalculator).
                'calculator_percentage' => $calculations[$index]['total_discount'],
            ]);
            $keptIds[] = $item->id;
        }

        $existing->except($keptIds)->each(fn (DplItem $item) => $item->delete());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function terms(array $input): array
    {
        $terms = [];

        foreach (self::TERM_FIELDS as $field) {
            $value = $input[$field] ?? null;
            $terms[$field] = $value === null && in_array($field, self::DISCOUNT_FIELDS, true) ? 0 : $value;
        }

        return $terms;
    }
}
