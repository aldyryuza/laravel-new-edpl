<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\DistributorMarginOverride;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Margin khusus outlet per distributor (legacy dpl_distributor_spesial_margin_outlet).
 */
class DistributorMarginOverrideRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:distributor-margin-overrides.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Outlet harus aktif, kecuali outlet yang sudah tersimpan pada data yang sedang diedit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'distributor_id' => ['required', 'integer', Rule::exists('distributors', 'id')],
            'outlet_id' => ['required', 'integer', DistributorMarginRuleRequest::activeOrCurrentRule('outlets', $this->route('distributor_margin_override')?->outlet_id)],
            'margin' => ['required', 'numeric', 'min:0', 'max:'.DistributorMarginRuleRequest::MAX_PERCENT, 'decimal:0,4'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Satu margin per distributor + outlet.
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

                $duplicate = DistributorMarginOverride::query()
                    ->where('distributor_id', (int) $this->input('distributor_id'))
                    ->where('outlet_id', (int) $this->input('outlet_id'))
                    ->when($this->route('distributor_margin_override'), fn ($query, DistributorMarginOverride $current) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('outlet_id', 'Margin khusus untuk distributor dan outlet ini sudah ada.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'distributor_id' => 'distributor',
            'outlet_id' => 'outlet',
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
            'outlet_id.exists' => 'Outlet tidak ditemukan atau sudah nonaktif.',
        ];
    }
}
