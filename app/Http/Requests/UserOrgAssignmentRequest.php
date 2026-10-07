<?php

namespace App\Http\Requests;

use App\Models\UserOrgAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserOrgAssignmentRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:user-assignments.*` di controller.
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'scope_type' => ['required', Rule::in([
                UserOrgAssignment::SCOPE_NATIONAL, UserOrgAssignment::SCOPE_ZONE, UserOrgAssignment::SCOPE_REGION, UserOrgAssignment::SCOPE_AREA,
            ])],
            'zone_id' => ['nullable', 'required_if:scope_type,'.UserOrgAssignment::SCOPE_ZONE, 'integer', Rule::exists('sales_zones', 'id')],
            'region_id' => ['nullable', 'required_if:scope_type,'.UserOrgAssignment::SCOPE_REGION, 'integer', Rule::exists('sales_regions', 'id')],
            'area_id' => ['nullable', 'required_if:scope_type,'.UserOrgAssignment::SCOPE_AREA, 'integer', Rule::exists('sales_areas', 'id')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * User yang sama tidak boleh punya dua penugasan aktif dengan wilayah + team sama dan periode bertumpang tindih.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('is_active')) {
                    return;
                }

                $data = $this->assignmentData();
                $query = UserOrgAssignment::query()
                    ->where('user_id', $data['user_id'])
                    ->where('is_active', true)
                    ->when($this->route('user_assignment'), fn ($query, UserOrgAssignment $current) => $query->whereKeyNot($current->getKey()))
                    ->when($data['valid_until'] !== null, fn ($query) => $query->where(
                        fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $data['valid_until']),
                    ))
                    ->when($data['valid_from'] !== null, fn ($query) => $query->where(
                        fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $data['valid_from']),
                    ));

                foreach (['zone_id', 'region_id', 'area_id', 'team_id'] as $column) {
                    $data[$column] === null ? $query->whereNull($column) : $query->where($column, $data[$column]);
                }

                if ($query->exists()) {
                    $validator->errors()->add('user_id', 'User ini sudah punya penugasan aktif dengan wilayah dan team yang sama pada periode tersebut.');
                }
            },
        ];
    }

    /**
     * Data siap simpan: zona/region/area dikosongkan sesuai scope (Nasional = ketiganya kosong).
     *
     * @return array{user_id: int, zone_id: int|null, region_id: int|null, area_id: int|null, team_id: int|null, valid_from: string|null, valid_until: string|null, is_active: bool}
     */
    public function assignmentData(): array
    {
        $scope = $this->input('scope_type');

        return [
            'user_id' => (int) $this->input('user_id'),
            'zone_id' => $scope === UserOrgAssignment::SCOPE_ZONE ? (int) $this->input('zone_id') : null,
            'region_id' => $scope === UserOrgAssignment::SCOPE_REGION ? (int) $this->input('region_id') : null,
            'area_id' => $scope === UserOrgAssignment::SCOPE_AREA ? (int) $this->input('area_id') : null,
            'team_id' => $this->input('team_id') ? (int) $this->input('team_id') : null,
            'valid_from' => $this->input('valid_from') ?: null,
            'valid_until' => $this->input('valid_until') ?: null,
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
            'scope_type' => 'wilayah',
            'zone_id' => 'zona',
            'region_id' => 'region',
            'area_id' => 'area',
            'team_id' => 'team',
            'valid_from' => 'berlaku mulai',
            'valid_until' => 'berlaku sampai',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'zone_id.required_if' => 'Zona wajib dipilih untuk wilayah Zona.',
            'region_id.required_if' => 'Region wajib dipilih untuk wilayah Region.',
            'area_id.required_if' => 'Area wajib dipilih untuk wilayah Area.',
        ];
    }
}
