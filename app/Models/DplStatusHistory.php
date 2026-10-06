<?php

namespace App\Models;

use Database\Factories\DplStatusHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Business history transisi status DPL. Append-only; hanya created_at.
 */
#[Table('dpl.dpl_status_histories')]
#[Fillable(['dpl_proposal_id', 'status_type', 'old_status', 'new_status', 'reason', 'changed_by_user_id'])]
class DplStatusHistory extends Model
{
    /** @use HasFactory<DplStatusHistoryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPE_PROPOSAL = 'PROPOSAL';

    public const TYPE_PROGRAM = 'PROGRAM';

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'dpl_proposal_id');
    }

    /**
     * Null bila transisi dilakukan sistem.
     *
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
