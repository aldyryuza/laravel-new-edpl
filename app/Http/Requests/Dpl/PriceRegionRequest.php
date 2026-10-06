<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\PriceRegion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceRegionRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:price-regions.*` di controller.
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
            'code' => ['required', 'string', 'max:30', Rule::unique(PriceRegion::class, 'code')->ignore($this->route('price_region'))],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama', 'description' => 'keterangan', 'is_active' => 'status aktif'];
    }
}
