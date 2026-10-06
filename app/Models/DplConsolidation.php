<?php

namespace App\Models;

use Database\Factories\DplConsolidationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Parent proses consolidation / RS Group. Nilai status lifecycle belum final.
 */
#[Table('dpl.dpl_consolidations')]
#[Fillable(['code', 'created_by_user_id', 'status'])]
class DplConsolidation extends Model
{
    /** @use HasFactory<DplConsolidationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<DplConsolidationOutlet, $this>
     */
    public function outlets(): HasMany
    {
        return $this->hasMany(DplConsolidationOutlet::class, 'consolidation_id');
    }
}
