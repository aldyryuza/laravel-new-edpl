<?php

namespace App\Models;

use App\Models\Dpl\DplProposal;
use App\Support\AuditLog;
use Database\Factories\OutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Master outlet (schema public, dipakai lintas sistem). Region/zone outlet diturunkan dari area
 * (area -> region -> zone).
 */
#[Fillable([
    'code', 'name', 'address', 'city_id', 'area_id', 'outlet_category_id', 'ownership_type_id', 'bed_count',
    'crm_id', 'latitude', 'longitude', 'is_active',
])]
class Outlet extends Model
{
    /** @use HasFactory<OutletFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['code', 'name', 'address', 'city_id', 'area_id', 'outlet_category_id', 'ownership_type_id', 'bed_count', 'crm_id', 'latitude', 'longitude', 'is_active'], fn () => "Outlet {$this->name} ({$this->code})");
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
     * Outlet di wilayah user (area tercakup); user Nasional melihat semua, tanpa penugasan tidak melihat apa pun.
     *
     * @param  Builder<Outlet>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $user->regionScope()->applyToOutlets($query);
    }

    /**
     * Kota administratif (master kota), terpisah dari area penjualan.
     *
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
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
     * PIC outlet (AM / SPV), pemilik DPL anak konsolidasi Group RS.
     *
     * @return HasOne<OutletSalesAssignment, $this>
     */
    public function salesAssignment(): HasOne
    {
        return $this->hasOne(OutletSalesAssignment::class);
    }

    /**
     * @return HasMany<DplProposal, $this>
     */
    public function dplProposals(): HasMany
    {
        return $this->hasMany(DplProposal::class);
    }
}
