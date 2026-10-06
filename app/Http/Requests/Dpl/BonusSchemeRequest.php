<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\BonusScheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Skema bonus (legacy dpl_bonus). Semua nilai diisi manual; diskon efektif tidak dihitung otomatis
 * karena rumusnya belum dikonfirmasi.
 */
class BonusSchemeRequest extends FormRequest
{
    /** Batas numeric(12,4) untuk kuantitas. */
    private const MAX_QUANTITY = '99999999.9999';

    /** Batas numeric(10,4) untuk persentase diskon. */
    private const MAX_DISCOUNT = '999999.9999';

    /**
     * Hak akses dicek oleh middleware `can:bonus-schemes.*` di controller.
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
        $quantity = ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_QUANTITY, 'decimal:0,4'];
        $discount = ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_DISCOUNT, 'decimal:0,4'];

        return [
            'code' => ['nullable', 'string', 'max:30', Rule::unique(BonusScheme::class, 'code')->ignore($this->route('bonus_scheme'))],
            'name' => ['required', 'string', 'max:255'],
            'buy_quantity' => $quantity,
            'bonus_quantity' => $quantity,
            'discount_invoice' => $discount,
            'discount_bonus' => $discount,
            'effective_discount' => $discount,
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
            'buy_quantity' => 'jumlah beli',
            'bonus_quantity' => 'jumlah bonus',
            'discount_invoice' => 'diskon invoice',
            'discount_bonus' => 'diskon bonus',
            'effective_discount' => 'diskon efektif',
            'is_active' => 'status aktif',
        ];
    }
}
