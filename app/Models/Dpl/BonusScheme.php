<?php

namespace App\Models\Dpl;

use App\Support\AuditLog;
use Database\Factories\Dpl\BonusSchemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Skema bonus (legacy dpl_bonus).
 */
#[Table('dpl.bonus_schemes')]
#[Fillable([
    'code', 'name', 'bonus', 'discount_invoice', 'discount_bonus', 'discount_rounded', 'total_discount', 'is_active',
])]
class BonusScheme extends Model
{
    /** @use HasFactory<BonusSchemeFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['code', 'name', 'bonus', 'discount_invoice', 'discount_bonus', 'discount_rounded', 'total_discount', 'is_active'], fn () => "Skema bonus {$this->name}");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bonus' => 'decimal:4',
            'discount_invoice' => 'decimal:4',
            'discount_bonus' => 'decimal:4',
            'discount_rounded' => 'decimal:4',
            'total_discount' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<DplItem, $this>
     */
    public function dplItems(): HasMany
    {
        return $this->hasMany(DplItem::class);
    }
}
