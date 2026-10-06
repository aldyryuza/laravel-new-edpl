<?php

namespace App\Models\Dpl;

use App\Models\Role;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use App\Support\AuditLog;
use Database\Factories\Dpl\ApproverAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Master resolusi approver. Scope dipilih eksplisit, tidak diturunkan dari nama role:
 * - region: region_id terisi (mis. RM)
 * - zone  : zone_id terisi (mis. NSM)
 * - global: keduanya null (mis. GM/SMD/DMD)
 *
 * Boleh lebih dari satu approver aktif per role + scope (BR-026); semua menjadi kandidat.
 */
#[Table('dpl.approver_assignments')]
#[Fillable(['role_id', 'region_id', 'zone_id', 'user_id', 'valid_from', 'valid_until', 'is_active'])]
class ApproverAssignment extends Model
{
    /** @use HasFactory<ApproverAssignmentFactory> */
    use HasFactory, LogsActivity;

    public const SCOPE_REGION = 'region';

    public const SCOPE_ZONE = 'zone';

    public const SCOPE_GLOBAL = 'global';

    public function scopeType(): string
    {
        return match (true) {
            $this->region_id !== null => self::SCOPE_REGION,
            $this->zone_id !== null => self::SCOPE_ZONE,
            default => self::SCOPE_GLOBAL,
        };
    }

    public function getActivitylogOptions(): LogOptions
    {
        return AuditLog::dataOptions(['role_id', 'region_id', 'zone_id', 'user_id', 'valid_from', 'valid_until', 'is_active'], fn () => "Approver {$this->role?->name} {$this->user?->name}");
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

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<SalesRegion, $this>
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(SalesRegion::class, 'region_id');
    }

    /**
     * @return BelongsTo<SalesZone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(SalesZone::class, 'zone_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
