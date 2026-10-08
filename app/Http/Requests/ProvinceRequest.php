<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProvinceRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:provinces.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kode = kode wilayah BPS tanpa titik (mis. 82).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10', Rule::unique('provinces', 'code')->ignore($this->route('province'))],
            'name' => ['required', 'string', 'max:100'],
            // Margin DPL Direct ke outlet Instansi: Jawa vs luar Jawa.
            'is_java' => ['sometimes', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama', 'is_java' => 'Pulau Jawa', 'is_active' => 'status aktif'];
    }
}
