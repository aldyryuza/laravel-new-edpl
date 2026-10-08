<?php

namespace App\Models\Dpl;

use App\Models\Outlet;
use App\Support\AuditLog;
use Database\Factories\Dpl\DistributorMarginOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Margin khusus outlet per grup distributor (legacy dpl_distributor_spesial_margin_outlet, KODEDIST = grup).
 * Berlaku untuk cabang grup itu yang ditandai is_special_case_margin.
 */
#[Table('dpl.distributor_margin_overrides')]
#[Fillable(['distributor_group_code', 'outlet_id', 'margin', 'note', 'is_active'])]
class DistributorMarginOverride extends Model
{
    /** @use HasFactory<DistributorMarginOverrideFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['distributor_group_code', 'outlet_id', 'margin', 'note', 'is_active'],
            fn () => "Margin khusus outlet {$this->distributor_group_code} - {$this->outlet?->code}",
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'margin' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
