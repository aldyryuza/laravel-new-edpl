<?php

namespace App\Http\Requests;

use App\Http\Requests\Dpl\DistributorMarginRuleRequest;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PIC outlet (AM / SPV). Satu baris per outlet; minimal AM atau SPV terisi.
 */
class OutletSalesAssignmentRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:outlet-sales-assignments.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Outlet harus aktif (kecuali outlet tersimpan saat edit) dan belum punya PIC. User harus aktif.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $current = $this->route('outlet_sales_assignment');
        $activeUser = Rule::exists(User::class, 'id')->where('is_active', true);

        return [
            'outlet_id' => [
                'required', 'integer',
                DistributorMarginRuleRequest::activeOrCurrentRule('outlets', $current?->outlet_id),
                Rule::unique('outlet_sales_assignments', 'outlet_id')->ignore($current?->getKey()),
            ],
            'am_user_id' => ['nullable', 'integer', 'required_without:spv_user_id', $activeUser],
            'spv_user_id' => ['nullable', 'integer', 'required_without:am_user_id', $activeUser],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'outlet_id' => 'outlet',
            'am_user_id' => 'AM',
            'spv_user_id' => 'SPV',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'outlet_id.exists' => 'Outlet tidak ditemukan atau sudah nonaktif.',
            'outlet_id.unique' => 'Outlet ini sudah punya PIC.',
            'am_user_id.required_without' => 'Isi AM atau SPV.',
            'spv_user_id.required_without' => 'Isi AM atau SPV.',
            'am_user_id.exists' => 'User tidak ditemukan atau sudah nonaktif.',
            'spv_user_id.exists' => 'User tidak ditemukan atau sudah nonaktif.',
        ];
    }
}
