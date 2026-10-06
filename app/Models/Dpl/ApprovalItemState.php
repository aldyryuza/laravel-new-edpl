<?php

namespace App\Models\Dpl;

use Database\Factories\Dpl\ApprovalItemStateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * State approval saat ini per DPL item per step (dasar partial approved).
 */
#[Table('dpl.approval_item_states')]
#[Fillable(['step_instance_id', 'dpl_item_id', 'status', 'approved_at', 'rejected_at', 'reject_reason'])]
class ApprovalItemState extends Model
{
    /** @use HasFactory<ApprovalItemStateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
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
}
