<?php

namespace App\Models\Dpl;

use App\Models\User;
use Database\Factories\Dpl\ApprovalOverrideRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request override/open lock (mis. calculator di atas threshold).
 */
#[Table('dpl.approval_override_requests')]
#[Fillable([
    'dpl_proposal_id', 'dpl_item_id', 'requested_by_user_id', 'request_reason', 'status',
    'reviewed_by_user_id', 'review_reason', 'requested_at', 'reviewed_at',
])]
class ApprovalOverrideRequest extends Model
{
    /** @use HasFactory<ApprovalOverrideRequestFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
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
     * @return BelongsTo<DplItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(DplItem::class, 'dpl_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
