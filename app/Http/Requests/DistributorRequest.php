<?php

namespace App\Http\Requests;

use App\Models\Distributor;
use App\Services\DistributorService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DistributorRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:distributors.*` di controller.
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
            'code' => ['required', 'string', 'max:30', Rule::unique('distributors', 'code')->ignore($this->route('distributor'))],
            'name' => ['required', 'string', 'max:255'],
            'group_code' => ['nullable', 'string', 'max:50'],
            'city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'meeting_radius_meters' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_branch' => ['required', 'boolean'],
            'parent_distributor_id' => ['nullable', 'integer', Rule::exists('distributors', 'id')],
            'region_ids' => ['array'],
            'region_ids.*' => ['integer', 'distinct', Rule::exists('sales_regions', 'id')],
            'netsuite_id' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Induk tidak boleh distributor itu sendiri atau salah satu cabangnya (hierarki melingkar).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('parent_distributor_id')) {
                    return;
                }

                /** @var Distributor|null $distributor */
                $distributor = $this->route('distributor');
                $parentId = $this->integer('parent_distributor_id') ?: null;

                if (app(DistributorService::class)->createsCycle($distributor, $parentId)) {
                    $validator->errors()->add('parent_distributor_id', 'Induk tidak boleh distributor ini sendiri atau salah satu cabangnya.');
                }
            },
        ];
    }

    /**
     * Atribut kolom distributors (tanpa region yang disimpan di pivot).
     *
     * @return array<string, mixed>
     */
    public function distributorAttributes(): array
    {
        return $this->safe()->except(['region_ids']);
    }

    /**
     * @return array<int, int>
     */
    public function regionIds(): array
    {
        return array_map('intval', $this->validated('region_ids', []));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode',
            'name' => 'nama',
            'group_code' => 'grup distributor',
            'city_id' => 'kota',
            'address' => 'alamat',
            'phone' => 'telepon',
            'npwp' => 'NPWP',
            'meeting_radius_meters' => 'radius meeting',
            'is_branch' => 'cabang',
            'parent_distributor_id' => 'induk',
            'region_ids' => 'region',
            'region_ids.*' => 'region',
            'netsuite_id' => 'NetSuite ID',
            'is_active' => 'status aktif',
        ];
    }
}
