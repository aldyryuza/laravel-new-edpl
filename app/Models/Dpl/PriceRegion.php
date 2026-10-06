<?php

namespace App\Models\Dpl;

use App\Models\Distributor;
use App\Models\Outlet;
use Database\Factories\Dpl\PriceRegionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Region harga (legacy dpl_price_region).
 */
#[Table('dpl.price_regions')]
#[Fillable(['code', 'name', 'description', 'is_active'])]
class PriceRegion extends Model
{
    /** @use HasFactory<PriceRegionFactory> */
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
     * @return HasMany<ProductPrice, $this>
     */
    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Outlet yang memakai region harga ini (lewat mapping dpl.outlet_price_regions).
     *
     * @return BelongsToMany<Outlet, $this>
     */
    public function outlets(): BelongsToMany
    {
        return $this->belongsToMany(Outlet::class, 'dpl.outlet_price_regions')->withTimestamps();
    }

    /**
     * Distributor yang memakai region harga ini (lewat mapping dpl.distributor_price_regions).
     *
     * @return BelongsToMany<Distributor, $this>
     */
    public function distributors(): BelongsToMany
    {
        return $this->belongsToMany(Distributor::class, 'dpl.distributor_price_regions')->withTimestamps();
    }
}
