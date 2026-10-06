<?php

namespace App\Models;

use Database\Factories\DplRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot revisi proposal yang sama. Append-only; hanya created_at.
 */
#[Table('dpl.dpl_revisions')]
#[Fillable(['dpl_proposal_id', 'revision_number', 'reason', 'snapshot', 'created_by_user_id'])]
class DplRevision extends Model
{
    /** @use HasFactory<DplRevisionFactory> */
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
            'revision_number' => 'integer',
            'snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<DplProposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(DplProposal::class, 'dpl_proposal_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
