<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutletRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:outlets.*` di controller.
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
            'code' => ['required', 'string', 'max:30', Rule::unique('outlets', 'code')->ignore($this->route('outlet'))],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'area_id' => ['nullable', 'integer', Rule::exists('sales_areas', 'id')],
            'outlet_category_id' => ['nullable', 'integer', Rule::exists('outlet_categories', 'id')],
            'ownership_type_id' => ['nullable', 'integer', Rule::exists('ownership_types', 'id')],
            'outlet_group_id' => ['nullable', 'integer', Rule::exists('outlet_groups', 'id')],
            'bed_count' => ['nullable', 'integer', 'min:0'],
            'crm_id' => ['nullable', 'integer', 'min:1'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Atribut kolom outlets (tanpa group, yang disimpan sebagai keanggotaan).
     *
     * @return array<string, mixed>
     */
    public function outletAttributes(): array
    {
        return $this->safe()->except('outlet_group_id');
    }

    public function groupId(): ?int
    {
        return $this->validated('outlet_group_id') ? (int) $this->validated('outlet_group_id') : null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'address' => 'alamat',
            'area_id' => 'area',
            'outlet_category_id' => 'kategori',
            'ownership_type_id' => 'kepemilikan',
            'outlet_group_id' => 'group',
            'bed_count' => 'jumlah bed',
            'crm_id' => 'CRM ID',
            'is_active' => 'status aktif',
        ];
    }
}
