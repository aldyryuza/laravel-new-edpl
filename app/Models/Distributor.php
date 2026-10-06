<?php

namespace App\Models;

use App\Models\Dpl\DistributorBranchHead;
use App\Support\AuditLog;
use Database\Factories\DistributorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Master distributor beserta cabangnya.
 */
#[Fillable(['code', 'name', 'group_code', 'city_name', 'is_branch', 'parent_distributor_id', 'is_active', 'netsuite_id'])]
class Distributor extends Model
{
    /** @use HasFactory<DistributorFactory> */
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['code', 'name', 'group_code', 'city_name', 'is_branch', 'parent_distributor_id', 'is_active', 'netsuite_id'], fn () => "Distributor {$this->name} ({$this->code})");
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_branch' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Distributor, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Distributor::class, 'parent_distributor_id');
    }

    /**
     * @return HasMany<Distributor, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Distributor::class, 'parent_distributor_id');
    }

    /**
     * Pivot distributor_regions (tanpa timestamps).
     *
     * @return BelongsToMany<SalesRegion, $this>
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(SalesRegion::class, 'distributor_regions', 'distributor_id', 'region_id');
    }

    /**
     * @return HasMany<DistributorBranchHead, $this>
     */
    public function branchHeads(): HasMany
    {
        return $this->hasMany(DistributorBranchHead::class);
    }
}
