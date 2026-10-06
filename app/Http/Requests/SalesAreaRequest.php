<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesAreaRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:sales-areas.*` di controller.
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
            'code' => ['required', 'string', 'max:30', Rule::unique('sales_areas', 'code')->ignore($this->route('sales_area'))],
            'name' => ['required', 'string', 'max:100'],
            'region_id' => ['required', 'integer', Rule::exists('sales_regions', 'id')],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama', 'region_id' => 'region', 'is_active' => 'status aktif'];
    }
}
