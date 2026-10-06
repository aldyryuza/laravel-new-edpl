<?php

namespace App\Models;

use App\Models\Dpl\DplProposal;
use App\Support\AuditLog;
use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Master outlet (schema public, dipakai lintas sistem). Region/zone outlet diturunkan dari area
 * (area -> region -> zone). Region harga outlet milik DPL ada di Dpl\OutletPriceRegion.
 */
#[Fillable([
    'code', 'name', 'address', 'area_id', 'outlet_category_id', 'ownership_type_id', 'bed_count',
    'crm_id', 'latitude', 'longitude', 'is_active',
])]
class Outlet extends Model
{
    /** @use HasFactory<OutletFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['code', 'name', 'address', 'area_id', 'outlet_category_id', 'ownership_type_id', 'bed_count', 'crm_id', 'latitude', 'longitude', 'is_active'], fn () => "Outlet {$this->name} ({$this->code})");
    }

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
     * Seluruh histori keanggotaan group.
     *
     * @return HasMany<OutletGroupMember, $this>
     */
    public function groupMemberships(): HasMany
    {
        return $this->hasMany(OutletGroupMember::class);
    }

    /**
     * Keanggotaan group yang sedang berlaku (valid_until kosong); maksimal satu per outlet.
     *
     * @return HasOne<OutletGroupMember, $this>
     */
    public function activeGroupMembership(): HasOne
    {
        return $this->hasOne(OutletGroupMember::class)->whereNull('valid_until');
    }

    /**
     * @return HasMany<DplProposal, $this>
     */
    public function dplProposals(): HasMany
    {
        return $this->hasMany(DplProposal::class);
    }
}
