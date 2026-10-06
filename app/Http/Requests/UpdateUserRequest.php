<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:users.update` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Password boleh kosong: berarti tidak diganti.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'nik' => ['required', 'string', 'max:30', Rule::unique('users', 'nik')->ignore($user->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', Password::defaults()],
            // Akun yang sedang dipakai login tidak boleh menonaktifkan dirinya sendiri.
            'is_active' => ['sometimes', 'boolean', Rule::when($user->is($this->user()), ['accepted'])],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['is_active.accepted' => 'Tidak dapat menonaktifkan akun yang sedang dipakai login.'];
    }
}
