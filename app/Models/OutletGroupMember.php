<?php

namespace App\Models;

use Database\Factories\OutletGroupMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['outlet_group_id', 'outlet_id', 'valid_from', 'valid_until'])]
class OutletGroupMember extends Model
{
    /** @use HasFactory<OutletGroupMemberFactory> */
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
     * @return BelongsTo<OutletGroup, $this>
     */
    public function outletGroup(): BelongsTo
    {
        return $this->belongsTo(OutletGroup::class);
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
