<?php

namespace App\Models;

use Database\Factories\UserVisibilityEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Viewer boleh melihat pengajuan milik owner (transformasi legacy users_level_order).
 * Hanya visibility KAE, BUKAN hierarchy approval.
 */
#[Fillable(['viewer_user_id', 'owner_user_id', 'relation_type', 'valid_from', 'valid_until'])]
class UserVisibilityEdge extends Model
{
    /** @use HasFactory<UserVisibilityEdgeFactory> */
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
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
}
