<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutletGroupRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:outlet-groups.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kode opsional (tidak semua group legacy punya kode), tetapi unik bila diisi.
     * Group tanpa kode tidak bisa dirujuk dari import Excel outlet.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('outlet_groups', 'code')->ignore($this->route('outlet_group'))],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
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
