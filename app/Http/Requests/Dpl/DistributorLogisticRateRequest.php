<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\DistributorLogisticRate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tarif logistic fee (%) khusus grup distributor + kota.
 */
class DistributorLogisticRateRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:distributor-logistic-rates.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'distributor_group_code' => ['required', 'string', 'max:50'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')],
            'logistic_fee' => ['required', 'numeric', 'min:0', 'max:'.DistributorMarginRuleRequest::MAX_PERCENT, 'decimal:0,4'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Satu tarif per grup + kota.
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

                $duplicate = DistributorLogisticRate::query()
                    ->where('distributor_group_code', $this->input('distributor_group_code'))
                    ->where('city_id', (int) $this->input('city_id'))
                    ->when($this->route('distributor_logistic_rate'), fn ($query, DistributorLogisticRate $current) => $query->whereKeyNot($current->getKey()))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('distributor_group_code', 'Tarif logistic untuk grup dan kota ini sudah ada.');
                }
            },
        ];
    }

    /**
     * Kode grup disimpan huruf besar (mengikuti distributors.group_code).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('distributor_group_code'))) {
            $this->merge(['distributor_group_code' => mb_strtoupper(trim($this->input('distributor_group_code')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'distributor_group_code' => 'grup distributor',
            'city_id' => 'kota',
            'logistic_fee' => 'logistic fee',
            'note' => 'catatan',
            'is_active' => 'status aktif',
        ];
    }
}
