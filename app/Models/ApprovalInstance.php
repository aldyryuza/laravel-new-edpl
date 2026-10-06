<?php

namespace App\Models;

use Database\Factories\ApprovalInstanceFactory;
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
