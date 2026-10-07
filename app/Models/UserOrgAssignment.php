<?php

namespace App\Models;

use App\Support\AuditLog;
use Database\Factories\UserOrgAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Wilayah & team user (historis lewat periode berlaku). Scope: salah satu dari zona/region/area,
 * atau ketiganya kosong = Nasional (melihat semua wilayah). Role tetap di user_has_roles.
 * Dipakai untuk membatasi outlet/distributor yang terlihat (lihat App\Support\RegionScope).
 */
#[Fillable(['user_id', 'zone_id', 'region_id', 'area_id', 'team_id', 'valid_from', 'valid_until', 'is_active'])]
class UserOrgAssignment extends Model
{
    /** @use HasFactory<UserOrgAssignmentFactory> */
    use HasFactory, LogsActivity;

    public const SCOPE_NATIONAL = 'national';

    public const SCOPE_ZONE = 'zone';

    public const SCOPE_REGION = 'region';

    public const SCOPE_AREA = 'area';

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(
            ['user_id', 'zone_id', 'region_id', 'area_id', 'team_id', 'valid_from', 'valid_until', 'is_active'],
            fn () => "Wilayah user {$this->user?->name} ({$this->user?->nik})",
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }

    public function scopeType(): string
    {
        return match (true) {
            $this->area_id !== null => self::SCOPE_AREA,
            $this->region_id !== null => self::SCOPE_REGION,
            $this->zone_id !== null => self::SCOPE_ZONE,
            default => self::SCOPE_NATIONAL,
        };
    }

    /**
     * Penugasan yang aktif dan berlaku pada tanggal tertentu (default hari ini).
     *
     * @param  Builder<UserOrgAssignment>  $query
     */
    #[Scope]
    protected function effective(Builder $query, ?string $date = null): void
    {
        $date ??= now()->toDateString();

        $query->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date))
            ->where(fn (Builder $query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $date));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SalesZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SalesZone::class, 'zone_id');
    }

    /**
     * @return BelongsTo<SalesRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(SalesRegion::class, 'region_id');
    }

    /**
     * @return BelongsTo<SalesArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(SalesArea::class, 'area_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
