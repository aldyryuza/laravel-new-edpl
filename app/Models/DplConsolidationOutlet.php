<?php

namespace App\Models;

use Database\Factories\DplConsolidationOutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outlet anggota consolidation beserta DPL per outlet yang dihasilkan. Hanya created_at.
 */
#[Table('dpl.dpl_consolidation_outlets')]
#[Fillable(['consolidation_id', 'outlet_id', 'generated_dpl_id'])]
class DplConsolidationOutlet extends Model
{
    /** @use HasFactory<DplConsolidationOutletFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<DplConsolidation, $this>
     */
    public function consolidation(): BelongsTo
    {
        return $this->belongsTo(DplConsolidation::class, 'consolidation_id');
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function generatedDpl(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'generated_dpl_id');
    }
}
