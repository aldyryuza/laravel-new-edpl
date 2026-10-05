<?php

namespace App\Http\Requests;

use App\Models\RoleMenuAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleAccessRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:roles.update` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Payload: access[] = { menu_id, can_create, can_read, ... }.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'access' => ['required', 'array'],
            'access.*.menu_id' => ['required', 'integer', 'distinct', 'exists:menus,id'],
        ];

        foreach (RoleMenuAccess::ACTIONS as $action) {
            $rules["access.*.can_{$action}"] = ['sometimes', 'boolean'];
        }

        return $rules;
    }
}
