<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\ApprovalWorkflow;
use App\Models\Dpl\ApprovalWorkflowStep;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Aturan jalur approval item: rentang Total Calculator (bawah eksklusif, atas inklusif, kosong = tanpa batas),
 * step wajib (workflow DPL_STANDARD), dan provinsi outlet (kosong = semua provinsi).
 */
class ApprovalRuleRequest extends FormRequest
{
    /** Batas kolom numeric(10,4). */
    public const MAX_PERCENT = '999999.9999';

    /**
     * Hak akses dicek oleh middleware `can:approval-rules.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $percent = ['nullable', 'numeric', 'min:-'.self::MAX_PERCENT, 'max:'.self::MAX_PERCENT, 'decimal:0,4'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'discount_lower' => $percent,
            'discount_upper' => $percent,
            'step_ids' => ['required', 'array', 'min:1'],
            // Hanya step workflow DPL_STANDARD (string rule exists:dpl.* tidak dipakai untuk schema dpl).
            'step_ids.*' => ['integer', 'distinct', Rule::in(self::standardStepIds())],
            'province_ids' => ['nullable', 'array'],
            'province_ids.*' => ['integer', 'distinct', Rule::exists('provinces', 'id')],
            'sort_order' => ['nullable', 'integer', 'min:-2147483648', 'max:2147483647'],
            'note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Batas atas harus lebih besar dari batas bawah bila keduanya diisi (sama dengan CHECK constraint tabel).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['discount_lower', 'discount_upper'])) {
                    return;
                }

                $lower = $this->input('discount_lower');
                $upper = $this->input('discount_upper');

                if ($lower !== null && $upper !== null && (float) $upper <= (float) $lower) {
                    $validator->errors()->add('discount_upper', 'Batas "sampai dengan" harus lebih besar dari batas "lebih dari".');
                }
            },
        ];
    }

    /**
     * Id step workflow DPL_STANDARD.
     *
     * @return array<int, int>
     */
    public static function standardStepIds(): array
    {
        return ApprovalWorkflowStep::query()
            ->whereHas('workflow', fn ($query) => $query->where('code', ApprovalWorkflow::CODE_DPL_STANDARD))
            ->pluck('id')
            ->all();
    }

    /**
     * Data header aturan siap simpan.
     *
     * @return array{name: string, discount_lower: string|null, discount_upper: string|null, sort_order: int, note: string|null, is_active: bool}
     */
    public function ruleData(): array
    {
        return [
            'name' => $this->input('name'),
            'discount_lower' => $this->input('discount_lower'),
            'discount_upper' => $this->input('discount_upper'),
            'sort_order' => (int) $this->input('sort_order', 0),
            'note' => $this->input('note') ?: null,
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function stepIds(): array
    {
        return array_map('intval', $this->input('step_ids', []));
    }

    /**
     * Kosong = semua provinsi.
     *
     * @return array<int, int>
     */
    public function provinceIds(): array
    {
        return array_map('intval', $this->input('province_ids') ?? []);
    }

    /**
     * Field kosong dari form = null (tanpa batas / semua provinsi); urutan kosong = 0.
     */
    protected function prepareForValidation(): void
    {
        foreach (['discount_lower', 'discount_upper', 'sort_order'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'discount_lower' => 'lebih dari',
            'discount_upper' => 'sampai dengan',
            'step_ids' => 'step wajib',
            'step_ids.*' => 'step wajib',
            'province_ids' => 'provinsi',
            'province_ids.*' => 'provinsi',
            'sort_order' => 'urutan',
            'note' => 'catatan',
            'is_active' => 'status aktif',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'step_ids.required' => 'Pilih minimal satu step wajib.',
            'step_ids.min' => 'Pilih minimal satu step wajib.',
            'step_ids.*.in' => 'Step harus salah satu step workflow DPL standard.',
            'province_ids.*.exists' => 'Provinsi tidak ditemukan.',
        ];
    }
}
