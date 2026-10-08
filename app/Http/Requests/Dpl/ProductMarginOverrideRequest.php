<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\ProductMarginOverride;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Override margin per product (legacy DPL_PRODUCT_MARGIN_OVERRIDE). Grup/team kosong = semua.
 */
class ProductMarginOverrideRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:product-margin-overrides.*` di controller.
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
            'product_id' => ['required', 'integer', DistributorMarginRuleRequest::activeOrCurrentRule('products', $this->route('product_margin_override')?->product_id)],
            'distributor_group_code' => ['nullable', 'string', 'max:50'],
            'team_code' => ['nullable', 'string', 'max:20', Rule::exists('teams', 'code')],
            'margin' => ['required', 'numeric', 'min:0', 'max:'.DistributorMarginRuleRequest::MAX_PERCENT, 'decimal:0,4'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Satu override per product + grup + team; kosong (null) dihitung sebagai satu nilai "semua"
     * (index COALESCE(kolom, '')).
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

                $group = $this->input('distributor_group_code');
                $team = $this->input('team_code');

                $duplicate = ProductMarginOverride::query()
                    ->where('product_id', (int) $this->input('product_id'))
                    ->when($group === null, fn ($query) => $query->whereNull('distributor_group_code'), fn ($query) => $query->where('distributor_group_code', $group))
                    ->when($team === null, fn ($query) => $query->whereNull('team_code'), fn ($query) => $query->where('team_code', $team))
                    ->when($this->route('product_margin_override'), fn ($query, ProductMarginOverride $current) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('product_id', 'Override margin untuk product, grup, dan team ini sudah ada.');
                }
            },
        ];
    }

    /**
     * Kode grup disimpan huruf besar; grup/team kosong = semua (null).
     */
    protected function prepareForValidation(): void
    {
        $group = $this->input('distributor_group_code');
        $team = $this->input('team_code');

        $this->merge([
            'distributor_group_code' => is_string($group) && trim($group) !== '' ? mb_strtoupper(trim($group)) : null,
            'team_code' => is_string($team) && trim($team) !== '' ? trim($team) : null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'distributor_group_code' => 'grup distributor',
            'team_code' => 'team',
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
