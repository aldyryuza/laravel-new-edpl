<?php

namespace App\Http\Requests;

use App\Models\Menu;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:menus.*` di controller.
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
        /** @var Menu|null $menu */
        $menu = $this->route('menu');

        return [
            'parent_id' => [
                'nullable', 'integer', 'exists:menus,id',
                function (string $attribute, mixed $value, Closure $fail) use ($menu): void {
                    if ($menu && $value && $menu->isSelfOrDescendant((int) $value)) {
                        $fail('Parent tidak boleh menu ini sendiri atau sub menunya.');
                    }
                },
            ],
            // Dipakai sebagai kunci permission ("{code}.{action}"), jadi tanpa titik.
            'code' => ['required', 'string', 'max:50', 'alpha_dash:ascii', Rule::unique('menus', 'code')->ignore($menu)],
            'title' => ['required', 'string', 'max:100'],
            'icon' => ['nullable', 'string', 'max:100'],
            'route' => ['nullable', 'string', 'max:150'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'parent_id' => 'parent',
            'code' => 'kode',
            'title' => 'judul',
            'sort_order' => 'urutan',
            'is_active' => 'status aktif',
        ];
    }
}
