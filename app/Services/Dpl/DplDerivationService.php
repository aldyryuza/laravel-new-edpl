<?php

namespace App\Services\Dpl;

use App\Models\Dpl\DplItem;
use App\Models\Dpl\DplProposal;
use App\Models\Dpl\DplRelation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Make a Copy (AMENDMENT) dan Duplicate (RENEWAL) dari DPL yang sudah approved (legacy createFrom / duplicateForm).
 *
 * - Hanya pemilik DPL sumber; DPL baru berupa draft milik pemilik yang sama, nomor baru, relasi di dpl_relations.
 * - AMENDMENT: sumber harus program ACTIVE & periodenya berjalan; periode baru dikunci hari ini s/d Date End sumber.
 * - RENEWAL: sumber APPROVED (program apa pun); periode dipilih (tidak backdate, maks. 1 tahun).
 * - Item disalin lalu margin & logistic fee dihitung ulang dengan aturan terbaru (DplItemDefaults); logistic fee item
 *   Direct (input manual) dipertahankan. Item yang tetap sama persis dengan sumber mempertahankan approval saat
 *   amendment diajukan (lihat DplApprovalService::submit); item berubah / baru melewati approval ulang.
 * - Sumber tidak diubah; saat DPL baru aktif, sumber (aktif) menjadi SUPERSEDED.
 */
class DplDerivationService
{
    public function __construct(
        private DplDraftService $drafts,
        private DplItemDefaults $defaults,
    ) {}

    /**
     * @throws ValidationException
     */
    public function amend(DplProposal $source, User $user): DplProposal
    {
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();
        $this->authorizeSource($source, $user);

        if ($source->program_status !== DplProposal::PROGRAM_ACTIVE
            || $source->date_start->toDateString() > $today || $source->date_end->toDateString() < $today) {
            throw ValidationException::withMessages(['proposal' => 'Make a Copy hanya untuk DPL yang program-nya aktif dan periodenya sedang berjalan.']);
        }

        return $this->derive($source, $user, DplRelation::TYPE_AMENDMENT, $today, $source->date_end->toDateString());
    }

    /**
     * @throws ValidationException
     */
    public function renew(DplProposal $source, User $user, string $dateStart, string $dateEnd): DplProposal
    {
        $this->authorizeSource($source, $user);
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();
        $start = CarbonImmutable::parse($dateStart);
        $end = CarbonImmutable::parse($dateEnd);

        if ($start->toDateString() < $today) {
            throw ValidationException::withMessages(['date_start' => 'Date Start tidak boleh tanggal yang sudah lewat.']);
        }

        if ($end->lessThan($start) || $end->greaterThan($start->addYear())) {
            throw ValidationException::withMessages(['date_end' => 'Date End harus setelah Date Start dan maksimal satu tahun dari Date Start.']);
        }

        return $this->derive($source, $user, DplRelation::TYPE_RENEWAL, $start->toDateString(), $end->toDateString());
    }

    /**
     * Item sumber yang sama persis (product + distributor + semua isian) dengan item amendment, untuk mempertahankan
     * approval. Null = item baru / berubah.
     */
    public function matchingSourceItem(DplItem $item, DplProposal $source): ?DplItem
    {
        $source->loadMissing('items.terms');
        $item->loadMissing('terms');

        return $source->items->first(fn (DplItem $candidate) => $this->comparable($candidate) == $this->comparable($item));
    }

    /**
     * DPL sumber amendment (null bila bukan amendment).
     */
    public function amendmentSource(DplProposal $proposal): ?DplProposal
    {
        return $proposal->incomingRelations()
            ->where('relation_type', DplRelation::TYPE_AMENDMENT)
            ->with('source')
            ->first()
            ?->source;
    }

    private function derive(DplProposal $source, User $user, string $type, string $dateStart, string $dateEnd): DplProposal
    {
        $source->load(['outlet.area.region.zone', 'items.terms', 'items.product']);
        $outlet = $source->outlet;

        if (! $outlet->is_active || $outlet->area?->region?->zone === null) {
            throw ValidationException::withMessages(['proposal' => 'Outlet DPL sumber sudah tidak aktif atau belum memiliki area/region/zona.']);
        }

        $items = $source->items->map(fn (DplItem $item) => $this->recalculatedItem($item, $source))->values()->all();

        return DB::transaction(function () use ($source, $user, $type, $dateStart, $dateEnd, $outlet, $items): DplProposal {
            $proposal = $this->drafts->create($user, $outlet, $source->team_code, [
                'proposal_type' => $source->proposal_type,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'is_contract' => $source->is_contract,
                'bed_order_minimum' => $source->bed_order_minimum,
                'description' => $source->description,
            ], $items);

            DplRelation::query()->create([
                'source_dpl_id' => $source->id,
                'target_dpl_id' => $proposal->id,
                'relation_type' => $type,
                'created_by_user_id' => $user->id,
            ]);

            return $proposal;
        });
    }

    /**
     * Salin item dan hitung ulang margin & logistic fee dengan aturan terbaru (seperti legacy checkingUpdateTerbaru).
     *
     * @return array<string, mixed>
     */
    private function recalculatedItem(DplItem $item, DplProposal $source): array
    {
        $data = [
            ...$item->only(DplDraftService::ITEM_FIELDS),
            ...($item->terms?->only(DplDraftService::TERM_FIELDS) ?? []),
        ];

        $defaults = $this->defaults->resolve(
            $item->product,
            $item->distributor()->firstOrFail(),
            $source->outlet,
            $source->team_code,
            $item->is_direct_delivery,
            $item->is_factory_pickup,
        );

        $data['margin'] = $defaults['margin'] ?? $data['margin'];

        // Logistic fee Direct adalah input manual; selain itu ikut aturan terbaru (Ambil Dari Pabrik = 0).
        if (! $item->is_direct_delivery) {
            $data['logistic_fee'] = $defaults['logistic_fee'] ?? $data['logistic_fee'];
        }

        return $data;
    }

    /**
     * @throws ValidationException
     */
    private function authorizeSource(DplProposal $source, User $user): void
    {
        if ($source->created_by_user_id !== $user->id) {
            throw ValidationException::withMessages(['proposal' => 'Hanya pengaju DPL ini yang bisa Make a Copy / Duplicate.']);
        }

        if ($source->proposal_status !== DplProposal::STATUS_APPROVED) {
            throw ValidationException::withMessages(['proposal' => 'Hanya DPL yang sudah approved yang bisa di-copy / duplicate.']);
        }
    }

    /**
     * Nilai item untuk perbandingan, dinormalkan seperti saat draft disimpan: angka "10" = "10.0000", diskon &
     * Support Logistic Satoria kosong = 0, jenis harga kosong = default.
     *
     * @return array<string, mixed>
     */
    private function comparable(DplItem $item): array
    {
        $values = [
            ...$item->only(DplDraftService::ITEM_FIELDS),
            ...($item->terms?->only(DplDraftService::TERM_FIELDS) ?? []),
        ];

        foreach (['principle_discount_on', 'principle_discount_off', 'distributor_discount_on', 'distributor_discount_off', 'logistic_support'] as $field) {
            $values[$field] ??= 0;
        }

        $values['price_type'] ??= DplPricing::defaultPriceType();

        return array_map(fn ($value) => is_numeric($value) && ! is_bool($value) ? round((float) $value, 4) : $value, $values);
    }
}
