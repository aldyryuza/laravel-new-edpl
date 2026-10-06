<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesRegionRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:sales-regions.*` di controller.
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
            'code' => ['required', 'string', 'max:30', Rule::unique('sales_regions', 'code')->ignore($this->route('sales_region'))],
            'name' => ['required', 'string', 'max:100'],
            'zone_id' => ['required', 'integer', Rule::exists('sales_zones', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama', 'zone_id' => 'zona', 'is_active' => 'status aktif'];
    }
}
