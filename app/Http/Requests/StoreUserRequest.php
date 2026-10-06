<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:users.create` di controller.
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
            'nik' => ['required', 'string', 'max:30', 'unique:users,nik'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'is_active' => ['sometimes', 'boolean'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nik' => 'NIK', 'name' => 'nama', 'is_active' => 'status aktif', 'role_ids' => 'role', 'role_ids.*' => 'role'];
    }
}
