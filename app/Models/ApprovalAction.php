<?php

namespace App\Models;

use Database\Factories\ApprovalActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event approval, append-only: jangan update/delete baris lama. Hanya created_at.
 * dpl_item_id null = aksi level step/proposal.
 */
#[Table('dpl.approval_actions')]
#[Fillable(['step_instance_id', 'dpl_item_id', 'actor_user_id', 'action', 'reason', 'metadata'])]
class ApprovalAction extends Model
{
    /** @use HasFactory<ApprovalActionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const APPROVE = 'APPROVE';

    public const REJECT = 'REJECT';

    public const RETURN = 'RETURN';

    public const CANCEL = 'CANCEL';

    public const OVERRIDE = 'OVERRIDE';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ApprovalStepInstance, $this>
     */
    public function stepInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalStepInstance::class, 'step_instance_id');
    }

    /**
     * @return BelongsTo<DplItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(DplItem::class, 'dpl_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
