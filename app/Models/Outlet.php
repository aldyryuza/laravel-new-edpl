<?php

namespace App\Models;

use App\Models\Dpl\DplProposal;
use App\Models\Dpl\PriceRegion;
use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master outlet. Region/zone outlet diturunkan dari area (area -> region -> zone).
 */
#[Fillable([
    'code', 'name', 'address', 'area_id', 'outlet_category_id', 'ownership_type_id', 'bed_count',
    'price_region_id', 'crm_id', 'latitude', 'longitude', 'is_active',
])]
class Outlet extends Model
{
    /** @use HasFactory<OutletFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bed_count' => 'integer',
            'crm_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<SalesArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(SalesArea::class, 'area_id');
    }

    /**
     * @return BelongsTo<OutletCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(OutletCategory::class, 'outlet_category_id');
    }

    /**
     * @return BelongsTo<OwnershipType, $this>
     */
    public function ownershipType(): BelongsTo
    {
        return $this->belongsTo(OwnershipType::class);
    }

    /**
     * @return BelongsTo<PriceRegion, $this>
     */
    public function priceRegion(): BelongsTo
    {
        return $this->belongsTo(PriceRegion::class);
    }

    /**
     * @return HasMany<OutletGroupMember, $this>
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(OutletGroupMember::class);
    }

    /**
     * @return HasMany<DplProposal, $this>
     */
    public function dplProposals(): HasMany
    {
        return $this->hasMany(DplProposal::class);
    }
}
