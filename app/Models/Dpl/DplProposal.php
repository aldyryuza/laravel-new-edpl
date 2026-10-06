<?php

namespace App\Models\Dpl;

use App\Models\Outlet;
use App\Models\User;
use Database\Factories\Dpl\DplProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Header pengajuan DPL. proposal_status (pengajuan/approval) dan program_status (aktivasi program)
 * adalah konsep terpisah. Maksimal satu DPL ACTIVE per outlet + team (partial unique index).
 */
#[Table('dpl.dpl_proposals')]
#[Fillable([
    'dpl_number', 'outlet_id', 'team_code', 'created_by_user_id', 'date_offer', 'date_start', 'date_end',
    'is_contract', 'bed_count_snapshot', 'proposal_type', 'proposal_status', 'program_status', 'description',
    'submitted_at', 'approved_at',
])]
class DplProposal extends Model
{
    /** @use HasFactory<DplProposalFactory> */
    use HasFactory, SoftDeletes;

    public const TYPE_NORMAL = 'NORMAL';

    public const TYPE_CLEARANCE = 'CLEARANCE';

    public const TYPE_CONSOLIDATION = 'CONSOLIDATION';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SUBMITTED = 'SUBMITTED';

    public const STATUS_IN_APPROVAL = 'IN_APPROVAL';

    public const STATUS_PARTIALLY_APPROVED = 'PARTIALLY_APPROVED';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    public const STATUS_CANCELLED = 'CANCELLED';

    public const PROGRAM_INACTIVE = 'INACTIVE';

    public const PROGRAM_ACTIVE = 'ACTIVE';

    public const PROGRAM_EXPIRED = 'EXPIRED';

    public const PROGRAM_SUPERSEDED = 'SUPERSEDED';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_offer' => 'date',
            'date_start' => 'date',
            'date_end' => 'date',
            'is_contract' => 'boolean',
            'bed_count_snapshot' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<DplItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DplItem::class);
    }

    /**
     * @return HasMany<DplStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(DplStatusHistory::class);
    }

    /**
     * @return HasMany<DplRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(DplRevision::class);
    }

    /**
     * Relasi di mana DPL ini menjadi sumber (mis. DPL ini di-amend/renew menjadi DPL lain).
     *
     * @return HasMany<DplRelation, $this>
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(DplRelation::class, 'source_dpl_id');
    }

    /**
     * Relasi di mana DPL ini menjadi target (mis. DPL ini hasil amendment/renewal).
     *
     * @return HasMany<DplRelation, $this>
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(DplRelation::class, 'target_dpl_id');
    }

    /**
     * @return HasMany<ApprovalInstance, $this>
     */
    public function approvalInstances(): HasMany
    {
        return $this->hasMany(ApprovalInstance::class);
    }

    /**
     * @return HasMany<ApprovalOverrideRequest, $this>
     */
    public function overrideRequests(): HasMany
    {
        return $this->hasMany(ApprovalOverrideRequest::class);
    }

    /**
     * @return HasMany<ExitLetter, $this>
     */
    public function exitLetters(): HasMany
    {
        return $this->hasMany(ExitLetter::class);
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'entity');
    }
}
