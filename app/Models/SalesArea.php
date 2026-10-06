<?php

namespace App\Models;

use Database\Factories\SalesAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Area penjualan (zone -> region -> area).
 */
#[Fillable(['code', 'name', 'region_id', 'is_active'])]
class SalesArea extends Model
{
    /** @use HasFactory<SalesAreaFactory> */
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
     * @return BelongsTo<SalesRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(SalesRegion::class, 'region_id');
    }

    /**
     * @return HasMany<Outlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(Outlet::class, 'area_id');
    }
}
