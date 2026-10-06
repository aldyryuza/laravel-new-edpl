<?php

namespace App\Models\Dpl;

use App\Models\User;
use Database\Factories\Dpl\ApprovalStepInstanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Step approval aktual. Kandidat approver di-snapshot ke approval_step_assignees saat instance
 * dibuat (boleh lebih dari satu; salah satu cukup). Jangan di-resolve ulang dari ApproverAssignment.
 */
#[Table('dpl.approval_step_instances')]
#[Fillable(['approval_instance_id', 'workflow_step_id', 'status', 'started_at', 'completed_at'])]
class ApprovalStepInstance extends Model
{
    /** @use HasFactory<ApprovalStepInstanceFactory> */
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
     * @return BelongsTo<ApprovalInstance, $this>
     */
    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class);
    }

    /**
     * @return BelongsTo<ApprovalWorkflowStep, $this>
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflowStep::class, 'workflow_step_id');
    }

    /**
     * Snapshot kandidat approver step ini.
     *
     * @return HasMany<ApprovalStepAssignee, $this>
     */
    public function assignees(): HasMany
    {
        return $this->hasMany(ApprovalStepAssignee::class, 'step_instance_id');
    }

    /**
     * User kandidat approver (lewat snapshot approval_step_assignees).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assigneeUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'dpl.approval_step_assignees', 'step_instance_id', 'user_id');
    }

    /**
     * @return HasMany<ApprovalItemState, $this>
     */
    public function itemStates(): HasMany
    {
        return $this->hasMany(ApprovalItemState::class, 'step_instance_id');
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'step_instance_id');
    }
}
