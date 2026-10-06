<?php

namespace App\Models;

use Database\Factories\UserOrgAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scope organisasi user (area + team) secara historis. Role tetap di user_has_roles.
 */
#[Fillable(['user_id', 'area_id', 'team_code', 'valid_from', 'valid_until', 'is_active'])]
class UserOrgAssignment extends Model
{
    /** @use HasFactory<UserOrgAssignmentFactory> */
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SalesArea, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(SalesArea::class, 'area_id');
    }
}
