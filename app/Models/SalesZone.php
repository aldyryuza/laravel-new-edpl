<?php

namespace App\Models;

use Database\Factories\SalesZoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Zona penjualan. NSM approver di-resolve per zona.
 */
#[Fillable(['code', 'name', 'is_active'])]
class SalesZone extends Model
{
    /** @use HasFactory<SalesZoneFactory> */
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
     * @return HasMany<SalesRegion, $this>
     */
    public function regions(): HasMany
    {
        return $this->hasMany(SalesRegion::class, 'zone_id');
    }
}
