<?php

namespace App\Models\Dpl;

use App\Models\Role;
use App\Models\SalesRegion;
use App\Models\SalesZone;
use App\Models\User;
use Database\Factories\Dpl\ApproverAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master resolusi approver:
 * - RM : region_id terisi, zone_id null
 * - NSM: zone_id terisi, region_id null
 * - GM/SMD/DMD: keduanya null (global)
 */
#[Table('dpl.approver_assignments')]
#[Fillable(['role_id', 'region_id', 'zone_id', 'user_id', 'valid_from', 'valid_until', 'is_active'])]
class ApproverAssignment extends Model
{
    /** @use HasFactory<ApproverAssignmentFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
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
