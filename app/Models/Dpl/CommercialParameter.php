<?php

namespace App\Models\Dpl;

use App\Support\AuditLog;
use Database\Factories\Dpl\CommercialParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Parameter bisnis configurable (mis. MAX_DPL_DISCOUNT), tidak di-hardcode di kode/schema.
 *
 * value (jsonb) berbentuk {"type": "number|text|boolean", "value": ...}. Angka disimpan sebagai
 * string desimal agar presisi (bukan float); baca lewat typedValue().
 */
#[Table('dpl.commercial_parameters')]
#[Fillable(['code', 'value', 'description', 'is_active'])]
class CommercialParameter extends Model
{
    /** @use HasFactory<CommercialParameterFactory> */
    use HasFactory, LogsActivity;

    public const TYPE_NUMBER = 'number';

    public const TYPE_TEXT = 'text';

    public const TYPE_BOOLEAN = 'boolean';

    /**
     * Contoh parameter dari docs/Schema.md; hanya contoh, nilai final diisi setelah review bisnis.
     *
     * @var array<string, string>
     */
    public const SUGGESTED_CODES = [
        'MAX_DPL_DISCOUNT' => 'Batas persentase kalkulator/diskon DPL sebelum butuh override (legacy 35)',
        'MIN_MONTHLY_ORDER' => 'Minimal order per bulan untuk diskon SAI (legacy 200 botol)',
        'MAX_PROGRAM_MONTHS' => 'Maksimal masa program DPL dalam bulan (legacy 12)',
    ];

    /**
     * @param  string|bool  $value  angka sebagai string desimal, teks, atau boolean
     * @return array{type: string, value: string|bool}
     */
    public static function wrapValue(string $type, string|bool $value): array
    {
        return ['type' => $type, 'value' => $value];
    }

    public function valueType(): string
    {
        return $this->value['type'] ?? self::TYPE_TEXT;
    }

    /**
     * Nilai sesuai tipenya: angka (string desimal), teks, atau boolean.
     */
    public function typedValue(): string|bool|null
    {
        return $this->value['value'] ?? null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['code', 'value', 'description', 'is_active'], fn () => "Parameter komersial {$this->code}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_active' => 'boolean',
        ];
    }
}
