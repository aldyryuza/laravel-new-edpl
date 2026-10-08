<?php

namespace App\Models\Dpl;

use App\Models\Outlet;
use App\Models\User;
use Database\Factories\Dpl\DplConsolidationOutletFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outlet anggota consolidation: Kontrak, Bed, "25% Bed" per outlet, serta pemilik dan DPL anak yang dihasilkan. Hanya created_at.
 */
#[Table('dpl.dpl_consolidation_outlets')]
#[Fillable(['consolidation_id', 'outlet_id', 'is_contract', 'bed_count_snapshot', 'bed_order_minimum', 'owner_user_id', 'generated_dpl_id'])]
class DplConsolidationOutlet extends Model
{
    /** @use HasFactory<DplConsolidationOutletFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_contract' => 'boolean',
            'bed_count_snapshot' => 'integer',
            'bed_order_minimum' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

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
