<?php

namespace App\Http\Requests\Dpl;

use App\Models\Distributor;
use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\Dpl\DplApprovalService;
use App\Services\Dpl\DplDerivationService;
use App\Services\Dpl\DplDraftService;
use App\Services\Dpl\DplPricing;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Draft DPL (header + item + commercial terms). Outlet dan team hanya dipilih saat membuat draft.
 */
class DplProposalRequest extends FormRequest
{
    /** Batas kolom numeric(10,4) untuk nilai persen. */
    private const MAX_PERCENT = 999999.9999;

    /**
     * Hak akses menu dicek oleh middleware `can:dpl-proposals.*` di controller. Untuk ubah draft,
     * hanya pembuat draft yang diizinkan (dicek sebelum validasi agar tidak membocorkan error validasi).
     */
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return ! $proposal instanceof DplProposal || app(DplApprovalService::class)->canEdit($proposal, $this->user());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('proposal') === null;
        $today = CarbonImmutable::now(config('dpl.timezone'))->toDateString();
        $percent = ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PERCENT];

        return [
            ...($creating ? [
                'outlet_id' => ['required', 'integer'],
                'team_code' => ['nullable', 'string', 'max:20'],
            ] : []),
            // CONSOLIDATION dibuat lewat alur konsolidasi, bukan dari form ini.
            'proposal_type' => ['required', Rule::in([DplProposal::TYPE_NORMAL, DplProposal::TYPE_CLEARANCE])],
            // Tanggal penawaran otomatis hari ini (diisi server). Semua tanggal tidak boleh backdated (BR-007).
            // Amendment (Make a Copy): periode dikunci dari pembuatan, tidak divalidasi / diubah lagi.
            ...($this->isAmendment() ? [] : [
                'date_start' => ['required', 'date', 'after_or_equal:'.$today],
                'date_end' => ['required', 'date', 'after_or_equal:'.$today, 'after_or_equal:date_start'],
            ]),
            'is_contract' => ['required', 'boolean'],
            // "25% Bed": dihitung server dari bed outlet; hanya dipakai bila bed outlet 0/kosong.
            'bed_order_minimum' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:5000'],

            'items' => ['present', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('is_active', true)],
            'items.*.distributor_id' => ['required', 'integer'],
            'items.*.minimum_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'items.*.bonus_scheme_id' => ['nullable', 'integer', Rule::exists(BonusScheme::class, 'id')->where('is_active', true)],
            'items.*.is_direct_delivery' => ['boolean'],
            'items.*.is_factory_pickup' => ['boolean'],
            'items.*.is_buyer_reward' => ['boolean'],
            'items.*.is_support_satoria' => ['boolean'],
            'items.*.is_returnable' => ['boolean'],
            'items.*.remark' => ['nullable', 'string', 'max:2000'],
            'items.*.principle_discount_on' => $percent,
            'items.*.principle_discount_off' => $percent,
            'items.*.distributor_discount_on' => $percent,
            'items.*.distributor_discount_off' => $percent,
            'items.*.price_type' => ['nullable', Rule::in(array_keys(DplPricing::priceTypes()))],
            'items.*.margin' => $percent,
            // Logistic fee wajib (boleh 0) untuk item Direct.
            'items.*.logistic_fee' => ['nullable', 'required_if_accepted:items.*.is_direct_delivery', 'numeric', 'min:0', 'max:'.self::MAX_PERCENT],
            // Logistic fee & logistic support dalam % (ikut dijumlah di Total Diskon kalkulator).
            // Support Logistic Satoria (legacy support_logistic_satoria): input manual 0–100%.
            'items.*.logistic_support' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Outlet & distributor harus di wilayah user, team dari penugasan user, dan masa program maksimal 1 tahun (BR-008).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePeriod($validator);
                $this->validateDistributors($validator);
                $this->validateItemRules($validator);

                if ($this->route('proposal') === null) {
                    $this->validateOutlet($validator);
                    $this->validateTeam($validator);
                }
            },
        ];
    }

    /**
     * Outlet terpilih (aktif, di wilayah user, sudah punya area). Hanya untuk create.
     */
    public function outlet(): Outlet
    {
        return $this->visibleOutletQuery()->with('area.region.zone')->findOrFail($this->integer('outlet_id'));
    }

    /**
     * Team DPL: dari penugasan wilayah user; wajib dipilih bila user punya lebih dari satu team.
     */
    public function teamCode(): string
    {
        $teams = $this->user()->effectiveTeams();

        return $teams->count() === 1 ? $teams->first()->code : (string) $this->input('team_code');
    }

    /**
     * @return array<string, mixed>
     */
    public function proposalAttributes(): array
    {
        $fields = ['proposal_type', 'date_start', 'date_end', 'is_contract', 'bed_order_minimum', 'description'];

        return $this->safe()->only($this->isAmendment() ? array_diff($fields, ['date_start', 'date_end']) : $fields);
    }

    /**
     * DPL yang diubah adalah amendment (Make a Copy): periodenya dikunci.
     */
    private function isAmendment(): bool
    {
        $proposal = $this->route('proposal');

        return $proposal instanceof DplProposal && app(DplDerivationService::class)->amendmentSource($proposal) !== null;
    }

    /**
     * Item siap simpan dengan aturan otomatis:
     * - Discount Share Principle OFF diisi (> 0) -> buyer reward dicentang;
     * - Ambil Dari Pabrik -> logistic fee 0 (item lain menyimpan logistic fee yang dikirim; Direct wajib diisi).
     *
     * @return array<int, array<string, mixed>>
     */
    public function proposalItems(): array
    {
        $fields = array_flip(['id', ...DplDraftService::ITEM_FIELDS, ...DplDraftService::TERM_FIELDS]);

        return array_map(function (array $item) use ($fields): array {
            $item = array_intersect_key($item, $fields);

            if ((float) ($item['principle_discount_off'] ?? 0) > 0) {
                $item['is_buyer_reward'] = true;
            }

            if ((bool) ($item['is_factory_pickup'] ?? false)) {
                $item['logistic_fee'] = '0';
            }

            return $item;
        }, $this->validated('items', []));
    }

    /**
     * Product harus dalam group DPL, jenis harga sesuai volume, Direct dan Ambil Dari Pabrik tidak boleh bersamaan,
     * dan satu product hanya sekali per distributor.
     */
    private function validateItemRules(Validator $validator): void
    {
        $seen = [];
        $products = Product::query()
            ->whereKey(collect($this->input('items', []))->pluck('product_id')->filter()->map(fn ($id) => (int) $id)->unique()->all())
            ->get(['id', 'volume', 'product_group'])
            ->keyBy('id');

        foreach ($this->input('items', []) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $priceType = $item['price_type'] ?? null;
            $product = isset($item['product_id']) ? $products->get((int) $item['product_id']) : null;

            // Hanya product dalam group DPL (1L/500/250/100/25 ml atau group manual) yang bisa diajukan.
            if ($product !== null && $product->dplGroup() === null) {
                $validator->errors()->add("items.{$index}.product_id", 'Product ini tidak termasuk group product DPL.');
            }

            if (is_string($priceType) && $product !== null && ! DplPricing::allowsVolume($priceType, $product->volume)) {
                $allowed = implode('/', config("dpl.price_type_volumes.{$priceType}"));
                $validator->errors()->add("items.{$index}.price_type", "{$priceType} hanya untuk product {$allowed} ml.");
            }

            if (filter_var($item['is_direct_delivery'] ?? false, FILTER_VALIDATE_BOOL) && filter_var($item['is_factory_pickup'] ?? false, FILTER_VALIDATE_BOOL)) {
                $validator->errors()->add("items.{$index}.is_factory_pickup", 'Pilih salah satu: Direct atau Ambil Dari Pabrik.');
            }

            if (! isset($item['product_id'], $item['distributor_id'])) {
                continue;
            }

            $key = (int) $item['product_id'].'-'.(int) $item['distributor_id'];

            if (isset($seen[$key])) {
                $validator->errors()->add("items.{$index}.product_id", 'Product ini sudah ada untuk distributor yang sama (item #'.($seen[$key] + 1).').');
            } else {
                $seen[$key] = $index;
            }
        }
    }

    private function validatePeriod(Validator $validator): void
    {
        if ($this->isAmendment() || $validator->errors()->hasAny(['date_start', 'date_end'])) {
            return;
        }

        $maxEnd = CarbonImmutable::parse($this->input('date_start'))->addYear();

        if (CarbonImmutable::parse($this->input('date_end'))->greaterThan($maxEnd)) {
            $validator->errors()->add('date_end', 'Masa program maksimal satu tahun dari Date Start (sampai '.$maxEnd->toDateString().').');
        }
    }

    private function validateDistributors(Validator $validator): void
    {
        $ids = collect($this->input('items', []))->pluck('distributor_id')->filter()->map(fn ($id) => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return;
        }

        $allowed = Distributor::query()->visibleTo($this->user())->where('is_active', true)->whereKey($ids)->pluck('id')->all();

        foreach ($this->input('items', []) as $index => $item) {
            if (isset($item['distributor_id']) && ! in_array((int) $item['distributor_id'], $allowed, true)) {
                $validator->errors()->add("items.{$index}.distributor_id", 'Distributor tidak ditemukan, nonaktif, atau di luar wilayah Anda.');
            }
        }
    }

    private function validateOutlet(Validator $validator): void
    {
        if ($validator->errors()->has('outlet_id')) {
            return;
        }

        $outlet = $this->visibleOutletQuery()->with('area.region.zone')->find($this->integer('outlet_id'));

        if ($outlet === null) {
            $validator->errors()->add('outlet_id', 'Outlet tidak ditemukan, nonaktif, atau di luar wilayah Anda.');
        } elseif ($outlet->area?->region?->zone === null) {
            $validator->errors()->add('outlet_id', 'Outlet belum memiliki area/region/zona, sehingga nomor DPL tidak bisa dibuat.');
        }
    }

    private function validateTeam(Validator $validator): void
    {
        $teams = $this->user()->effectiveTeams();

        if ($teams->isEmpty()) {
            $validator->errors()->add('team_code', 'Anda belum memiliki team aktif. Minta admin mengatur team di halaman Wilayah User.');
        } elseif ($teams->count() > 1 && ! $teams->contains('code', $this->input('team_code'))) {
            $validator->errors()->add('team_code', 'Pilih salah satu team Anda.');
        }
    }

    /**
     * @return Builder<Outlet>
     */
    private function visibleOutletQuery()
    {
        return Outlet::query()->visibleTo($this->user())->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'outlet_id' => 'outlet',
            'team_code' => 'team',
            'proposal_type' => 'jenis DPL',
            'date_start' => 'Date Start',
            'date_end' => 'Date End',
            'is_contract' => 'kontrak',
            'description' => 'keterangan',
            'items.*.product_id' => 'product',
            'items.*.distributor_id' => 'distributor',
            'items.*.minimum_order' => 'minimum order per bulan',
            'items.*.bonus_scheme_id' => 'skema bonus',
            'items.*.remark' => 'catatan item',
            'items.*.principle_discount_on' => 'Discount Share Principle ON',
            'items.*.principle_discount_off' => 'Discount Share Principle OFF',
            'items.*.distributor_discount_on' => 'Discount Share Distributor ON',
            'items.*.distributor_discount_off' => 'Discount Share Distributor OFF',
            'items.*.price_type' => 'jenis harga',
            'items.*.margin' => 'margin',
            'items.*.logistic_fee' => 'logistic fee',
            'items.*.logistic_support' => 'Support Logistic Satoria',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_start.after_or_equal' => 'Date Start tidak boleh tanggal yang sudah lewat.',
            'date_end.after_or_equal' => 'Date End tidak boleh tanggal yang sudah lewat atau sebelum Date Start.',
            'items.*.logistic_fee.required_if_accepted' => 'Logistic fee wajib diisi (boleh 0) untuk item Direct.',
        ];
    }
}
