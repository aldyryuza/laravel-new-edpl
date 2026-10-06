<?php

namespace App\Models\Dpl;

use App\Models\User;
use Database\Factories\Dpl\ApprovalStepAssigneeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot kandidat approver sebuah step saat approval dimulai. Satu step bisa punya beberapa
 * kandidat (mis. 2 RM dalam satu region); cukup salah satu yang bertindak. Hanya created_at.
 */
#[Table('dpl.approval_step_assignees')]
#[Fillable(['step_instance_id', 'user_id'])]
class ApprovalStepAssignee extends Model
{
    /** @use HasFactory<ApprovalStepAssigneeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<ApprovalStepInstance, $this>
     */
    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'step_instance_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
