<?php

namespace App\Models\Dpl;

use App\Models\Distributor;
use App\Support\AuditLog;
use Database\Factories\Dpl\DistributorPriceRegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Region harga sebuah distributor untuk keperluan DPL (satu per distributor; legacy PRICE_REGION_ID).
 * Harga DPL memakai region harga outlet dan distributor; prioritasnya diatur di modul Commercial.
 */
#[Table('dpl.distributor_price_regions')]
#[Fillable(['distributor_id', 'price_region_id'])]
class DistributorPriceRegion extends Model
{
    /** @use HasFactory<DistributorPriceRegionFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['distributor_id', 'price_region_id'], fn () => "Region harga distributor {$this->distributor?->code}");
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    /**
     * @return BelongsTo<PriceRegion, $this>
     */
    public function priceRegion(): BelongsTo
    {
        return $this->belongsTo(PriceRegion::class);
    }
}
