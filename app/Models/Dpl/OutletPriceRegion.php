<?php

namespace App\Models\Dpl;

use App\Models\Outlet;
use App\Support\AuditLog;
use Database\Factories\Dpl\OutletPriceRegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Region harga sebuah outlet untuk keperluan DPL (satu per outlet). Dipisah dari master outlet
 * (public) agar master tidak bergantung pada schema dpl.
 */
#[Table('dpl.outlet_price_regions')]
#[Fillable(['outlet_id', 'price_region_id'])]
class OutletPriceRegion extends Model
{
    /** @use HasFactory<OutletPriceRegionFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['outlet_id', 'price_region_id'], fn () => "Region harga outlet {$this->outlet?->code}");
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<PriceRegion, $this>
     */
    public function priceRegion(): BelongsTo
    {
        return $this->belongsTo(PriceRegion::class);
    }
}
