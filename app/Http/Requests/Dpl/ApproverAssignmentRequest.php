<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\ApprovalWorkflowStep;
use App\Models\Dpl\ApproverAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ApproverAssignmentRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:approver-assignments.*` di controller.
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
            // Hanya role yang dipakai sebagai step di workflow approval.
            'role_id' => ['required', 'integer', Rule::in(ApprovalWorkflowStep::query()->distinct()->pluck('role_id')->all())],
            'scope_type' => ['required', Rule::in([ApproverAssignment::SCOPE_REGION, ApproverAssignment::SCOPE_ZONE, ApproverAssignment::SCOPE_GLOBAL])],
            'region_id' => ['nullable', 'required_if:scope_type,'.ApproverAssignment::SCOPE_REGION, 'integer', Rule::exists('sales_regions', 'id')],
            'zone_id' => ['nullable', 'required_if:scope_type,'.ApproverAssignment::SCOPE_ZONE, 'integer', Rule::exists('sales_zones', 'id')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * User yang sama tidak boleh punya dua assignment aktif dengan role + scope sama dan periode bertumpang tindih.
     * Approver berbeda pada role + scope yang sama tetap diizinkan (BR-026).
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

                $duplicate = ApproverAssignment::query()
                    ->where('user_id', $data['user_id'])
                    ->where('role_id', $data['role_id'])
                    ->where('is_active', true)
                    ->when($data['region_id'] === null, fn ($query) => $query->whereNull('region_id'), fn ($query) => $query->where('region_id', $data['region_id']))
                    ->when($data['zone_id'] === null, fn ($query) => $query->whereNull('zone_id'), fn ($query) => $query->where('zone_id', $data['zone_id']))
                    ->when($this->route('approver_assignment'), fn ($query, ApproverAssignment $current) => $query->whereKeyNot($current->getKey()))
                    ->when($data['valid_until'] !== null, fn ($query) => $query->where(
                        fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $data['valid_until']),
                    ))
                    ->when($data['valid_from'] !== null, fn ($query) => $query->where(
                        fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $data['valid_from']),
                    ))
                    ->exists();

                if ($duplicate) {
                    $validator->errors()->add('user_id', 'User ini sudah menjadi approver aktif untuk role dan scope yang sama pada periode tersebut.');
                }
            },
        ];
    }

    /**
     * Data siap simpan: region/zone dikosongkan sesuai scope yang dipilih.
     *
     * @return array{role_id: int, region_id: int|null, zone_id: int|null, user_id: int, valid_from: string|null, valid_until: string|null, is_active: bool}
     */
    public function assignmentData(): array
    {
        $scope = $this->input('scope_type');

        return [
            'role_id' => (int) $this->input('role_id'),
            'region_id' => $scope === ApproverAssignment::SCOPE_REGION ? (int) $this->input('region_id') : null,
            'zone_id' => $scope === ApproverAssignment::SCOPE_ZONE ? (int) $this->input('zone_id') : null,
            'user_id' => (int) $this->input('user_id'),
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
            'role_id' => 'role',
            'scope_type' => 'scope',
            'region_id' => 'region',
            'zone_id' => 'zona',
            'user_id' => 'user',
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
            'role_id.in' => 'Role harus salah satu role pada workflow approval.',
            'user_id.exists' => 'User tidak ditemukan atau sudah nonaktif.',
            'region_id.required_if' => 'Region wajib dipilih untuk scope Region.',
            'zone_id.required_if' => 'Zona wajib dipilih untuk scope Zona.',
        ];
    }
}
