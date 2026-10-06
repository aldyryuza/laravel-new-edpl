<?php

namespace App\Models;

use Database\Factories\OutletGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Group rumah sakit / group beberapa outlet (legacy GRUP3).
 */
#[Fillable(['code', 'name', 'description', 'is_active'])]
class OutletGroup extends Model
{
    /** @use HasFactory<OutletGroupFactory> */
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
     * Keanggotaan beserta periode berlaku.
     *
     * @return HasMany<OutletGroupMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(OutletGroupMember::class);
    }
}
