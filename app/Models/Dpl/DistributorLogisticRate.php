<?php

namespace App\Models\Dpl;

use App\Models\City;
use App\Support\AuditLog;
use Database\Factories\Dpl\DistributorLogisticRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Tarif logistic fee (%) khusus grup distributor + kota (pengganti legacy DPL_UDC_LOGISTIC_PER_AREA).
 */
#[Table('dpl.distributor_logistic_rates')]
#[Fillable(['distributor_group_code', 'city_id', 'logistic_fee', 'note', 'is_active'])]
class DistributorLogisticRate extends Model
{
    /** @use HasFactory<DistributorLogisticRateFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['distributor_group_code', 'city_id', 'logistic_fee', 'note', 'is_active'],
            fn () => "Tarif logistic {$this->distributor_group_code} - {$this->city?->name}",
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
            'logistic_fee' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
