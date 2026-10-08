<?php

namespace App\Models\Dpl;

use App\Models\Distributor;
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
 * Margin khusus outlet per distributor (legacy dpl_distributor_spesial_margin_outlet).
 * Hanya berlaku bila distributor is_special_case_margin.
 */
#[Table('dpl.distributor_margin_overrides')]
#[Fillable(['distributor_id', 'outlet_id', 'margin', 'note', 'is_active'])]
class DistributorMarginOverride extends Model
{
    /** @use HasFactory<DistributorMarginOverrideFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['distributor_id', 'outlet_id', 'margin', 'note', 'is_active'],
            fn () => "Margin khusus outlet {$this->distributor?->code} - {$this->outlet?->code}",
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
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
