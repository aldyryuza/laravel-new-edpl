<?php

namespace App\Models;

use Database\Factories\UserAreaScopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scope visibility eksplisit per area (mis. KAE). Bukan sumber approver.
 */
#[Fillable(['user_id', 'area_id', 'valid_from', 'valid_until'])]
class UserAreaScope extends Model
{
    /** @use HasFactory<UserAreaScopeFactory> */
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
