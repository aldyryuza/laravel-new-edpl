<?php

namespace App\Models\Dpl;

use Database\Factories\Dpl\ApprovalInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eksekusi workflow approval untuk satu DPL. Restart approval = instance baru.
 */
#[Table('dpl.approval_instances')]
#[Fillable(['dpl_proposal_id', 'workflow_id', 'status', 'started_at', 'completed_at'])]
class ApprovalInstance extends Model
{
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    /** @use HasFactory<ApprovalInstanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<ApprovalWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /**
     * @return HasMany<ApprovalStepInstance, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStepInstance::class);
    }
}
