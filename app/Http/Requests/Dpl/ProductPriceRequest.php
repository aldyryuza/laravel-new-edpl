<?php

namespace App\Http\Requests\Dpl;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductPriceRequest extends FormRequest
{
    /** Batas kolom numeric(18,4). */
    public const MAX_AMOUNT = '99999999999999.9999';

    /**
     * Hak akses dicek oleh middleware `can:product-prices.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Overlap periode harga sengaja belum divalidasi sampai aturan histori harga dikonfirmasi (docs/Schema.md).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'price' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,4'],
            'regular_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,4'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'product',
            'price' => 'harga',
            'regular_price' => 'harga reguler',
            'valid_from' => 'berlaku mulai',
            'valid_until' => 'berlaku sampai',
            'is_active' => 'status aktif',
        ];
    }
}
