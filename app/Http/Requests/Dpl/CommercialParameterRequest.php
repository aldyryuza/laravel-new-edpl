<?php

namespace App\Http\Requests\Dpl;

use App\Models\Dpl\CommercialParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommercialParameterRequest extends FormRequest
{
    /**
     * Hak akses dicek oleh middleware `can:commercial-parameters.*` di controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kode dirujuk program (mis. kalkulator), jadi dibatasi format KODE_HURUF_BESAR.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $valueRules = match ($this->input('value_type')) {
            CommercialParameter::TYPE_NUMBER => ['required', 'numeric', 'decimal:0,4'],
            CommercialParameter::TYPE_BOOLEAN => ['required', 'boolean'],
            default => ['required', 'string', 'max:1000'],
        };

        return [
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/', Rule::unique(CommercialParameter::class, 'code')->ignore($this->route('commercial_parameter'))],
            'value_type' => ['required', Rule::in([CommercialParameter::TYPE_NUMBER, CommercialParameter::TYPE_TEXT, CommercialParameter::TYPE_BOOLEAN])],
            'value' => $valueRules,
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Data siap simpan: nilai dibungkus {type, value}; angka dinormalkan sebagai string desimal.
     *
     * @return array{code: string, value: array{type: string, value: string|bool}, description: string|null, is_active: bool}
     */
    public function parameterData(): array
    {
        $type = $this->validated('value_type');
        $raw = $this->validated('value');

        $value = match ($type) {
            CommercialParameter::TYPE_NUMBER => $this->normalizeNumber((string) $raw),
            CommercialParameter::TYPE_BOOLEAN => $this->boolean('value'),
            default => (string) $raw,
        };

        return [
            'code' => $this->validated('code'),
            'value' => CommercialParameter::wrapValue($type, $value),
            'description' => $this->validated('description'),
            'is_active' => $this->boolean('is_active'),
        ];
    }

    /**
     * "35.50" -> "35.5", "035" -> "35"; tetap string agar tidak menjadi float.
     */
    private function normalizeNumber(string $number): string
    {
        $number = trim($number);
        $negative = str_starts_with($number, '-');
        [$integer, $fraction] = array_pad(explode('.', ltrim($number, '+-'), 2), 2, '');
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = rtrim($fraction, '0');
        $result = $fraction === '' ? $integer : "{$integer}.{$fraction}";

        return $negative && $result !== '0' ? "-{$result}" : $result;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'value_type' => 'tipe nilai', 'value' => 'nilai', 'description' => 'keterangan', 'is_active' => 'status aktif'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => 'Kode harus huruf besar, angka, atau garis bawah dan diawali huruf, mis. MAX_DPL_DISCOUNT.'];
    }
}
