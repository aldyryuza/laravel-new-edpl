<?php

namespace App\Models;

use Database\Factories\SalesRegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regional penjualan. RM approver di-resolve per region.
 */
#[Fillable(['code', 'name', 'zone_id', 'is_active'])]
class SalesRegion extends Model
{
    /** @use HasFactory<SalesRegionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SalesZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SalesZone::class, 'zone_id');
    }

    /**
     * @return HasMany<SalesArea, $this>
     */
    public function areas(): HasMany
    {
        return $this->hasMany(SalesArea::class, 'region_id');
    }

    /**
     * @return BelongsToMany<Distributor, $this>
     */
    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class, 'distributor_regions', 'region_id', 'distributor_id');
    }
}
