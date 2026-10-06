<?php

namespace App\Models;

use Database\Factories\ApprovalWorkflowStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Step workflow; approver step ditentukan lewat role existing (role_id).
 */
#[Table('dpl.approval_workflow_steps')]
#[Fillable(['workflow_id', 'sequence', 'role_id', 'can_edit', 'can_reject', 'is_required'])]
class ApprovalWorkflowStep extends Model
{
    /** @use HasFactory<ApprovalWorkflowStepFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'can_edit' => 'boolean',
            'can_reject' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ApprovalWorkflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
