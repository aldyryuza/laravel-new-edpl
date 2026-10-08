<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\DistributorMarginRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Validator;

/**
 * Margin khusus product per grup distributor (legacy dpl_distributor_margin). Kota kosong = semua kota.
 */
class DistributorMarginRuleRequest extends FormRequest
{
    /** Batas kolom numeric(10,4). */
    public const MAX_PERCENT = '999999.9999';

    /**
     * Hak akses dicek oleh middleware `can:distributor-margin-rules.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Product harus aktif, kecuali product yang sudah tersimpan pada data yang sedang diedit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'distributor_group_code' => ['required', 'string', 'max:50'],
            'product_id' => ['required', 'integer', self::activeOrCurrentRule('products', $this->route('distributor_margin_rule')?->product_id)],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'margin' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_PERCENT, 'decimal:0,4'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Rule exists untuk data yang aktif, atau data yang sudah tersimpan pada record yang sedang diedit
     * (agar record lama tetap bisa diedit walau master-nya sudah nonaktif).
     */
    public static function activeOrCurrentRule(string $table, ?int $currentId): Exists
    {
        return Rule::exists($table, 'id')->where(fn (Builder $query) => $query->where(
            fn (Builder $query) => $query->where('is_active', true)->when($currentId, fn (Builder $query, int $id) => $query->orWhere('id', $id)),
        ));
    }

    /**
     * Satu aturan per grup + product + kota; "semua kota" (null) juga hanya satu (index COALESCE(city_id, 0)).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $cityId = $this->input('city_id') ? (int) $this->input('city_id') : null;

                $duplicate = DistributorMarginRule::query()
                    ->where('distributor_group_code', $this->input('distributor_group_code'))
                    ->where('product_id', (int) $this->input('product_id'))
                    ->when($cityId === null, fn ($query) => $query->whereNull('city_id'), fn ($query) => $query->where('city_id', $cityId))
                    ->when($this->route('distributor_margin_rule'), fn ($query, DistributorMarginRule $current) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('distributor_group_code', 'Margin khusus untuk grup, product, dan kota ini sudah ada.');
                }
            },
        ];
    }

    /**
     * Kode grup disimpan huruf besar (mengikuti distributors.group_code); kota kosong = semua kota.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('distributor_group_code'))) {
            $this->merge(['distributor_group_code' => mb_strtoupper(trim($this->input('distributor_group_code')))]);
        }

        if ($this->input('city_id') === '') {
            $this->merge(['city_id' => null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'distributor_group_code' => 'grup distributor',
            'product_id' => 'product',
            'city_id' => 'kota',
            'margin' => 'margin',
            'note' => 'catatan',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'Product tidak ditemukan atau sudah nonaktif.',
        ];
    }
}
