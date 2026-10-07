<?php

namespace App\Http\Requests\Dpl;

use App\Models\Distributor;
use App\Models\Dpl\BonusScheme;
use App\Models\Dpl\DplProposal;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\Dpl\DplDraftService;
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
    /** Batas kolom numeric(10,4) dan numeric(18,4). */
    private const MAX_PERCENT = 999999.9999;

    private const MAX_AMOUNT = 99999999999999.9999;

    /**
     * Hak akses menu dicek oleh middleware `can:dpl-proposals.*` di controller. Untuk ubah draft,
     * hanya pembuat draft yang diizinkan (dicek sebelum validasi agar tidak membocorkan error validasi).
     */
    public function authorize(): bool
    {
        $proposal = $this->route('proposal');

        return ! $proposal instanceof DplProposal || $proposal->isEditableDraftOf($this->user());
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
        $amount = ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT];
        $percent = ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PERCENT];

        return [
            ...($creating ? [
                'outlet_id' => ['required', 'integer'],
                'team_code' => ['nullable', 'string', 'max:20'],
            ] : []),
            // CONSOLIDATION dibuat lewat alur konsolidasi, bukan dari form ini.
            'proposal_type' => ['required', Rule::in([DplProposal::TYPE_NORMAL, DplProposal::TYPE_CLEARANCE])],
            'date_offer' => ['required', 'date'],
            // BR-007: Date Start tidak boleh backdated.
            'date_start' => ['required', 'date', 'after_or_equal:'.$today],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'is_contract' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],

            'items' => ['present', 'array'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('is_active', true)],
            'items.*.distributor_id' => ['required', 'integer'],
            'items.*.minimum_order' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            'items.*.bonus_scheme_id' => ['nullable', 'integer', Rule::exists(BonusScheme::class, 'id')->where('is_active', true)],
            'items.*.is_direct_delivery' => ['boolean'],
            'items.*.is_buyer_reward' => ['boolean'],
            'items.*.is_bonus_conversion' => ['boolean'],
            'items.*.is_support_satoria' => ['boolean'],
            'items.*.is_returnable' => ['boolean'],
            'items.*.remark' => ['nullable', 'string', 'max:2000'],
            'items.*.principle_discount_on' => $percent,
            'items.*.principle_discount_off' => $percent,
            'items.*.distributor_discount_on' => $percent,
            'items.*.distributor_discount_off' => $percent,
            'items.*.negotiated_price' => $amount,
            'items.*.margin' => $percent,
            'items.*.logistic_support' => $amount,
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
        return $this->safe()->only(['proposal_type', 'date_offer', 'date_start', 'date_end', 'is_contract', 'description']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function proposalItems(): array
    {
        $fields = array_flip(['id', ...DplDraftService::ITEM_FIELDS, ...DplDraftService::TERM_FIELDS]);

        return array_map(fn (array $item) => array_intersect_key($item, $fields), $this->validated('items', []));
    }

    private function validatePeriod(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['date_start', 'date_end'])) {
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
            'date_offer' => 'tanggal penawaran',
            'date_start' => 'Date Start',
            'date_end' => 'Date End',
            'is_contract' => 'kontrak',
            'description' => 'keterangan',
            'items.*.product_id' => 'product',
            'items.*.distributor_id' => 'distributor',
            'items.*.minimum_order' => 'minimum order',
            'items.*.bonus_scheme_id' => 'skema bonus',
            'items.*.remark' => 'catatan item',
            'items.*.principle_discount_on' => 'diskon prinsipal on',
            'items.*.principle_discount_off' => 'diskon prinsipal off',
            'items.*.distributor_discount_on' => 'diskon distributor on',
            'items.*.distributor_discount_off' => 'diskon distributor off',
            'items.*.negotiated_price' => 'harga nego',
            'items.*.margin' => 'margin',
            'items.*.logistic_support' => 'logistic support',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_start.after_or_equal' => 'Date Start tidak boleh tanggal yang sudah lewat.',
        ];
    }
}
