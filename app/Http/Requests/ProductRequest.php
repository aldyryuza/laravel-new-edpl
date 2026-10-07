<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:products.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Volume dalam ml (kolom numeric(10,4)); dipakai untuk grouping 500/250/100/25 ml.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('products', 'code')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'volume' => ['nullable', 'numeric', 'min:0', 'max:999999.9999', 'decimal:0,4'],
            // Group manual (mis. IV CAT WING); product bervolume 1L/500/250/100/25 ml tidak perlu diisi.
            'product_group' => ['nullable', 'string', Rule::in(config('dpl.product_groups'))],
            'is_branded' => ['required', 'boolean'],
            'netsuite_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'volume' => 'volume',
            'product_group' => 'group product',
            'is_branded' => 'branded',
            'netsuite_id' => 'NetSuite ID',
            'is_active' => 'status aktif',
        ];
    }
}
