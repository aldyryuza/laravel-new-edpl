<?php

namespace App\Http\Requests\Dpl;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Harga khusus (legacy dpl_price_product_special), mis. JKN/PADMA, opsional per group outlet.
 */
class ProductPriceOverrideRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:special-prices.*` di controller.
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
            'price_type' => ['required', 'string', 'max:50'],
            'outlet_group_id' => ['nullable', 'integer', Rule::exists('outlet_groups', 'id')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'price' => ['required', 'numeric', 'min:0', 'max:'.ProductPriceRequest::MAX_AMOUNT, 'decimal:0,4'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Jenis harga disimpan huruf besar agar konsisten (JKN, PADMA).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('price_type'))) {
            $this->merge(['price_type' => mb_strtoupper(trim($this->input('price_type')))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'price_type' => 'jenis harga',
            'outlet_group_id' => 'group RS',
            'product_id' => 'product',
            'price' => 'harga',
            'valid_from' => 'berlaku mulai',
            'valid_until' => 'berlaku sampai',
            'is_active' => 'status aktif',
            'note' => 'catatan',
        ];
    }
}
