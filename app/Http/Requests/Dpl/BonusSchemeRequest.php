<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\BonusScheme;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Skema bonus, mengikuti legacy dpl_bonus (name, bonus, discount_invoiced, discount_bonus, discount_rounded,
 * total_discount). Semua nilai diisi manual; total diskon tidak dihitung otomatis karena rumusnya belum dikonfirmasi.
 */
class BonusSchemeRequest extends FormRequest
{
    /** Batas kolom numeric(10,4). */
    private const MAX_AMOUNT = '999999.9999';

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
        $amount = ['required', 'numeric', 'min:0', 'max:'.self::MAX_AMOUNT, 'decimal:0,4'];

        return [
            'code' => ['nullable', 'string', 'max:30', Rule::unique(BonusScheme::class, 'code')->ignore($this->route('bonus_scheme'))],
            'name' => ['required', 'string', 'max:255'],
            'bonus' => $amount,
            'discount_invoice' => $amount,
            'discount_bonus' => $amount,
            'discount_rounded' => $amount,
            'total_discount' => $amount,
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
            'bonus' => 'bonus',
            'discount_invoice' => 'diskon invoice',
            'discount_bonus' => 'diskon bonus',
            'discount_rounded' => 'diskon pembulatan',
            'total_discount' => 'total diskon',
            'is_active' => 'status aktif',
        ];
    }
}
