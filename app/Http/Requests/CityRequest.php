<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CityRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:cities.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kode = kode wilayah BPS tanpa titik (mis. 8271 = Kota Ternate).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('cities', 'code')->ignore($this->route('city'))],
            'name' => ['required', 'string', 'max:100'],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')],
            // Logistic fee DPL (%) untuk distributor di kota ini.
            'logistic_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.9999', 'decimal:0,4'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama', 'province_id' => 'provinsi', 'logistic_fee' => 'logistic fee', 'is_active' => 'status aktif'];
    }
}
