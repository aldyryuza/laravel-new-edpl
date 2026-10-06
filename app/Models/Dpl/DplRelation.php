<?php

namespace App\Models\Dpl;

use App\Models\User;
use Database\Factories\Dpl\DplRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Relasi antar DPL. Pengganti flag legacy ref_id_dpl / from_dpl_action / tag_make_a_copy.
 */
#[Table('dpl.dpl_relations')]
#[Fillable(['source_dpl_id', 'target_dpl_id', 'relation_type', 'created_by_user_id'])]
class DplRelation extends Model
{
    /** @use HasFactory<DplRelationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Legacy "Make a Copy": dari DPL active, periode tetap. */
    public const TYPE_AMENDMENT = 'AMENDMENT';

    /** Legacy "Duplicate": periode baru, approval dari awal. */
    public const TYPE_RENEWAL = 'RENEWAL';

    public const TYPE_CONSOLIDATION_CHILD = 'CONSOLIDATION_CHILD';

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'source_dpl_id');
    }

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'target_dpl_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
